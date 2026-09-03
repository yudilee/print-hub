# -*- coding: utf-8 -*-
from odoo import models, fields, api, _
from odoo.exceptions import UserError
import logging
import requests

_logger = logging.getLogger(__name__)


class PrintHubJob(models.Model):
    _name = 'print.hub.job'
    _inherit = ['mail.thread', 'mail.activity.mixin']
    _description = 'Print Hub Job Log'
    _order = 'create_date desc'
    _rec_name = 'name'

    name = fields.Char(string='Job ID', required=True, index=True, default=lambda self: _('New'), tracking=True)
    report_name = fields.Char(string='Report Technical Name', index=True)
    report_display_name = fields.Char(string='Report / Document', compute='_compute_report_display_name', store=True)
    model_name = fields.Char(string='Source Model', index=True)
    res_id = fields.Integer(string='Record ID', index=True)
    record_reference = fields.Char(string='Source Document', compute='_compute_record_reference')

    user_id = fields.Many2one('res.users', string='Printed By', default=lambda self: self.env.user, index=True, tracking=True)
    branch_id = fields.Many2one('res.branch', string='Branch', ondelete='set null', index=True, tracking=True)
    branch_code = fields.Char(string='Branch Code', index=True)
    printer_name = fields.Char(string='Target Printer', tracking=True)
    queue = fields.Char(string='Queue / Profile', tracking=True)
    copies = fields.Integer(string='Copies', default=1)

    status = fields.Selection([
        ('draft', 'Queued'),
        ('pending_approval', 'Pending Approval'),
        ('spooling', 'Printing'),
        ('completed', 'Completed'),
        ('failed', 'Failed'),
        ('cancelled', 'Cancelled')
    ], string='Status', default='draft', index=True, required=True, tracking=True)

    error_message = fields.Text(string='Error Details', tracking=True)
    agent_name = fields.Char(string='Spool Agent')
    sent_at = fields.Datetime(string='Dispatched At', default=fields.Datetime.now)
    completed_at = fields.Datetime(string='Completed At')

    # Audit & Approval Enhancements
    retry_count = fields.Integer(string='Retry Count', default=0, tracking=True)
    cancel_reason = fields.Text(string='Cancel / Rejection Reason', tracking=True)
    approver_id = fields.Many2one('res.users', string='Approved By', tracking=True)
    approved_at = fields.Datetime(string='Approved At', tracking=True)

    @api.depends('report_name')
    def _compute_report_display_name(self):
        for rec in self:
            if rec.report_name:
                report = self.env['ir.actions.report'].search([('report_name', '=', rec.report_name)], limit=1)
                rec.report_display_name = report.name if report else rec.report_name
            else:
                rec.report_display_name = False

    @api.depends('model_name', 'res_id')
    def _compute_record_reference(self):
        for rec in self:
            if rec.model_name and rec.res_id and rec.model_name in self.env:
                try:
                    record = self.env[rec.model_name].sudo().browse(rec.res_id)
                    rec.record_reference = record.display_name if record.exists() else f"{rec.model_name},{rec.res_id}"
                except Exception:
                    rec.record_reference = f"{rec.model_name},{rec.res_id}"
            else:
                rec.record_reference = f"{rec.model_name},{rec.res_id}" if rec.model_name else False

    def action_retry(self):
        """
        Re-render document and resubmit the print job to Print Hub.
        """
        self.ensure_one()
        if not self.model_name or not self.res_id:
            raise UserError(_("Cannot retry: No source document linked."))

        if self.model_name not in self.env:
            raise UserError(_("Model %s no longer exists in Odoo.") % self.model_name)

        record = self.env[self.model_name].browse(self.res_id)
        if not record.exists():
            raise UserError(_("The source record (%s, ID: %s) was deleted.") % (self.model_name, self.res_id))

        if not hasattr(record, 'print_via_hub'):
            raise UserError(_("Model %s does not inherit 'print.hub.mixin'.") % self.model_name)

        # Trigger print with original options
        options = {
            'branch_code': self.branch_code,
            'queue': self.queue,
            'printer': self.printer_name,
            'copies': self.copies,
        }

        result = record.print_via_hub(self.report_name, options=options)

        if result.get('success'):
            new_job_id = result.get('job_id')
            self.write({
                'name': new_job_id,
                'status': 'draft',
                'error_message': False,
                'sent_at': fields.Datetime.now(),
                'completed_at': False,
                'retry_count': self.retry_count + 1,
            })
            self.message_post(
                body=_("Print job retried by <b>%s</b>. New Print Hub Job ID: <code>%s</code>") % (self.env.user.name, new_job_id)
            )
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': _('Print Job Retried'),
                    'message': _('Job %s has been resubmitted to Print Hub.') % new_job_id,
                    'type': 'success',
                    'sticky': False,
                }
            }
        else:
            err = result.get('error', _('Retry failed'))
            self.write({
                'status': 'failed',
                'error_message': err,
                'retry_count': self.retry_count + 1,
            })
            self.message_post(
                body=_("Print job retry failed: <span class='text-danger'>%s</span>") % err
            )
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': _('Print Hub Error'),
                    'message': err,
                    'type': 'danger',
                    'sticky': True,
                }
            }

    def action_cancel(self, reason=None):
        """Cancel print job with optional cancellation reason."""
        self.ensure_one()
        if self.status in ('completed', 'cancelled'):
            raise UserError(_("Job is already %s and cannot be cancelled.") % self.status)

        vals = {'status': 'cancelled'}
        if reason:
            vals['cancel_reason'] = reason

        self.write(vals)
        self.message_post(
            body=_("Print job cancelled by <b>%s</b>. %s") % (self.env.user.name, f"Reason: {reason}" if reason else "")
        )
        return True

    def action_approve(self):
        """Approve a pending print job and dispatch it to Print Hub."""
        self.ensure_one()
        if self.status != 'pending_approval':
            raise UserError(_("Only jobs pending approval can be approved."))

        if not self.env.user.has_group('print_hub_connector.group_print_hub_manager'):
            raise UserError(_("Only Print Hub Managers can approve print jobs."))

        if not self.model_name or not self.res_id or self.model_name not in self.env:
            raise UserError(_("Linked document is missing or invalid."))

        record = self.env[self.model_name].browse(self.res_id)
        if not record.exists():
            raise UserError(_("The source record (%s, ID: %s) was deleted.") % (self.model_name, self.res_id))

        # Mark as approved
        self.write({
            'approver_id': self.env.user.id,
            'approved_at': fields.Datetime.now(),
        })

        # Dispatch
        options = {
            'branch_code': self.branch_code,
            'queue': self.queue,
            'printer': self.printer_name,
            'copies': self.copies,
            'skip_approval_check': True,  # Prevent infinite approval loop
        }

        result = record.print_via_hub(self.report_name, options=options)
        if result.get('success'):
            new_job_id = result.get('job_id')
            self.write({
                'name': new_job_id,
                'status': 'draft',
                'sent_at': fields.Datetime.now(),
                'error_message': False,
            })
            self.message_post(
                body=_("Job approved by <b>%s</b> and dispatched to Print Hub. Job ID: <code>%s</code>") % (self.env.user.name, new_job_id)
            )
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': _('Print Job Approved'),
                    'message': _('Job approved and spooled with ID: %s') % new_job_id,
                    'type': 'success',
                }
            }
        else:
            err = result.get('error', _('Dispatch failed after approval'))
            self.write({'status': 'failed', 'error_message': err})
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': _('Print Hub Error'),
                    'message': err,
                    'type': 'danger',
                    'sticky': True,
                }
            }

    def action_reject(self):
        """Reject print job."""
        self.ensure_one()
        if self.status != 'pending_approval':
            raise UserError(_("Only jobs pending approval can be rejected."))

        if not self.env.user.has_group('print_hub_connector.group_print_hub_manager'):
            raise UserError(_("Only Print Hub Managers can reject print jobs."))

        self.write({
            'status': 'cancelled',
            'approver_id': self.env.user.id,
            'approved_at': fields.Datetime.now(),
            'cancel_reason': _('Rejected by %s') % self.env.user.name,
        })
        self.message_post(
            body=_("Print request rejected by <b>%s</b>.") % self.env.user.name
        )
        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': _('Print Job Rejected'),
                'message': _('The print request was rejected.'),
                'type': 'warning',
            }
        }

    def action_open_source_record(self):
        """
        Navigate directly to the source record in Odoo.
        """
        self.ensure_one()
        if not self.model_name or not self.res_id:
            raise UserError(_("No source record linked."))

        return {
            'type': 'ir.actions.act_window',
            'res_model': self.model_name,
            'res_id': self.res_id,
            'view_mode': 'form',
            'target': 'current',
        }

    def action_sync_status(self):
        """
        Fetch latest status from Print Hub and update the record.
        """
        updated = self._sync_job_status_batch()
        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': _('Status Synced'),
                'message': _('Updated status for %d print jobs from Print Hub.') % updated,
                'type': 'info',
                'sticky': False,
            }
        }

    def _sync_job_status_batch(self):
        hub_url = self.env['ir.config_parameter'].sudo().get_param('print_hub.url')
        api_key = self.env['ir.config_parameter'].sudo().get_param('print_hub.api_key')
        if not hub_url or not api_key:
            return 0

        updated_count = 0
        for job in self:
            if not job.name or job.name.startswith('temp_') or job.name.startswith('batch_temp_'):
                continue
            try:
                endpoint = f"{hub_url.rstrip('/')}/api/v1/jobs/{job.name}"
                resp = requests.get(
                    endpoint,
                    headers={'X-API-Key': api_key, 'Accept': 'application/json'},
                    timeout=6
                )
                if resp.status_code == 200:
                    data = resp.json().get('data', {})
                    remote_status = data.get('status')
                    vals = {}
                    if remote_status == 'success':
                        vals['status'] = 'completed'
                        vals['error_message'] = False
                        if data.get('completed_at'):
                            vals['completed_at'] = fields.Datetime.to_datetime(data['completed_at'][:19].replace('T', ' '))
                        else:
                            vals['completed_at'] = fields.Datetime.now()
                    elif remote_status == 'failed':
                        vals['status'] = 'failed'
                        vals['error_message'] = data.get('error') or _('Print failed at workstation agent.')
                        if data.get('completed_at'):
                            vals['completed_at'] = fields.Datetime.to_datetime(data['completed_at'][:19].replace('T', ' '))
                        else:
                            vals['completed_at'] = fields.Datetime.now()
                    elif remote_status in ('spooling', 'printing'):
                        vals['status'] = 'spooling'
                    if data.get('printer'):
                        vals['printer_name'] = data['printer']

                    if vals:
                        job.write(vals)
                        updated_count += 1
            except Exception as e:
                _logger.warning("Error syncing job status for %s from Print Hub: %s", job.name, e)

        return updated_count

    @api.model
    def cron_sync_pending_jobs(self):
        """
        Scheduled action to sync any active/pending jobs with Print Hub.
        """
        pending_jobs = self.search([('status', 'in', ['draft', 'spooling'])], limit=50)
        if pending_jobs:
            pending_jobs._sync_job_status_batch()
