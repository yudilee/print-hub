# -*- coding: utf-8 -*-
from odoo import models, fields, api


class ResBranch(models.Model):
    _inherit = 'res.branch'

    print_hub_branch_code = fields.Char(
        string='Print Hub Branch Code',
        help='Corresponding branch code in Print Hub (e.g. HRMSBY, HRMBLI, HRMSMG, HRMJKT, HRMMLL)',
        index=True
    )
    default_printer_id = fields.Many2one(
        'print.hub.printer',
        string='Default Printer',
        domain="['|', ('branch_id', '=', id), ('branch_id', '=', False)]",
        help='Default physical printer used when printing from this branch'
    )
    default_queue_id = fields.Many2one(
        'print.hub.queue',
        string='Default Print Queue',
        help='Default queue profile configured in Print Hub for this branch'
    )
    printer_ids = fields.One2many(
        'print.hub.printer',
        'branch_id',
        string='Branch Printers'
    )
    job_ids = fields.One2many(
        'print.hub.job',
        'branch_id',
        string='Branch Print Jobs'
    )
