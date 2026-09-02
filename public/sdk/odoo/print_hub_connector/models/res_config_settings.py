# -*- coding: utf-8 -*-
from odoo import fields, models

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
        help='Branch code for routing if user has no branch override, e.g. SDP-MAIN'
    )
    print_hub_default_queue = fields.Char(
        string='Default Queue/Profile',
        config_parameter='print_hub.default_queue',
        help='Virtual profile queue name, e.g. default_queue or surat_jalan_queue'
    )
