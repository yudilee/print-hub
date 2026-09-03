# -*- coding: utf-8 -*-
import logging
import requests
from odoo import models, fields, api, _

_logger = logging.getLogger(__name__)


class PrintHubPrinter(models.Model):
    _name = 'print.hub.printer'
    _description = 'Print Hub Physical Printer & Health'
    _order = 'branch_code, name'

    name = fields.Char(string='Printer Name', required=True, index=True)
    agent_id = fields.Integer(string='Agent ID')
    agent_name = fields.Char(string='Workstation Agent', index=True)
    agent_online = fields.Boolean(string='Agent Online', default=False)

    branch_id = fields.Many2one('res.branch', string='Branch', ondelete='set null', index=True)
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

    display_name = fields.Char(string='Display Name', compute='_compute_display_name', store=True)

    @api.depends('name', 'branch_code', 'branch_id', 'agent_name', 'status')
    def _compute_display_name(self):
        for rec in self:
            status_symbol = "🟢" if rec.status == 'online' else "🔴"
            b_label = f"[{rec.branch_id.name}] " if rec.branch_id else (f"[{rec.branch_code}] " if rec.branch_code else "")
            agent = f" ({rec.agent_name})" if rec.agent_name else ""
            rec.display_name = f"{status_symbol} {b_label}{rec.name}{agent}"

    @api.model
    def sync_printers_from_hub(self):
        hub_url = self.env['ir.config_parameter'].sudo().get_param('print_hub.url')
        api_key = self.env['ir.config_parameter'].sudo().get_param('print_hub.api_key')

        if not hub_url or not api_key:
            return {
                'success': False,
                'error': _('Print Hub URL or API Key is not configured in Settings.')
            }

        headers = {
            'X-API-Key': api_key,
            'Accept': 'application/json',
        }

        try:
            endpoint = f"{hub_url.rstrip('/')}/api/v1/printers"
            resp = requests.get(endpoint, headers=headers, timeout=10)

            if resp.status_code == 200:
                data = resp.json().get('data', {})
                printers = data.get('printers', [])

                for p in printers:
                    printer_name = p.get('name')
                    agent_name = p.get('agent_name')
                    if not printer_name or not agent_name:
                        continue

                    branch_info = p.get('branch') or {}
                    branch_code = branch_info.get('code')
                    branch_name = branch_info.get('name')

                    # Automatic matching with Odoo res.branch
                    branch_rec = False
                    if branch_code:
                        branch_rec = self.env['res.branch'].sudo().search([
                            '|', ('print_hub_branch_code', '=', branch_code),
                            ('name', 'ilike', branch_code)
                        ], limit=1)
                        if not branch_rec:
                            # Match prefix (S -> HRMSBY, B -> HRMBLI, G -> HRMSMG, J -> HRMJKT, M -> HRMMLL)
                            prefix_map = {'S': 'HRMSBY', 'B': 'HRMBLI', 'G': 'HRMSMG', 'J': 'HRMJKT', 'M': 'HRMMLL'}
                            for pfx, code in prefix_map.items():
                                if code == branch_code:
                                    branch_rec = self.env['res.branch'].sudo().search([
                                        ('branch_sequence_prefix', '=', pfx)
                                    ], limit=1)
                                    if branch_rec:
                                        # Auto-save the mapped branch code on res.branch
                                        branch_rec.write({'print_hub_branch_code': branch_code})
                                        break

                    existing = self.search([
                        ('name', '=', printer_name),
                        ('agent_name', '=', agent_name)
                    ], limit=1)

                    vals = {
                        'agent_id': p.get('agent_id'),
                        'agent_online': bool(p.get('agent_online')),
                        'branch_id': branch_rec.id if branch_rec else False,
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

            # 2. Sync Queues / Profiles from /api/v1/queues?detailed=true
            try:
                q_endpoint = f"{hub_url.rstrip('/')}/api/v1/queues?detailed=true"
                q_resp = requests.get(q_endpoint, headers=headers, timeout=15)
                if q_resp.status_code == 200:
                    queues_data = q_resp.json().get('data', {}).get('queues', [])
                    QueueModel = self.env['print.hub.queue'].sudo()
                    for q in queues_data:
                        q_name = q.get('name')
                        if not q_name:
                            continue
                        existing_q = QueueModel.search([('name', '=', q_name)], limit=1)
                        q_vals = {
                            'description': q.get('description'),
                            'printer': q.get('printer'),
                            'agent_name': q.get('agent_name'),
                            'is_online': bool(q.get('is_online')),
                            'branch_id': q.get('branch_id'),
                            'paper_size': q.get('paper_size'),
                            'orientation': q.get('orientation'),
                            'copies': q.get('copies', 1),
                            'duplex': bool(q.get('duplex')),
                            'last_synced_at': fields.Datetime.now(),
                        }
                        if existing_q:
                            existing_q.write(q_vals)
                        else:
                            q_vals['name'] = q_name
                            QueueModel.create(q_vals)
            except Exception as e:
                _logger.warning("Failed to sync queues from Print Hub: %s", e)

            # 3. Sync Printer Pools from /api/v1/pools
            try:
                p_endpoint = f"{hub_url.rstrip('/')}/api/v1/pools"
                p_resp = requests.get(p_endpoint, headers=headers, timeout=15)
                if p_resp.status_code == 200:
                    pools_data = p_resp.json().get('data', {}).get('pools', [])
                    PoolModel = self.env['print.hub.pool'].sudo()
                    for pool in pools_data:
                        pool_name = pool.get('name')
                        if not pool_name:
                            continue
                        printers_list = pool.get('printers', [])
                        printer_names = [p.get('printer_name') for p in printers_list if isinstance(p, dict) and p.get('printer_name')]
                        existing_pool = PoolModel.search([('name', '=', pool_name)], limit=1)
                        pool_vals = {
                            'strategy': pool.get('strategy', 'round_robin'),
                            'printers_summary': ', '.join(printer_names),
                            'last_synced_at': fields.Datetime.now(),
                        }
                        if existing_pool:
                            existing_pool.write(pool_vals)
                        else:
                            pool_vals['name'] = pool_name
                            PoolModel.create(pool_vals)
            except Exception as e:
                _logger.warning("Failed to sync pools from Print Hub: %s", e)

            _logger.info("Print Hub synchronization complete.")
            return True
        except Exception as e:
            _logger.exception("Error syncing with Print Hub: %s", e)
            return False

    def action_sync_printers(self):
        """Action button to trigger manual sync from Print Hub."""
        success = self.sync_printers_from_hub()
        if success:
            return {
                'type': 'ir.actions.client',
                'tag': 'display_notification',
                'params': {
                    'title': _('Print Hub Synchronized'),
                    'message': _('Printers, Queues, and Pools updated from Print Hub.'),
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
                    'sticky': False,
                }
            }
