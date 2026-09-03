# -*- coding: utf-8 -*-
import json
import logging
import hmac
import hashlib
from odoo import http, fields, _
from odoo.http import request

_logger = logging.getLogger(__name__)


class PrintHubWebhookController(http.Controller):
    """
    Receives async webhook status updates from Print Hub (e.g. job.completed, job.failed).
    Includes HMAC signature verification, chatter logging, bus notifications, and mail activities.
    """

    @http.route(['/print-hub/webhook', '/api/print-hub/webhook'], type='http', auth='public', methods=['POST'], csrf=False)
    def receive_webhook(self, **kwargs):
        try:
            raw_body = request.httprequest.data or b''

            # ── 1. Optional HMAC Signature Verification ──
            secret = request.env['ir.config_parameter'].sudo().get_param('print_hub.webhook_secret')
            if secret and raw_body:
                sig_header = (
                    request.httprequest.headers.get('X-Webhook-Signature')
                    or request.httprequest.headers.get('X-Hub-Signature-256')
                    or ''
                )
                if sig_header:
                    expected_sig = hmac.new(secret.encode('utf-8'), raw_body, hashlib.sha256).hexdigest()
                    provided_sig = sig_header.replace('sha256=', '').strip()
                    if not hmac.compare_digest(provided_sig, expected_sig):
                        _logger.warning("Invalid webhook HMAC signature from IP %s", request.httprequest.remote_addr)
                        return request.make_response(
                            json.dumps({'status': 'error', 'message': 'Invalid signature verification.'}),
                            headers=[('Content-Type', 'application/json')],
                            status=403
                        )

            # ── 2. Parse Payload ──
            if raw_body:
                try:
                    data = json.loads(raw_body)
                except Exception:
                    data = request.params or {}
            else:
                data = request.params or {}

            # If payload is wrapped in 'payload' (from Print Hub WebhookService)
            if 'payload' in data and isinstance(data['payload'], dict):
                event_type = data.get('event_type')
                payload = data['payload']
                job_id = payload.get('job_id')
                ref_id = payload.get('reference_id')
                status = payload.get('status')
                error = payload.get('error') or payload.get('error_message')
                printer = payload.get('printer') or payload.get('printer_name')
            else:
                event_type = data.get('event_type') or data.get('event')
                job_id = data.get('job_id')
                ref_id = data.get('reference_id')
                status = data.get('status')
                error = data.get('error') or data.get('error_message')
                printer = data.get('printer') or data.get('printer_name')

            _logger.info("Print Hub webhook received: event=%s, job_id=%s, ref=%s, status=%s",
                         event_type, job_id, ref_id, status)

            # ── 3. Update PrintHubJob Record ──
            JobModel = request.env['print.hub.job'].sudo()
            job_record = None
            if job_id:
                job_record = JobModel.search([('name', '=', job_id)], limit=1)

            # Fallback search by reference if job_id was not yet matched
            if not job_record and ref_id and ',' in ref_id:
                parts = ref_id.split(',')
                if len(parts) >= 2 and parts[1].isdigit():
                    job_record = JobModel.search([
                        ('model_name', '=', parts[0]),
                        ('res_id', '=', int(parts[1])),
                        ('status', 'in', ['draft', 'spooling', 'pending_approval'])
                    ], order='id desc', limit=1)

            if job_record:
                if status == 'success' or event_type == 'job.completed':
                    job_record.write({
                        'status': 'completed',
                        'error_message': False,
                        'completed_at': fields.Datetime.now(),
                        'printer_name': printer or job_record.printer_name,
                    })
                    job_record.message_post(
                        body=_("✅ Print completed successfully on printer <b>%s</b>.") % (printer or job_record.printer_name or 'Default')
                    )
                elif status == 'failed' or event_type == 'job.failed':
                    job_record.write({
                        'status': 'failed',
                        'error_message': error or 'Unknown print agent failure',
                        'completed_at': fields.Datetime.now(),
                    })
                    job_record.message_post(
                        body=_("❌ Print failed on agent/printer: <span class='text-danger'>%s</span>") % (error or 'Unknown failure')
                    )
                elif status in ('spooling', 'printing') or event_type == 'job.spooling':
                    job_record.write({
                        'status': 'spooling',
                    })

            # ── 4. Post Notification to Source Record Chatter ──
            if ref_id and ',' in ref_id:
                parts = ref_id.split(',')
                model_name = parts[0]
                record_id = parts[1]

                if record_id.isdigit() and model_name in request.env:
                    record = request.env[model_name].sudo().browse(int(record_id))
                    if record.exists() and hasattr(record, 'message_post'):
                        if status == 'success' or event_type == 'job.completed':
                            record.message_post(
                                body=f"<p>🖨️ <b>Print Hub Spool Succeeded</b></p>"
                                     f"<p>Job ID: <code>{job_id or (job_record.name if job_record else 'N/A')}</code><br/>"
                                     f"Printer: {printer or (job_record.printer_name if job_record else 'Default')}</p>",
                                message_type='notification'
                            )
                        elif status == 'failed' or event_type == 'job.failed':
                            record.message_post(
                                body=f"<p>⚠️ <b>Print Hub Spool Failed</b></p>"
                                     f"<p>Job ID: <code>{job_id or (job_record.name if job_record else 'N/A')}</code><br/>"
                                     f"Error: {error or 'Unknown spool failure'}</p>",
                                message_type='notification'
                            )

            # ── 5. Real-Time Bus Notification to the User ──
            if job_record and job_record.user_id:
                target_partner = job_record.user_id.partner_id
                if target_partner:
                    is_success = (status == 'success' or event_type == 'job.completed')
                    is_failed = (status == 'failed' or event_type == 'job.failed')
                    doc_title = job_record.report_display_name or job_record.name

                    notif_data = {
                        'type': 'success' if is_success else ('danger' if is_failed else 'info'),
                        'title': _('🖨️ Cetak Selesai') if is_success else (_('⚠️ Cetak Gagal') if is_failed else _('🖨️ Printing')),
                        'message': (
                            _("Dokumen %s selesai dicetak di %s.") % (doc_title, printer or job_record.printer_name or 'Printer')
                            if is_success else (
                                _("Gagal mencetak %s: %s") % (doc_title, error or 'Error')
                                if is_failed else
                                _("Dokumen %s sedang diproses...") % doc_title
                            )
                        ),
                        'sticky': is_failed,
                    }

                    try:
                        if hasattr(target_partner, '_bus_send'):
                            target_partner._bus_send('simple_notification', notif_data)
                        elif hasattr(request.env['bus.bus'], '_sendone'):
                            request.env['bus.bus'].sudo()._sendone(target_partner, 'simple_notification', notif_data)
                    except Exception as bus_err:
                        _logger.debug("Bus notification error: %s", bus_err)

            # ── 6. Create To-Do Activity on Failure ──
            if (status == 'failed' or event_type == 'job.failed') and job_record:
                if ref_id and ',' in ref_id:
                    parts = ref_id.split(',')
                    model_name = parts[0]
                    record_id = parts[1]
                    if record_id.isdigit() and model_name in request.env:
                        src_rec = request.env[model_name].sudo().browse(int(record_id))
                        if src_rec.exists() and hasattr(src_rec, 'activity_schedule'):
                            try:
                                act_type = request.env.ref('mail.mail_activity_data_todo', raise_if_not_found=False)
                                if act_type:
                                    src_rec.activity_schedule(
                                        activity_type_id=act_type.id,
                                        summary=_("🖨️ Cetak Gagal di Print Hub"),
                                        note=_("Pencetakan dokumen gagal: %s. Silakan periksa di menu Print Hub Jobs.") % (error or 'Error tidak diketahui'),
                                        user_id=job_record.user_id.id or request.env.uid
                                    )
                            except Exception as act_err:
                                _logger.debug("Activity schedule error: %s", act_err)

            return request.make_response(
                json.dumps({'status': 'success', 'message': 'Webhook processed.'}),
                headers=[('Content-Type', 'application/json')]
            )
        except Exception as e:
            _logger.exception("Error processing Print Hub webhook: %s", e)
            return request.make_response(
                json.dumps({'status': 'error', 'message': str(e)}),
                headers=[('Content-Type', 'application/json')],
                status=500
            )
