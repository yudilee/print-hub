# -*- coding: utf-8 -*-
import base64
import json
import logging
from odoo import models, fields, api, _
from odoo.exceptions import UserError

_logger = logging.getLogger(__name__)


class PrintHubPrintWizard(models.TransientModel):
    _name = 'print.hub.print.wizard'
    _description = 'Universal Print Hub Direct Print Wizard'

    model_name = fields.Char(string='Target Model', required=True)
    res_ids_str = fields.Char(string='Target Record IDs', required=True)
    record_count = fields.Integer(string='Selected Records', compute='_compute_record_count')
    record_name = fields.Char(string='Document Title', readonly=True)

    report_id = fields.Many2one(
        'ir.actions.report',
        string='Document Report',
        required=True,
        help='Select report layout to render and dispatch.'
    )
    report_domain = fields.Char(string='Report Domain', compute='_compute_report_domain')

    branch_id = fields.Many2one(
        'res.branch',
        string='Branch',
        help='Branch context for routing and printer discovery.'
    )

    printer_id = fields.Many2one(
        'print.hub.printer',
        string='Target Printer',
        domain="['|', ('branch_id', '=', branch_id), ('branch_id', '=', False)]",
        help='Select physical printer discovered on your branch workstation.'
    )
    printer_status = fields.Selection(related='printer_id.status', readonly=True)

    queue_id = fields.Many2one(
        'print.hub.queue',
        string='Print Queue / Profile',
        help='Virtual profile configured in Print Hub (driver & paper settings).'
    )

    copies = fields.Integer(string='Copies', default=1, required=True)
    custom_watermark = fields.Char(string='Custom Watermark', placeholder='e.g. COPY / DRAFT')

    # Print Preview (Phase 3.2)
    preview_pdf = fields.Binary(string='PDF Preview File', readonly=True)
    preview_filename = fields.Char(string='Preview Filename', default='preview.pdf')
    has_preview = fields.Boolean(string='Has Preview', default=False)

    @api.model
    def _register_hook(self):
        super()._register_hook()
        for target_model in ['job.card', 'account.move', 'stock.picking', 'sale.order']:
            model_rec = self.env['ir.model'].sudo().search([('model', '=', target_model)], limit=1)
            if model_rec:
                existing = self.env['ir.actions.act_window'].sudo().search([
                    ('res_model', '=', self._name),
                    ('binding_model_id', '=', model_rec.id)
                ], limit=1)
                if not existing:
                    try:
                        self.env['ir.actions.act_window'].sudo().create({
                            'name': _('🖨️ Cetak ke Print Hub'),
                            'res_model': self._name,
                            'view_mode': 'form',
                            'target': 'new',
                            'binding_model_id': model_rec.id,
                            'binding_view_types': 'list,form',
                        })
                    except Exception as e:
                        _logger.debug("Could not bind print wizard to %s: %s", target_model, e)

    @api.model
    def default_get(self, fields_list):
        res = super().default_get(fields_list)
        active_model = self._context.get('active_model')
        active_ids = self._context.get('active_ids', [])

        if not active_model or not active_ids:
            return res

        res['model_name'] = active_model
        res['res_ids_str'] = json.dumps(active_ids)

        # Resolve Branch
        user = self.env.user
        branch = False
        if hasattr(user, 'branch_id') and user.branch_id:
            branch = user.branch_id
        elif self._context.get('allowed_branch_ids'):
            branch = self.env['res.branch'].sudo().browse(self._context['allowed_branch_ids'][0])

        if branch:
            res['branch_id'] = branch.id
            if branch.default_printer_id:
                res['printer_id'] = branch.default_printer_id.id
            if branch.default_queue_id:
                res['queue_id'] = branch.default_queue_id.id

        # Find first matching report for this model
        reports = self.env['ir.actions.report'].search([
            ('model', '=', active_model),
            ('report_type', 'in', ['qweb-pdf', 'qweb-text'])
        ], limit=5)
        if reports:
            res['report_id'] = reports[0].id

        # Document title
        if len(active_ids) == 1:
            rec = self.env[active_model].browse(active_ids[0])
            if rec.exists():
                res['record_name'] = rec.display_name
        else:
            res['record_name'] = _("%d records selected for printing") % len(active_ids)

        return res

    @api.depends('res_ids_str')
    def _compute_record_count(self):
        for w in self:
            try:
                ids = json.loads(w.res_ids_str or '[]')
                w.record_count = len(ids)
            except Exception:
                w.record_count = 0

    @api.depends('model_name')
    def _compute_report_domain(self):
        for w in self:
            w.report_domain = json.dumps([('model', '=', w.model_name), ('report_type', 'in', ['qweb-pdf', 'qweb-text'])])

    def action_preview(self):
        """Render QWeb PDF preview into embedded binary field."""
        self.ensure_one()
        if not self.report_id:
            raise UserError(_("Please select a report first."))

        ids = json.loads(self.res_ids_str or '[]')
        if not ids:
            raise UserError(_("No records selected."))

        try:
            # Render first record for preview
            preview_id = [ids[0]]
            pdf_content, _ = self.env['ir.actions.report']._render_qweb_pdf(self.report_id.report_name, preview_id)
            self.write({
                'preview_pdf': base64.b64encode(pdf_content),
                'preview_filename': f"preview_{ids[0]}.pdf",
                'has_preview': True,
            })
            return {
                'type': 'ir.actions.act_window',
                'res_model': self._name,
                'res_id': self.id,
                'view_mode': 'form',
                'target': 'new',
            }
        except Exception as e:
            raise UserError(_("Failed to generate PDF preview: %s") % e)

    def action_print(self):
        """Execute print to Print Hub for all selected records."""
        self.ensure_one()
        if not self.report_id:
            raise UserError(_("Please select a report to print."))

        ids = json.loads(self.res_ids_str or '[]')
        if not ids:
            raise UserError(_("No records selected."))

        options = {
            'copies': self.copies,
            'branch_code': self.branch_id.print_hub_branch_code if self.branch_id else None,
            'printer': self.printer_id.name if self.printer_id else None,
            'queue': self.queue_id.name if self.queue_id else None,
        }
        if self.custom_watermark:
            options['watermark'] = {
                'text': self.custom_watermark,
                'opacity': 0.15,
                'rotation': 45
            }

        records = self.env[self.model_name].browse(ids)

        # Batch print if multiple records and batch method supported
        if len(records) > 1 and hasattr(records, 'print_batch_via_hub'):
            res = records.print_batch_via_hub(self.report_id.report_name, options=options)
            if res.get('success'):
                return {
                    'type': 'ir.actions.client',
                    'tag': 'display_notification',
                    'params': {
                        'title': _('Batch Print Dispatched'),
                        'message': _('Successfully spooled %d documents to Print Hub.') % len(records),
                        'type': 'success',
                        'sticky': False,
                    }
                }
            else:
                raise UserError(res.get('error', _('Batch print failed')))

        # Single or loop print
        dispatched = 0
        errors = []
        for rec in records:
            if hasattr(rec, 'print_via_hub'):
                res = rec.print_via_hub(self.report_id.report_name, options=options)
                if res.get('success'):
                    dispatched += 1
                else:
                    errors.append(f"{rec.display_name}: {res.get('error')}")
            else:
                # Direct render and send fallback
                pdf_content, _ = self.env['ir.actions.report']._render_qweb_pdf(self.report_id.report_name, [rec.id])
                ref_id = f"{self.model_name},{rec.id}"
                ICP = self.env['ir.config_parameter'].sudo()
                hub_url = ICP.get_param('print_hub.url')
                api_key = ICP.get_param('print_hub.api_key')

                job_log = self.env['print.hub.job'].sudo().create({
                    'name': f"wiz_{self.model_name}_{rec.id}_{int(fields.Datetime.now().timestamp())}",
                    'report_name': self.report_id.report_name,
                    'model_name': self.model_name,
                    'res_id': rec.id,
                    'user_id': self.env.user.id,
                    'branch_id': self.branch_id.id if self.branch_id else False,
                    'branch_code': options.get('branch_code'),
                    'printer_name': options.get('printer'),
                    'queue': options.get('queue'),
                    'copies': self.copies,
                    'status': 'draft',
                })

                payload = {
                    'document_base64': base64.b64encode(pdf_content).decode('utf-8'),
                    'reference_id': ref_id,
                    'options': options,
                }
                if options.get('branch_code'):
                    payload['branch_code'] = options['branch_code']
                if options.get('printer'):
                    payload['printer'] = options['printer']
                if options.get('queue'):
                    payload['queue'] = options['queue']

                try:
                    import requests
                    resp = requests.post(
                        f"{hub_url.rstrip('/')}/api/v1/print",
                        headers={'X-API-Key': api_key, 'Content-Type': 'application/json'},
                        json=payload,
                        timeout=20
                    )
                    if resp.status_code in (200, 201, 202):
                        jid = resp.json().get('data', {}).get('job_id', job_log.name)
                        job_log.write({'name': jid, 'status': 'draft'})
                        dispatched += 1
                    else:
                        err = resp.json().get('error', {}).get('message', resp.text)
                        job_log.write({'status': 'failed', 'error_message': err})
                        errors.append(f"{rec.display_name}: {err}")
                except Exception as e:
                    job_log.write({'status': 'failed', 'error_message': str(e)})
                    errors.append(f"{rec.display_name}: {e}")

        if errors:
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': _('Print Spool Warning'),
                    'message': _("Dispatched %d/%d documents. Errors: %s") % (dispatched, len(records), "; ".join(errors[:2])),
                    'type': 'warning',
                    'sticky': True,
                }
            }

        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': _('Direct Print Spooled'),
                'message': _('Successfully spooled %d document(s) to %s.') % (dispatched, self.printer_id.name or self.queue_id.name or 'Default Printer'),
                'type': 'success',
                'sticky': False,
            }
        }
