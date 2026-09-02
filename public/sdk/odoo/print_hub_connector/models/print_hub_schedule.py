# -*- coding: utf-8 -*-
import logging
from odoo import models, fields, api, _
from odoo.exceptions import UserError
from odoo.tools.safe_eval import safe_eval

_logger = logging.getLogger(__name__)


class PrintHubSchedule(models.Model):
    _name = 'print.hub.schedule'
    _description = 'Print Hub Scheduled Print Automation'
    _order = 'name'

    name = fields.Char(string='Schedule Title', required=True)
    active = fields.Boolean(string='Active', default=True)

    model_id = fields.Many2one('ir.model', string='Target Model', required=True, ondelete='cascade')
    model_name = fields.Char(string='Model Technical Name', related='model_id.model', readonly=True, store=True)

    domain = fields.Text(string='Filter Domain', default='[]', required=True,
                         help='Odoo search domain to match records to print (e.g. [("state", "=", "assigned")])')

    print_method = fields.Selection([
        ('qweb_pdf', 'QWeb PDF Report'),
        ('template_json', 'Print Hub JSON Template')
    ], string='Print Method', default='qweb_pdf', required=True)

    report_id = fields.Many2one('ir.actions.report', string='Odoo Report')
    report_technical_name = fields.Char(related='report_id.report_name', readonly=True)

    template_slug = fields.Char(string='Print Hub Template Name',
                                placeholder='e.g. odoo_surat_jalan_continuous')

    branch_code = fields.Char(string='Branch Code')
    queue = fields.Char(string='Target Queue / Profile')
    printer_name = fields.Char(string='Target Physical Printer')
    copies = fields.Integer(string='Copies', default=1)

    max_records = fields.Integer(string='Max Records per Run', default=50,
                                 help='Maximum documents to spool per execution run.')

    last_run_at = fields.Datetime(string='Last Executed At', readonly=True)
    last_run_summary = fields.Char(string='Last Run Result', readonly=True)

    def action_run_now(self):
        """Manually trigger the scheduled print job."""
        self.ensure_one()
        return self._execute_schedule()

    def _execute_schedule(self):
        self.ensure_one()
        if not self.model_name or self.model_name not in self.env:
            raise UserError(_("Target model %s is invalid.") % self.model_name)

        Model = self.env[self.model_name]
        try:
            domain = safe_eval(self.domain or '[]')
        except Exception as e:
            raise UserError(_("Invalid search domain: %s") % e)

        records = Model.search(domain, limit=self.max_records or 50)
        if not records:
            msg = _("No records matched domain.")
            self.write({'last_run_at': fields.Datetime.now(), 'last_run_summary': msg})
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {'title': _('Scheduled Print'), 'message': msg, 'type': 'warning'}
            }

        options = {
            'branch_code': self.branch_code,
            'queue': self.queue,
            'printer': self.printer_name,
            'copies': self.copies,
        }

        success_count = 0
        failed_count = 0

        if hasattr(records, 'print_batch_via_hub'):
            # Use high-speed batch print
            res = records.print_batch_via_hub(
                report_name=self.report_technical_name,
                template_slug=self.template_slug if self.print_method == 'template_json' else None,
                options=options
            )
            success_count = res.get('created_jobs_count', len(records)) if res.get('success') else 0
            failed_count = 0 if res.get('success') else len(records)
        else:
            for rec in records:
                if hasattr(rec, 'print_via_hub'):
                    res = rec.print_via_hub(self.report_technical_name, options=options)
                    if res.get('success'):
                        success_count += 1
                    else:
                        failed_count += 1

        summary = _("Processed %d records (%d succeeded, %d failed).") % (len(records), success_count, failed_count)
        self.write({
            'last_run_at': fields.Datetime.now(),
            'last_run_summary': summary
        })

        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': _('Scheduled Print Completed'),
                'message': summary,
                'type': 'success' if failed_count == 0 else 'warning'
            }
        }

    @api.model
    def cron_process_all_schedules(self):
        """Cron execution handler for active schedules."""
        schedules = self.search([('active', '=', True)])
        for sched in schedules:
            try:
                sched._execute_schedule()
            except Exception as e:
                _logger.exception("Error executing schedule %s: %s", sched.name, e)
