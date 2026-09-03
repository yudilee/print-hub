# -*- coding: utf-8 -*-
import logging
from odoo import models, fields, api

_logger = logging.getLogger(__name__)


class PrintHubQueue(models.Model):
    _name = 'print.hub.queue'
    _description = 'Print Hub Queue / Profile'
    _order = 'name'

    name = fields.Char(string='Queue Name', required=True, index=True)
    description = fields.Char(string='Description')
    printer = fields.Char(string='Configured Printer')
    agent_name = fields.Char(string='Agent Workstation')
    is_online = fields.Boolean(string='Agent Online', default=False)
    branch_id = fields.Integer(string='Branch ID')
    paper_size = fields.Char(string='Paper Size')
    orientation = fields.Char(string='Orientation')
    copies = fields.Integer(string='Default Copies', default=1)
    duplex = fields.Boolean(string='Duplex', default=False)
    last_synced_at = fields.Datetime(string='Last Synced', default=fields.Datetime.now)

    display_name = fields.Char(string='Display Name', compute='_compute_display_name', store=True)

    @api.depends('name', 'paper_size', 'printer', 'agent_name')
    def _compute_display_name(self):
        for rec in self:
            details = []
            if rec.paper_size:
                details.append(f"Paper: {rec.paper_size}")
            if rec.printer:
                details.append(f"Printer: {rec.printer}")
            if rec.agent_name:
                details.append(f"Agent: {rec.agent_name}")
            info = f" ({' | '.join(details)})" if details else ""
            rec.display_name = f"{rec.name}{info}"
