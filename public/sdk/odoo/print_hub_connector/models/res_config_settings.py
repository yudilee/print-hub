# -*- coding: utf-8 -*-
import requests
from odoo import fields, models, _
from odoo.exceptions import UserError


class ResConfigSettings(models.TransientModel):
    _inherit = 'res.config.settings'

    print_hub_url = fields.Char(
        string='Print Hub URL',
        config_parameter='print_hub.url',
        help='Base URL of your Print Hub instance, e.g. https://print-hub.hartonomotor-group.com'
    )
    print_hub_api_key = fields.Char(
        string='Print Hub API Key',
        config_parameter='print_hub.api_key',
        help='X-API-Key issued by Print Hub for this Odoo client application'
    )
    print_hub_default_branch = fields.Char(
        string='Default Branch Code',
        config_parameter='print_hub.default_branch',
        help='Branch code for routing if user has no branch override, e.g. HRMSBY'
    )
    print_hub_default_queue = fields.Char(
        string='Default Queue/Profile',
        config_parameter='print_hub.default_queue',
        help='Virtual profile queue name, e.g. default_queue or job_card_laser'
    )
    print_hub_approval_enabled = fields.Boolean(
        string='Enable Approval Workflow',
        config_parameter='print_hub.approval_enabled',
        help='When enabled, print routing rules marked as "Require Approval" will hold documents in Pending Approval state until authorized by a Print Hub Manager.'
    )
    print_hub_webhook_secret = fields.Char(
        string='Webhook HMAC Secret',
        config_parameter='print_hub.webhook_secret',
        help='Optional shared secret to verify HMAC-SHA256 signatures on incoming Print Hub status webhooks.'
    )

    def action_test_connection(self):
        """
        Verify connection and authentication with the Print Hub server.
        """
        self.ensure_one()
        url = self.print_hub_url or self.env['ir.config_parameter'].sudo().get_param('print_hub.url')
        key = self.print_hub_api_key or self.env['ir.config_parameter'].sudo().get_param('print_hub.api_key')

        if not url or not key:
            raise UserError(_("Please provide both Print Hub URL and API Key before testing."))

        try:
            endpoint = f"{url.rstrip('/')}/api/v1/printers"
            resp = requests.get(
                endpoint,
                headers={'X-API-Key': key, 'Accept': 'application/json'},
                timeout=8
            )

            if resp.status_code == 200:
                data = resp.json().get('data', {})
                printers = data.get('printers', [])
                online_count = sum(1 for p in printers if p.get('status') == 'online')
                msg = _("Successfully connected to Print Hub! Found %d printers (%d online).") % (len(printers), online_count)
                return {
                    'type': 'ir.actions.client',
                    'tag': 'display_notification',
                    'params': {
                        'title': _('Connection Successful'),
                        'message': msg,
                        'type': 'success',
                        'sticky': False,
                    }
                }
            else:
                err_msg = resp.json().get('error', {}).get('message', resp.text)
                return {
                    'type': 'ir.actions.client',
                    'tag': 'display_notification',
                    'params': {
                        'title': _('Connection Refused (HTTP %s)') % resp.status_code,
                        'message': err_msg,
                        'type': 'danger',
                        'sticky': True,
                    }
                }
        except requests.exceptions.Timeout:
            raise UserError(_("Connection timed out. Please verify Print Hub server URL and firewall/network connectivity."))
        except Exception as e:
            raise UserError(_("Connection test failed: %s") % e)

    def action_sync_printers_now(self):
        """
        Immediately synchronize printers, queues, and printer pools from Print Hub.
        """
        res = self.env['print.hub.printer'].sync_printers_from_hub()
        if isinstance(res, dict) and not res.get('success'):
            raise UserError(res.get('error', _('Sync failed')))

        count = self.env['print.hub.printer'].search_count([])
        return {
            'type': 'ir.actions.client',
            'tag': 'display_notification',
            'params': {
                'title': _('Sync Completed'),
                'message': _('Synchronized %d physical printers from Print Hub.') % count,
                'type': 'success',
                'sticky': False,
            }
        }
