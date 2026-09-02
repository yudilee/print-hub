# -*- coding: utf-8 -*-
import logging
import requests
from odoo import models, fields, api, _
from odoo.exceptions import UserError

_logger = logging.getLogger(__name__)


class PrintHubPrinter(models.Model):
    _name = 'print.hub.printer'
    _description = 'Print Hub Physical Printer & Health'
    _order = 'branch_code, name'

    name = fields.Char(string='Printer Name', required=True, index=True)
    agent_id = fields.Integer(string='Agent ID')
    agent_name = fields.Char(string='Workstation Agent', index=True)
    agent_online = fields.Boolean(string='Agent Online', default=False)

    branch_code = fields.Char(string='Branch Code', index=True)
    branch_name = fields.Char(string='Branch Name')

    status = fields.Selection([
        ('online', 'Online / Ready'),
        ('offline', 'Offline'),
        ('paused', 'Paused'),
        ('error', 'Error')
    ], string='Status', default='online', required=True)

    paper_status = fields.Selection([
        ('ok', 'Paper OK'),
        ('low', 'Paper Low'),
        ('out', 'Paper Out'),
        ('jammed', 'Paper Jammed')
    ], string='Paper & Spool State', default='ok', required=True)

    is_default = fields.Boolean(string='Is Workstation Default', default=False)
    capabilities = fields.Text(string='Capabilities (JSON)')
    last_synced_at = fields.Datetime(string='Last Synced', default=fields.Datetime.now)

    @api.model
    def sync_printers_from_hub(self):
        """
        Query Print Hub API (/api/v1/printers) and synchronize printer statuses.
        """
        ICP = self.env['ir.config_parameter'].sudo()
        hub_url = ICP.get_param('print_hub.url', '')
        api_key = ICP.get_param('print_hub.api_key', '')

        if not hub_url or not api_key:
            _logger.warning("Print Hub URL or API Key is missing in Settings.")
            return False

        try:
            endpoint = f"{hub_url.rstrip('/')}/api/v1/printers"
            resp = requests.get(
                endpoint,
                headers={
                    'X-API-Key': api_key,
                    'Accept': 'application/json',
                },
                timeout=15,
            )

            if resp.status_code != 200:
                _logger.error("Failed to fetch printers from Print Hub: %s", resp.text)
                return False

            res_json = resp.json()
            printers_data = res_json.get('data', {}).get('printers', [])

            for p in printers_data:
                printer_name = p.get('name')
                agent_name = p.get('agent_name')
                if not printer_name or not agent_name:
                    continue

                branch_info = p.get('branch') or {}
                branch_code = branch_info.get('code')
                branch_name = branch_info.get('name')

                # Find or create
                existing = self.search([
                    ('name', '=', printer_name),
                    ('agent_name', '=', agent_name)
                ], limit=1)

                vals = {
                    'agent_id': p.get('agent_id'),
                    'agent_online': bool(p.get('agent_online')),
                    'branch_code': branch_code,
                    'branch_name': branch_name,
                    'status': p.get('status', 'online') if p.get('agent_online') else 'offline',
                    'paper_status': p.get('paper_status', 'ok'),
                    'is_default': bool(p.get('is_default')),
                    'last_synced_at': fields.Datetime.now(),
                }

                if existing:
                    existing.write(vals)
                else:
                    vals.update({
                        'name': printer_name,
                        'agent_name': agent_name,
                    })
                    self.create(vals)

            _logger.info("Successfully synced %d printers from Print Hub.", len(printers_data))
            return True
        except Exception as e:
            _logger.exception("Error syncing printers from Print Hub: %s", e)
            return False

    def action_sync_printers(self):
        """Action button to trigger manual printer sync."""
        success = self.sync_printers_from_hub()
        if success:
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': _('Printers Synced'),
                    'message': _('Live printer health updated from Print Hub.'),
                    'type': 'success',
                    'sticky': False,
                }
            }
        else:
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': _('Sync Failed'),
                    'message': _('Could not connect to Print Hub API. Check Settings.'),
                    'type': 'danger',
                    'sticky': True,
                }
            }
