# -*- coding: utf-8 -*-
import logging
from odoo import models, fields, api

_logger = logging.getLogger(__name__)


class PrintHubPool(models.Model):
    _name = 'print.hub.pool'
    _description = 'Print Hub Printer Pool'
    _order = 'name'

    name = fields.Char(string='Pool Name', required=True, index=True)
    strategy = fields.Char(string='Load Balancing Strategy', default='round_robin')
    printers_summary = fields.Char(string='Printers in Pool')
    last_synced_at = fields.Datetime(string='Last Synced', default=fields.Datetime.now)

    display_name = fields.Char(string='Display Name', compute='_compute_display_name', store=True)

    @api.depends('name', 'strategy', 'printers_summary')
    def _compute_display_name(self):
        for rec in self:
            strategy_str = f" [{rec.strategy}]" if rec.strategy else ""
            printers_str = f" - {rec.printers_summary}" if rec.printers_summary else ""
            rec.display_name = f"{rec.name}{strategy_str}{printers_str}"
