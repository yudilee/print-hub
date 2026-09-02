# -*- coding: utf-8 -*-
import base64
import json
import logging
import requests
from odoo import models, api, fields, _

_logger = logging.getLogger(__name__)


class PrintHubMixin(models.AbstractModel):
    """
    Enhanced Mixin class providing direct background printing, JSON template printing,
    batch tree-view spooling, dynamic watermarking, and printer health checking.
    """
    _name = 'print.hub.mixin'
    _description = 'Print Hub Direct Printing Mixin'

    def print_via_hub(self, report_name=None, options=None):
        """
        Main print dispatcher. Resolves routing rules, evaluates dynamic watermarks,
        checks printer health, and dispatches either QWeb PDF or JSON Template.
        """
        self.ensure_one()
        options = dict(options or {})

        # Step 1: Check Document Routing Rules (print.hub.rule)
        matched_rule = self._resolve_matching_rule(report_name, options.get('branch_code'))

        # If rule specifies JSON Template printing and no explicit PDF requested, route to template
        if matched_rule and matched_rule.routing_type == 'template_json' and matched_rule.template_slug:
            return self.print_via_hub_template(
                template_slug=matched_rule.template_slug,
                options=options,
                matched_rule=matched_rule
            )

        # Step 2: Resolve Settings & Targets
        resolved = self._resolve_print_targets(matched_rule, options)
        if not resolved.get('hub_url') or not resolved.get('api_key'):
            return {'success': False, 'error': _('Print Hub is not configured in Settings.')}

        # Step 3: Evaluate Dynamic Watermark
        watermark_data = self._resolve_dynamic_watermark(matched_rule, options)
        if watermark_data:
            options['watermark'] = watermark_data

        # Step 4: Check Target Printer Health & Warnings
        printer_warning = self._check_printer_health(resolved.get('printer'))
        if printer_warning:
            _logger.warning("Printer health advisory for %s: %s", resolved.get('printer'), printer_warning)

        # Step 5: Render QWeb PDF
        try:
            pdf_content, _ = self.env['ir.actions.report']._render_qweb_pdf(report_name, self.ids)
        except Exception as e:
            _logger.exception("Failed to render QWeb PDF for %s: %s", report_name, e)
            return {'success': False, 'error': f"Failed to render QWeb PDF: {e}"}

        ref_id = f"{self._name},{self.id}"

        # Step 6: Create Job Log
        job_log = self.env['print.hub.job'].sudo().create({
            'name': f"temp_{self._name}_{self.id}_{int(fields.Datetime.now().timestamp())}",
            'report_name': report_name or 'custom_report',
            'model_name': self._name,
            'res_id': self.id,
            'user_id': self.env.user.id,
            'branch_code': resolved.get('branch_code'),
            'printer_name': resolved.get('printer'),
            'queue': resolved.get('queue'),
            'copies': resolved.get('copies', 1),
            'status': 'draft',
            'sent_at': fields.Datetime.now(),
        })

        payload = {
            'document_base64': base64.b64encode(pdf_content).decode('utf-8'),
            'reference_id': ref_id,
            'options': {
                'copies': resolved.get('copies', 1),
                **{k: v for k, v in options.items() if k not in ['branch_code', 'queue', 'printer', 'copies']},
            },
        }

        if resolved.get('branch_code'):
            payload['branch_code'] = resolved['branch_code']
        if resolved.get('queue'):
            payload['queue'] = resolved['queue']
        if resolved.get('printer'):
            payload['printer'] = resolved['printer']
        if resolved.get('pool_name'):
            payload['pool'] = resolved['pool_name']

        return self._send_to_hub(resolved['hub_url'], resolved['api_key'], payload, job_log)

    def print_via_hub_template(self, template_slug=None, data_dict=None, options=None, matched_rule=None):
        """
        Ultra-Fast Direct JSON Template Printing: Bypasses QWeb and sends raw record data
        to Print Hub's Continuous Form layout engine.
        """
        self.ensure_one()
        options = dict(options or {})
        matched_rule = matched_rule or self._resolve_matching_rule(template_slug, options.get('branch_code'))
        resolved = self._resolve_print_targets(matched_rule, options)

        if not resolved.get('hub_url') or not resolved.get('api_key'):
            return {'success': False, 'error': _('Print Hub is not configured in Settings.')}

        target_template = template_slug or (matched_rule.template_slug if matched_rule else None)
        if not target_template:
            return {'success': False, 'error': _('No Print Hub template specified.')}

        # Serialize record data if not provided
        payload_data = data_dict or self._get_print_hub_data()

        # Dynamic Watermark
        watermark_data = self._resolve_dynamic_watermark(matched_rule, options)
        if watermark_data:
            options['watermark'] = watermark_data

        ref_id = f"{self._name},{self.id}"

        job_log = self.env['print.hub.job'].sudo().create({
            'name': f"temp_tpl_{self._name}_{self.id}_{int(fields.Datetime.now().timestamp())}",
            'report_name': f"Template: {target_template}",
            'model_name': self._name,
            'res_id': self.id,
            'user_id': self.env.user.id,
            'branch_code': resolved.get('branch_code'),
            'printer_name': resolved.get('printer'),
            'queue': resolved.get('queue'),
            'copies': resolved.get('copies', 1),
            'status': 'draft',
            'sent_at': fields.Datetime.now(),
        })

        payload = {
            'template': target_template,
            'data': payload_data,
            'reference_id': ref_id,
            'options': {
                'copies': resolved.get('copies', 1),
                **{k: v for k, v in options.items() if k not in ['branch_code', 'queue', 'printer', 'copies']},
            },
        }

        if resolved.get('branch_code'):
            payload['branch_code'] = resolved['branch_code']
        if resolved.get('queue'):
            payload['queue'] = resolved['queue']
        if resolved.get('printer'):
            payload['printer'] = resolved['printer']
        if resolved.get('pool_name'):
            payload['pool'] = resolved['pool_name']

        return self._send_to_hub(resolved['hub_url'], resolved['api_key'], payload, job_log)

    def print_batch_via_hub(self, report_name=None, template_slug=None, options=None):
        """
        High-Speed Batch Spooling: Packages up to 50 records in `self` and submits
        a single multi-job request to POST /api/v1/print/batch.
        """
        if not self:
            return {'success': False, 'error': _('No records selected for batch printing.')}

        options = dict(options or {})
        ICP = self.env['ir.config_parameter'].sudo()
        hub_url = ICP.get_param('print_hub.url', '')
        api_key = ICP.get_param('print_hub.api_key', '')

        if not hub_url or not api_key:
            return {'success': False, 'error': _('Print Hub is not configured in Settings.')}

        batch_jobs = []
        logs = []

        for record in self:
            matched_rule = record._resolve_matching_rule(report_name or template_slug, options.get('branch_code'))
            resolved = record._resolve_print_targets(matched_rule, options)
            ref_id = f"{record._name},{record.id}"

            # Check if using template vs QWeb
            if template_slug or (matched_rule and matched_rule.routing_type == 'template_json'):
                tgt_tpl = template_slug or matched_rule.template_slug
                job_payload = {
                    'template': tgt_tpl,
                    'data': record._get_print_hub_data(),
                    'reference_id': ref_id,
                }
            else:
                try:
                    pdf_content, _ = record.env['ir.actions.report']._render_qweb_pdf(report_name, record.ids)
                    job_payload = {
                        'document_base64': base64.b64encode(pdf_content).decode('utf-8'),
                        'reference_id': ref_id,
                    }
                except Exception as e:
                    _logger.warning("Batch render failed for record %s: %s", record.id, e)
                    continue

            if resolved.get('branch_code'):
                job_payload['branch_code'] = resolved['branch_code']
            if resolved.get('queue'):
                job_payload['queue'] = resolved['queue']
            if resolved.get('printer'):
                job_payload['printer'] = resolved['printer']
            if resolved.get('pool_name'):
                job_payload['pool'] = resolved['pool_name']

            job_log = record.env['print.hub.job'].sudo().create({
                'name': f"batch_temp_{record._name}_{record.id}_{int(fields.Datetime.now().timestamp())}",
                'report_name': report_name or f"Template: {template_slug}",
                'model_name': record._name,
                'res_id': record.id,
                'user_id': record.env.user.id,
                'branch_code': resolved.get('branch_code'),
                'printer_name': resolved.get('printer'),
                'queue': resolved.get('queue'),
                'copies': resolved.get('copies', 1),
                'status': 'draft',
                'sent_at': fields.Datetime.now(),
            })

            batch_jobs.append(job_payload)
            logs.append(job_log)

        if not batch_jobs:
            return {'success': False, 'error': _('No valid documents could be prepared for batch.')}

        try:
            endpoint = f"{hub_url.rstrip('/')}/api/v1/print/batch"
            resp = requests.post(
                endpoint,
                headers={
                    'X-API-Key': api_key,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                json={'jobs': batch_jobs},
                timeout=30,
            )

            result = resp.json()
            if resp.status_code in (200, 201, 202):
                jobs_created = result.get('data', {}).get('jobs', [])
                for idx, log in enumerate(logs):
                    if idx < len(jobs_created):
                        log.write({
                            'name': jobs_created[idx].get('job_id', log.name),
                            'status': 'draft',
                            'printer_name': jobs_created[idx].get('printer') or log.printer_name,
                        })
                return {
                    'success': True,
                    'created_jobs_count': len(jobs_created),
                    'jobs': jobs_created
                }
            else:
                err = result.get('error', {}).get('message', resp.text)
                for log in logs:
                    log.write({'status': 'failed', 'error_message': err})
                return {'success': False, 'error': err}
        except Exception as e:
            err = f"Connection to Print Hub failed: {e}"
            for log in logs:
                log.write({'status': 'failed', 'error_message': err})
            return {'success': False, 'error': err}

    # ── Internal Helpers ──

    def _get_print_hub_data(self):
        """Default serializer extracting standard document fields for JSON templating."""
        data = {
            'id': self.id,
            'name': getattr(self, 'name', f"{self._name} #{self.id}"),
            'display_name': self.display_name,
            'date': str(getattr(self, 'date_done', getattr(self, 'invoice_date', getattr(self, 'date_order', fields.Date.today())))),
            'user': self.env.user.name,
            'company': {
                'name': self.env.company.name,
                'vat': self.env.company.vat or '',
                'phone': self.env.company.phone or '',
            }
        }

        # Partner extraction
        partner = getattr(self, 'partner_id', None)
        if partner:
            data['partner_name'] = partner.name
            data['customer_name'] = partner.name
            data['customer_address'] = partner.contact_address or ''
            data['recipient_phone'] = partner.phone or partner.mobile or ''

        # Line items extraction (stock.move.line / account.move.line / order_line)
        lines = getattr(self, 'move_line_ids', getattr(self, 'invoice_line_ids', getattr(self, 'order_line', None)))
        if lines:
            items = []
            for line in lines:
                product = getattr(line, 'product_id', None)
                qty = getattr(line, 'qty_done', getattr(line, 'quantity', getattr(line, 'product_uom_qty', 0)))
                items.append({
                    'product_code': product.default_code if product else '',
                    'product_name': product.name if product else (line.name or ''),
                    'quantity': float(qty),
                    'uom': getattr(line, 'product_uom_id', getattr(line, 'product_uom', None)).name if getattr(line, 'product_uom_id', None) else '',
                })
            data['items'] = items

        return data

    def _resolve_matching_rule(self, report_or_template, branch_override=None):
        RuleModel = self.env['print.hub.rule'].sudo()
        target_branch = branch_override or getattr(self.env.user, 'print_hub_branch_code', None)
        rules = RuleModel.search([
            ('active', '=', True),
            '|', '|',
            ('report_technical_name', '=', report_or_template),
            ('report_id.report_name', '=', report_or_template),
            ('template_slug', '=', report_or_template),
        ], order='sequence, id')

        if target_branch:
            for r in rules:
                if r.branch_code == target_branch:
                    return r
        for r in rules:
            if not r.branch_code:
                return r
        return None

    def _resolve_print_targets(self, matched_rule, options):
        ICP = self.env['ir.config_parameter'].sudo()
        user = self.env.user
        return {
            'hub_url': ICP.get_param('print_hub.url', ''),
            'api_key': ICP.get_param('print_hub.api_key', ''),
            'branch_code': (
                options.get('branch_code')
                or (matched_rule.branch_code if matched_rule and matched_rule.branch_code else None)
                or getattr(user, 'print_hub_branch_code', None)
                or ICP.get_param('print_hub.default_branch', '')
            ),
            'queue': (
                options.get('queue')
                or (matched_rule.queue if matched_rule and matched_rule.queue else None)
                or getattr(user, 'print_hub_queue', None)
                or ICP.get_param('print_hub.default_queue', '')
            ),
            'printer': (
                options.get('printer')
                or (matched_rule.printer_name if matched_rule and matched_rule.printer_name else None)
                or getattr(user, 'print_hub_printer', None)
            ),
            'pool_name': (
                options.get('pool')
                or (matched_rule.pool_name if matched_rule and matched_rule.pool_name else None)
            ),
            'copies': (
                options.get('copies')
                or (matched_rule.copies if matched_rule and matched_rule.copies else None)
                or getattr(user, 'print_hub_copies', None)
                or 1
            ),
        }

    def _resolve_dynamic_watermark(self, matched_rule, options):
        rule_type = matched_rule.watermark_rule if matched_rule else 'state_based'
        if rule_type == 'none':
            return None
        if rule_type == 'custom' and matched_rule.watermark_text:
            return {
                'text': matched_rule.watermark_text,
                'opacity': matched_rule.watermark_opacity or 0.15,
                'rotation': 45
            }

        # State-based dynamic watermark
        rec_state = getattr(self, 'state', None)
        if rec_state in ('draft', 'proforma', 'proforma2'):
            return {'text': 'DRAFT', 'opacity': 0.15, 'rotation': 45}
        if rec_state == 'cancel':
            return {'text': 'CANCELLED', 'opacity': 0.20, 'rotation': 45}

        # Check reprint count in Print Hub Job log
        existing_prints = self.env['print.hub.job'].sudo().search_count([
            ('model_name', '=', self._name),
            ('res_id', '=', self.id),
            ('status', '=', 'completed')
        ])
        if existing_prints > 0:
            return {'text': 'COPY / DUPLICATE', 'opacity': 0.12, 'rotation': 45}

        return None

    def _check_printer_health(self, printer_name):
        if not printer_name:
            return None
        printer = self.env['print.hub.printer'].sudo().search([('name', '=', printer_name)], limit=1)
        if printer:
            if printer.status == 'offline':
                return _("Printer %s is currently OFFLINE.") % printer_name
            if printer.paper_status in ('out', 'jammed'):
                return _("Printer %s status: %s.") % (printer_name, printer.paper_status.upper())
        return None

    def _send_to_hub(self, hub_url, api_key, payload, job_log):
        try:
            endpoint = f"{hub_url.rstrip('/')}/api/v1/print"
            resp = requests.post(
                endpoint,
                headers={
                    'X-API-Key': api_key,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                json=payload,
                timeout=20,
            )

            result = resp.json()
            if resp.status_code in (200, 201, 202):
                data = result.get('data', {})
                hub_job_id = data.get('job_id', 'unknown')
                job_log.write({
                    'name': hub_job_id,
                    'status': 'draft',
                    'agent_name': data.get('agent'),
                    'printer_name': data.get('printer') or job_log.printer_name,
                })
                return {
                    'success': True,
                    'job_id': hub_job_id,
                    'log_id': job_log.id,
                    'agent': data.get('agent'),
                    'printer': data.get('printer'),
                }
            else:
                error_msg = result.get('error', {}).get('message', resp.text)
                job_log.write({'status': 'failed', 'error_message': error_msg})
                return {'success': False, 'error': error_msg, 'log_id': job_log.id}
        except Exception as e:
            err = f"Connection to Print Hub failed: {e}"
            job_log.write({'status': 'failed', 'error_message': err})
            return {'success': False, 'error': err, 'log_id': job_log.id}
