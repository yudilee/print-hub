# -*- coding: utf-8 -*-
from odoo import models, fields, api, _


class PrintHubHelp(models.TransientModel):
    _name = 'print.hub.help'
    _description = 'Print Hub In-App Help & Documentation'

    name = fields.Char(string='Title', default=lambda self: _('Print Hub User Guide & Developer Reference'), readonly=True)

    @api.model
    def action_open_help(self):
        """Open the in-app documentation view."""
        record = self.create({})
        return {
            'name': _('Help & Documentation'),
            'type': 'ir.actions.act_window',
            'res_model': 'print.hub.help',
            'res_id': record.id,
            'view_mode': 'form',
            'target': 'current',
        }
