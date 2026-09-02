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

    branch_code = fields.Char(
        string='Branch Code',
        help='Optional: Match this specific branch (e.g. SDP-MAIN, SDP-BEKASI). Leave blank to match all branches.'
    )

    queue = fields.Char(
        string='Print Hub Queue / Profile',
        help='Print Hub profile or queue name (e.g. surat_jalan_queue, laser_a4_queue).'
    )

    pool_name = fields.Char(
        string='Printer Pool (Failover / Load Balanced)',
        placeholder='e.g. Warehouse Dot-Matrix Pool',
        help='If specified, Print Hub automatically load balances across active printers in this pool.'
    )

    printer_name = fields.Char(
        string='Default Physical Printer',
        help='Name of the physical printer on the branch agent (e.g. EPSON-LQ2190, Zebra-ZD220).'
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
