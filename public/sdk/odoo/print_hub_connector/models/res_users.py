# -*- coding: utf-8 -*-
from odoo import fields, models

class ResUsers(models.Model):
    _inherit = 'res.users'

    print_hub_enabled = fields.Boolean(
        string='Enable Direct Print via Hub',
        default=True,
        help='When enabled, print actions for this user are spooled directly to Print Hub without opening browser PDF dialogs.'
    )
    print_hub_branch_code = fields.Char(
        string='Assigned Branch Code',
        help='User workstation branch code (e.g. SDP-MAIN, SDP-BEKASI). Leave empty to use company default.'
    )
    print_hub_queue = fields.Char(
        string='Default Queue/Profile',
        help='Override default virtual queue for this user (e.g. dot_matrix_queue, laser_queue).'
    )
    print_hub_printer = fields.Char(
        string='Preferred Physical Printer',
        help='Specific printer name attached to the branch agent workstation (e.g. EPSON-LQ2190). Leave empty for auto pool selection.'
    )
    print_hub_copies = fields.Integer(
        string='Default Print Copies',
        default=1,
        help='Number of copies to print by default.'
    )
