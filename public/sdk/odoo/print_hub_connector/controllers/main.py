# -*- coding: utf-8 -*-
import json
import logging
from odoo import http, fields
from odoo.http import request

_logger = logging.getLogger(__name__)


class PrintHubWebhookController(http.Controller):
    """
    Receives async webhook status updates from Print Hub (e.g. job.completed, job.failed).
    """

    @http.route('/print-hub/webhook', type='json', auth='public', methods=['POST'], csrf=False)
    def receive_webhook(self, **kwargs):
        try:
            data = request.jsonrequest or {}
            event_type = data.get('event_type') or data.get('event')
            job_id = data.get('job_id')
            ref_id = data.get('reference_id')
            status = data.get('status')
            error = data.get('error') or data.get('error_message')
            printer = data.get('printer') or data.get('printer_name')

            _logger.info("Print Hub webhook received: event=%s, job_id=%s, ref=%s, status=%s",
                         event_type, job_id, ref_id, status)

            # 1. Update matching PrintHubJob record
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
                        ('status', 'in', ['draft', 'spooling'])
                    ], order='id desc', limit=1)

            if job_record:
                if status == 'success' or event_type == 'job.completed':
                    job_record.write({
                        'status': 'completed',
                        'error_message': False,
                        'completed_at': fields.Datetime.now(),
                        'printer_name': printer or job_record.printer_name,
                    })
                elif status == 'failed' or event_type == 'job.failed':
                    job_record.write({
                        'status': 'failed',
                        'error_message': error or 'Unknown print agent failure',
                        'completed_at': fields.Datetime.now(),
                    })
                elif status in ('spooling', 'printing') or event_type == 'job.spooling':
                    job_record.write({
                        'status': 'spooling',
                    })

            # 2. Post notification to the source record chatter
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
                                     f"Printer: {printer or 'Default Printer'}</p>",
                                message_type='notification'
                            )
                        elif status == 'failed' or event_type == 'job.failed':
                            record.message_post(
                                body=f"<p>⚠️ <b>Print Hub Spool Failed</b></p>"
                                     f"<p>Job ID: <code>{job_id or (job_record.name if job_record else 'N/A')}</code><br/>"
                                     f"Error: {error or 'Unknown spool failure'}</p>",
                                message_type='notification'
                            )

            return {'status': 'success', 'message': 'Webhook processed.'}
        except Exception as e:
            _logger.exception("Error processing Print Hub webhook: %s", e)
            return {'status': 'error', 'message': str(e)}
