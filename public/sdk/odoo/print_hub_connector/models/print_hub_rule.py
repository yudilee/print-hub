# -*- coding: utf-8 -*-
from odoo import models, fields, api, _


class PrintHubRule(models.Model):
    _name = 'print.hub.rule'
    _description = 'Print Hub Document Routing Rule'
    _order = 'sequence, id'

    name = fields.Char(string='Rule Name', required=True)
    sequence = fields.Integer(string='Sequence', default=10, help='Lower sequence numbers have higher priority.')
    active = fields.Boolean(string='Active', default=True)

    routing_type = fields.Selection([
        ('qweb_pdf', 'QWeb PDF Report'),
        ('template_json', 'Print Hub JSON Template (Ultra-Fast)')
    ], string='Print Engine', default='qweb_pdf', required=True)

    report_id = fields.Many2one(
        'ir.actions.report',
        string='Odoo Report',
        domain="[('report_type', 'in', ['qweb-pdf', 'qweb-text'])]",
        help='Target report action (e.g. Delivery Slip, Invoice, Production Order).'
    )
    report_technical_name = fields.Char(
        string='Report Technical Name',
        related='report_id.report_name',
        readonly=True,
        store=True,
        index=True
    )

    template_slug = fields.Char(
        string='Print Hub Template Name',
        placeholder='e.g. odoo_surat_jalan_continuous',
        help='Name of the WYSIWYG continuous form template configured in Print Hub.'
    )

    branch_id = fields.Many2one(
        'res.branch',
        string='Branch',
        ondelete='set null',
        index=True,
        help='Optional: Restrict this rule to a specific branch. Leave blank for all branches.'
    )
    branch_code = fields.Char(
        string='Branch Code',
        compute='_compute_branch_code',
        store=True,
        readonly=False,
        help='Branch code sent to Print Hub API.'
    )

    @api.depends('branch_id', 'branch_id.print_hub_branch_code')
    def _compute_branch_code(self):
        for rec in self:
            if rec.branch_id and rec.branch_id.print_hub_branch_code:
                rec.branch_code = rec.branch_id.print_hub_branch_code

    # ── Strict Destination Models from Print Hub ──
    queue_id = fields.Many2one(
        'print.hub.queue',
        string='Print Hub Queue / Profile',
        ondelete='set null',
        help='Strict: Select a pre-configured queue from Print Hub (includes paper format, driver, and tray settings).'
    )
    queue = fields.Char(
        string='Queue Technical Name',
        compute='_compute_queue',
        store=True,
        readonly=False,
        help='Technical queue slug sent to Print Hub API.'
    )

    pool_id = fields.Many2one(
        'print.hub.pool',
        string='Printer Pool (Failover / Load Balanced)',
        ondelete='set null',
        help='Select from configured printer pools in Print Hub.'
    )
    pool_name = fields.Char(
        string='Pool Technical Name',
        compute='_compute_pool_name',
        store=True,
        readonly=False,
        help='Technical pool slug sent to Print Hub API.'
    )

    printer_id = fields.Many2one(
        'print.hub.printer',
        string='Default Physical Printer',
        ondelete='set null',
        help='Select physical printer discovered on branch workstation agents.'
    )
    printer_name = fields.Char(
        string='Printer Technical Name',
        compute='_compute_printer_name',
        store=True,
        readonly=False,
        help='Technical printer name sent to Print Hub API.'
    )

    copies = fields.Integer(string='Default Copies', default=1)

    # ── Dynamic Watermark Rules ──
    watermark_rule = fields.Selection([
        ('none', 'No Watermark'),
        ('state_based', 'Auto State-Based (DRAFT, COPY, CANCELLED)'),
        ('custom', 'Always Custom Text')
    ], string='Watermark Rule', default='state_based', required=True)

    watermark_text = fields.Char(string='Custom Watermark Text', placeholder='e.g. CONFIDENTIAL / SALINAN')
    watermark_opacity = fields.Float(string='Watermark Opacity', default=0.15)

    notes = fields.Text(string='Notes / Description')

    @api.depends('queue_id')
    def _compute_queue(self):
        for r in self:
            if r.queue_id:
                r.queue = r.queue_id.name

    @api.depends('pool_id')
    def _compute_pool_name(self):
        for r in self:
            if r.pool_id:
                r.pool_name = r.pool_id.name

    @api.depends('printer_id')
    def _compute_printer_name(self):
        for r in self:
            if r.printer_id:
                r.printer_name = r.printer_id.name
