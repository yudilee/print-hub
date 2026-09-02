# Print Hub Connector for Odoo 19 / 18 / 17 / 16 (Enterprise Edition)

> Seamless background printing directly to branch physical printers (dot-matrix, laser, thermal) from Odoo ERP with live Print Job Monitoring, 1-Click Retries, Dynamic Watermarking, Printer Health Diagnostics, Printer Pooling Failover, and Automated Scheduled Printing.

---

## 📦 What this Module Provides

1. **Top-Level "Print Hub" App in Odoo**:
   - **📋 Print Jobs**: Complete audit log of every print job dispatched across Odoo. Live status badges (`Queued`, `Printing`, `Completed`, `Failed`), printer name, branch, user, and execution time.
   - **🔄 1-Click Retry**: Directly resend failed print jobs (paper jam, agent offline, power outage) without navigating back to the source record.
   - **🖨️ Printers & Health**: Real-time dashboard showing branch physical printers, online/offline status, and paper conditions (`Paper OK`, `Paper Low`, `Paper Out`, `Paper Jammed`).
   - **🔀 Document Routing Rules**: Map specific Odoo Reports or JSON Templates to physical printers, Printer Pools, and virtual queues per branch.
   - **⏰ Scheduled Jobs**: Automated cron-driven batch printing (e.g. print morning shift delivery slips at 06:00 AM, or daily invoices at end-of-day).
   - **⚙️ Settings**: Direct link to configure Print Hub server URL and API Key.

2. **⚡ Ultra-Fast JSON Template Printing (`print_via_hub_template`)**:
   - Bypass slow `wkhtmltopdf` compilation! Odoo extracts record fields into a lightweight JSON dictionary and sends it directly to Print Hub's Continuous Form layout engine.
   - Ideal for continuous dot-matrix stationery (3-ply paper) and thermal shipping labels.

3. **📦 Tree-View Multi-Select Batch Spooling (`print_batch_via_hub`)**:
   - Print up to 50 selected records from any Odoo list/tree view in a single API call via `POST /api/v1/print/batch`.

4. **🏷️ Dynamic Watermarking**:
   - Automatically inject watermarks based on Odoo record lifecycle:
     - `DRAFT` for unposted invoices / unconfirmed orders.
     - `COPY / DUPLICATE` for reprinted documents.
     - `CANCELLED` for cancelled records.

5. **🔀 Printer Pooling & Automatic Failover**:
   - Target a Print Hub **Printer Pool** (e.g. `Warehouse Dot-Matrix Pool`) instead of a single device for automatic load-balancing and zero-downtime failover.

---

## 🚀 Installation & Upgrade Guide

### Step 1: Copy Module to Odoo Addons
```bash
cp -r /path/to/public/sdk/odoo/print_hub_connector /path/to/odoo/custom_addons/
```

### Step 2: Restart Odoo & Upgrade Module
1. In Odoo (with Developer Mode enabled), go to **Apps → Update Apps List**.
2. Search for **"Print Hub"**.
3. Click **Upgrade** (or **Activate**).

### Step 3: Configure Print Hub Server
1. Go to **Print Hub → Configuration → Settings**.
2. Enter your server URL: `http://<your-print-hub-ip>:8000`
3. Enter your API Key created in Print Hub Admin.
4. Set default branch: `SDP-MAIN`.
5. Click **Save**.

### Step 4: Sync Printers
Go to **Print Hub → Printers & Health** and click **"🔄 Sync Live Health from Hub"** to discover all branch printers.

---

## 💻 Developer Usage Examples

### 1. Ultra-Fast JSON Template Printing (Bypass QWeb PDF)

```python
# models/stock_picking.py
from odoo import models

class StockPicking(models.Model):
    _inherit = ['stock.picking', 'print.hub.mixin']

    def action_print_continuous_surat_jalan(self):
        """Print directly using Print Hub's continuous-form layout engine."""
        return self.print_via_hub_template('odoo_surat_jalan_continuous', {
            'picking_number': self.name,
            'customer_name': self.partner_id.name,
            'customer_address': self.partner_id.contact_address,
            'date_done': str(self.date_done or self.scheduled_date),
            'vehicle_number': self.carrier_tracking_ref or '',
            'items': [{
                'product_code': l.product_id.default_code or '',
                'product_name': l.product_id.name,
                'quantity': l.qty_done or l.product_uom_qty,
                'uom': l.product_uom_id.name,
            } for l in self.move_line_ids]
        })
```

---

### 2. Multi-Select Batch Spooling from Tree View

Add a Server Action to print all selected records in a single batch request:

```python
class StockPicking(models.Model):
    _inherit = ['stock.picking', 'print.hub.mixin']

    def action_batch_print_delivery_slips(self):
        """Batch print up to 50 delivery slips in 1 click."""
        return self.print_batch_via_hub(report_name='stock.report_deliveryslip')
```

---

### 3. Automatic Printing on Record State Validation

```python
class AccountMove(models.Model):
    _inherit = ['account.move', 'print.hub.mixin']

    def action_post(self):
        res = super().action_post()
        for move in self:
            if move.is_invoice(include_receipts=True):
                # Automatically spools customer invoice upon posting
                move.print_via_hub('account.report_invoice')
        return res
```

---

## 🔀 Routing Rules Configuration Matrix

Navigate to **Print Hub → Configuration → Routing Rules**:

| Rule Name | Print Engine | Document / Template | Branch | Target Pool / Printer | Watermark Rule |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Surat Jalan Continuous** | JSON Template | `odoo_surat_jalan_continuous` | `SDP-MAIN` | `Warehouse Pool` | Auto State-Based |
| **Tax Invoice Finance** | QWeb PDF | `account.report_invoice` | *(All)* | `HP-LaserJet-Finance` | Auto State-Based |
| **Thermal Shipping Label** | JSON Template | `odoo_thermal_shipping_label` | `SDP-MAIN` | `Zebra-ZD220-Thermal` | None |

---

## ⏰ Scheduled Automation Example

Navigate to **Print Hub → Scheduled Jobs**:
1. Title: `Morning Shift Picking Lists`
2. Target Model: `stock.picking`
3. Domain: `[('state', '=', 'assigned'), ('picking_type_code', '=', 'outgoing')]`
4. Print Engine: `Print Hub JSON Template` (`odoo_surat_jalan_continuous`)
5. Destination: `Warehouse Pool`
6. Click **"⚡ Run Batch Now"** or let the automated cron trigger it at your morning shift time!
