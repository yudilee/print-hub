# Print Hub Connector for Odoo

**Enterprise Direct Background Printing, Hardware Spooling & Multi-Branch Document Routing**

---

## Overview

**Print Hub Connector** seamlessly bridges your Odoo ERP with the enterprise **Print Hub** print server. It enables lightning-fast, direct background printing to thermal receipt printers, dot-matrix continuous form printers, and laser network printers across multiple branches—bypassing browser PDF print dialogs and eliminating driver incompatibilities.

---

## Key Features

1. **Direct Background Printing**: Spool documents directly to physical printers on local branch workstations without opening the browser's print dialog.
2. **Universal Print Wizard**: Single-click printing from the **Action (⚙️)** menu on any document (`job.card`, `account.move`, `stock.picking`, `sale.order`).
3. **Built-in Print Preview**: Preview QWeb PDF layouts directly inside the popup modal before dispatching to physical hardware.
4. **Multi-Branch Isolation (`res.branch`)**:
   - Printers, print jobs, and routing rules are scoped to the user's active branch.
   - Each branch can configure its own default printer and virtual print queue.
5. **Dynamic Document Routing Rules**:
   - Map specific reports (e.g. Surat Jalan, Invoice, Delivery Slip) to dedicated physical printers or load-balanced printer pools.
   - Priority sequence: `Manual Wizard Override > Matching Routing Rule > Branch Default > User Default > Company Default`.
6. **Print Approval Workflow (Optional)**:
   - Sensitive financial documents or delivery slips can require manager authorization prior to physical printing.
   - Fully toggleable in Settings (`Enable Print Approval Workflow`).
7. **Audit Trail & Chatter Tracking**:
   - `print.hub.job` inherits `mail.thread` and `mail.activity.mixin`.
   - Full chatter history tracking status changes, retries, and manager approvals.
   - 1-click **Retry** with failure logging.
8. **Real-time Browser Notifications & Failure Activities**:
   - Instant toast notification pushed to the user's browser via Odoo Bus when a print job completes or encounters an error.
   - Automated `mail.activity` scheduled on the source document when a job fails.
9. **Analytics Dashboard**:
   - Real-time KPI metrics, Kanban view, Pivot analysis (Branch × Status × Copies), and Bar Chart analytics.
10. **Webhook Security**:
    - Optional HMAC-SHA256 signature verification (`X-Webhook-Signature`) on status callbacks.

---

## Architecture & Data Flow

```text
┌─────────────────┐       HTTPS POST /api/v1/print       ┌──────────────────────┐
│    Odoo ERP     │─────────────────────────────────────▶│   Print Hub Server   │
│ (Connector App) │                                      │ (Laravel Cloud Core) │
└─────────────────┘                                      └──────────────────────┘
         ▲                                                           │
         │                                               WebSocket / │ gRPC Push
         │ Webhook Callback                                          ▼
         │ (/print-hub/webhook)                          ┌──────────────────────┐
         └───────────────────────────────────────────────│ Workstation Agent(s) │
                                                         │  (Local LAN Driver)  │
                                                         └──────────────────────┘
                                                                     │ USB / Network
                                                                     ▼
                                                         ┌──────────────────────┐
                                                         │   Physical Printer   │
                                                         │ (Epson, Brother, etc)│
                                                         └──────────────────────┘
```

---

## Installation & Requirements

### Dependencies
- Odoo 17.0+ / 18.0+ / 19.0+
- `base`, `base_setup`, `branch`, `mail`
- Python library: `requests`

### Installation Steps
1. Place the `print_hub_connector` folder into your Odoo custom addons directory:
   ```bash
   /opt/odoo/custom/addons/print_hub_connector
   ```
2. Restart Odoo server:
   ```bash
   sudo systemctl restart odoo-server
   ```
3. Update App List in Odoo (`Apps -> Update Apps List`).
4. Search for `Print Hub` and click **Install** or **Upgrade**.

---

## Configuration

Navigate to **Print Hub ➔ Configuration ➔ Settings** (or **Settings ➔ Print Hub**):

| Setting Field | Description | Example |
|---|---|---|
| **Server URL** | Base URL of your Print Hub instance | `https://print-hub.hartonomotor-group.com` |
| **API Key (X-API-Key)** | API Key issued by Print Hub for this client application | `phk_live_odoo_erp_hrm_stage_...` |
| **Default Branch Code** | Global fallback branch code if not specified on user or branch | `HRMSBY` |
| **Default Queue Profile** | Default virtual profile (driver & paper format) | `default_queue` |
| **Enable Approval Workflow** | Toggle required manager approval for flagged rules | Checked / Unchecked |
| **Webhook HMAC Secret** | Shared secret key for verifying status webhook callbacks | `sec_webhook_...` |

> [!TIP]
> Click the **"🔗 Test Connection"** button in Settings to verify connectivity and view how many physical printers are currently online.
> Click **"🔄 Sync Printers Now"** to instantly pull discovered hardware from all branch workstation agents.

---

## Security Roles & Permissions

The module defines three dedicated security groups under the **Print Hub** category:

| Role | Permissions |
|---|---|
| **Print Hub: User** | Can dispatch print jobs, view own print history, and see branch printer availability. Assigned automatically to all internal users. |
| **Print Hub: Manager** | Can view all print jobs across the branch, manage Document Routing Rules, execute Scheduled Print Automations, and Approve / Reject pending print requests. |
| **Print Hub: Administrator** | Full administrative access, server endpoint configuration, API keys, and hardware synchronization. Assigned automatically to System Administrators. |

---

## Developer Guide (`print.hub.mixin`)

To enable direct printing on any custom Odoo model, inherit `print.hub.mixin`:

```python
from odoo import models

class CustomJobCard(models.Model):
    _name = 'custom.job.card'
    _inherit = ['custom.job.card', 'print.hub.mixin']

    def action_print_direct(self):
        self.ensure_one()
        # Dispatches QWeb report directly to the printer resolved by Print Hub
        return self.print_via_hub(
            report_name='custom_module.report_job_card',
            options={
                'copies': 2,
                'branch_code': 'HRMSBY',  # optional override
            }
        )
```

### High-Speed Continuous Form / Template Printing

```python
    def action_print_continuous_slip(self):
        self.ensure_one()
        # Sends raw record data directly to Print Hub continuous form renderer
        return self.print_via_hub_template(
            template_slug='odoo_surat_jalan_continuous',
            data_dict={
                'no_surat_jalan': self.name,
                'customer_name': self.partner_id.name,
                'items': [{'name': line.product_id.name, 'qty': line.qty} for line in self.line_ids]
            }
        )
```

### High-Speed Batch Spooling

```python
    def action_print_selected_batch(self):
        # Spools multiple records in a single batch API call
        return self.print_batch_via_hub(
            report_name='custom_module.report_invoice',
            options={'copies': 1}
        )
```

---

## Troubleshooting

1. **Printer Status is Offline**:
   - Ensure the branch workstation running the Print Hub Agent software is powered on and connected to LAN.
   - In Odoo, check **Print Hub ➔ Printers & Health** to inspect the agent heartbeat.
2. **Job remains in "Pending Approval"**:
   - The document matched a Routing Rule requiring manager approval.
   - A user with **Print Hub: Manager** permissions must click **"Approve Print"** on the job record.
3. **Webhook Not Updating Status**:
   - Ensure your Odoo base URL is publicly reachable from the Print Hub server or configured with reverse proxy routing to `/print-hub/webhook`.
   - If using HMAC verification, verify that the secret key matches in both Print Hub Admin and Odoo Settings.

---

## License & Support

- **License**: LGPL-3
- **Author**: Print Hub Team
- **Website**: [https://print-hub.hartonomotor-group.com](https://print-hub.hartonomotor-group.com)
