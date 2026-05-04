# Print Hub 🖨️

> Enterprise print job management system with agent-based distributed printing,
> real-time monitoring, approval workflows, and multi-platform print agent support.

[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

---

## ✨ Features

### Core Printing

- **Job Submission** — REST API for submitting print jobs from any client application (ERP, accounting, dealership systems)
- **Agent-Based Printing** — Distributed print agents ([TrayPrint](https://github.com/your-org/trayprint)) installed on print servers across branches
- **Template-Based PDF Generation** — Drag-and-drop WYSIWYG template designer with continuous-form layout engine
- **Profile Management** — Reusable print profiles with paper size, orientation, margins, custom sizes, printer trays, color mode, quality, and scaling
- **Priority System** — Job prioritization (0–255) for urgent print tasks
- **Scheduled Printing** — Schedule jobs for future execution with daily/weekly/monthly recurrence support
- **Printer Control** — Full DEVMODE/CUPS control: tray source, color mode, print quality, scaling, media type, collation, reverse order, duplex
- **Finishing Options** — Stapling (single/dual/saddle), hole punch (2/4 hole), booklet, folding (half/tri/z), binding (tape/comb/thermal)
- **Watermark Support** — Configurable watermark text, opacity, rotation, and position on print profiles

### Document Management

- **Document Upload** — Upload PDF, PNG, JPG documents (up to 50 MB) via REST API
- **Document Preview** — In-browser preview with download support via generated preview URLs
- **Soft Delete** — Safe document removal with recovery (uses `SoftDeletes` trait)

### Print Agents (TrayPrint)

- **Auto-Discovery** — Automatic printer enumeration via CUPS (Linux/macOS) and win32print (Windows)
- **Capability Discovery** — Queries printer capabilities (trays, resolutions, media sizes, color modes, duplex)
- **Status Reporting** — Periodic agent health and printer status reporting to the hub
- **Watchdog** — Automatic spooler monitoring with self-healing restart
- **Auto-Update** — Version checking and self-update mechanism with SHA-256 verification
- **macOS Support** — Full CUPS-based printing for macOS via `lp` command
- **Diagnostics** — Built-in diagnostics dialog with one-click system health report copy

### Scheduling & Automation

- **Job Scheduling** — Schedule print jobs for specific dates/times via `scheduled_at` field
- **Recurrence** — Daily, weekly, monthly recurring print jobs with end date and max count limits
- **Artisan Commands** — [`php artisan print-hub:process-scheduled`](app/Console/Commands/ProcessScheduledJobs.php) for processing scheduled jobs via cron

### Approval Workflow

- **Approval Rules** — Configurable rules based on user, role, page count, or cost thresholds
- **Pending Queue** — Jobs requiring approval are held in a pending queue until approved
- **Web Dashboard** — Admin approval/rejection interface at `/admin/approvals`
- **API Support** — Approve/reject via REST API for external integrations

### Webhooks & Events

- **Event Types** — `job.created`, `job.completed`, `job.failed`, `job.approved`, `job.rejected`, `agent.online`, `agent.offline`, `printer.added`, `printer.removed`
- **HMAC Signing** — SHA-256 signed payloads for payload verification by receivers
- **Retry with Backoff** — Exponential backoff (30s → 2m → 5m) with configurable max attempts
- **Delivery Tracking** — Full delivery history with response status codes and body logging via [`WebhookDelivery`](app/Models/WebhookDelivery.php)

### Real-Time Updates

- **WebSocket Broadcasting** — Job status, agent status, and queue updates via Laravel Reverb (Pusher protocol)
- **Admin Queue Channel** — Real-time queue monitoring on the admin dashboard
- **TrayPrint Integration** — Agent receives instant queue refresh via WebSocket subscription to `admin.queue`

### Printer Pooling

- **Load Balancing** — Round-robin, least-busy, random, and failover strategies
- **Pool Management** — Group printers into pools for redundancy and load distribution
- **Auto-Selection** — Automatic printer selection from pool on job submission with priority ordering

### Enterprise Security

- **API Key Authentication** — Per-agent (`X-Agent-Key`) and per-client (`X-API-Key`) authentication
- **Key Rotation Tracking** — Tracks `last_key_rotated_at` with automatic initialization on creation
- **IP Whitelisting** — CIDR notation support for API access control via [`IpWhitelist`](app/Http/Middleware/IpWhitelist.php) middleware
- **TLS Enforcement** — Automatic HTTP→HTTPS redirect in production via [`ForceTls`](app/Http/Middleware/ForceTls.php) middleware
- **SSO/SAML** — Single Sign-On integration (SAML2) with auto-provisioning support via [`onelogin/php-saml`](config/sso.php)
- **Role-Based Access** — Super Admin, Admin, and Branch roles with granular permission checks via [`CheckRole`](app/Http/Middleware/CheckRole.php) and [`CheckPermission`](app/Http/Middleware/CheckPermission.php) middleware

### Monitoring & Analytics

- **Dashboard** — Real-time stat cards, job timeline, agent health overview at `/admin/dashboard`
- **Monitoring Page** — Comprehensive monitoring dashboard at `/admin/monitoring` with job volume stats, success/failure rates, average processing time, top printers, top users, agent health, version distribution
- **Sustainability Metrics** — CO₂ savings, trees saved, pages saved by duplex printing tracked via `carbon_saved`, `duplex_saved`, `eco_mode` fields
- **Activity Logging** — Comprehensive audit trail of all system actions via [`ActivityLog`](app/Models/ActivityLog.php) model

### Template Designer

- **WYSIWYG Editor** — Drag-and-drop template designer for continuous-form layouts at `/admin/templates/designer`
- **Elements** — Fields, labels, lines, images, tables with multi-page auto-pagination and header repetition
- **Styling** — Named styles, column formatting, borders, background stationery overlays with opacity control
- **Schema Binding** — Templates bind to versioned data schemas for input validation

### Data Schemas

- **Field Definitions** — Types: `string`, `number`, `date`, `boolean` with format specifiers
- **Table Definitions** — Columns, computed expressions (Symfony ExpressionLanguage), minimum rows
- **Versioning** — Auto-changelog generation on schema updates with full version history
- **Localized Formats** — Currency (Rp formatting), `terbilang` (Indonesian spelled-out numbers), custom date formats

### Developer Experience

- **PHP SDK** — Complete PHP client library at [`public/sdk/PrintHubClient.php`](public/sdk/PrintHubClient.php)
- **Python SDK** — Python client with auto-retry at [`public/sdk/PrintHubClient.py`](public/sdk/PrintHubClient.py)
- **Node.js SDK** — ESM JavaScript SDK at [`public/sdk/PrintHubClient.mjs`](public/sdk/PrintHubClient.mjs)
- **Postman Collection** — Pre-built API collection for testing at [`public/sdk/PrintHub-Postman.json`](public/sdk/PrintHub-Postman.json)
- **OpenAPI 3.0 Spec** — Full API specification at [`public/sdk/openapi.yaml`](public/sdk/openapi.yaml)
- **In-App Docs** — SDK documentation page at `/admin/sdk-docs`

---

## 🏗 Architecture

```
┌──────────────┐     ┌──────────────────┐     ┌─────────────────┐
│  Client App  │────▶│                  │◀────│  TrayPrint Agent │
│  (Odoo, ERP) │     │   Print Hub      │     │  (Windows/Linux) │
└──────────────┘     │   (Laravel)      │     └─────────────────┘
                     │                  │     ┌─────────────────┐
┌──────────────┐     │   ┌──────────┐   │     │  TrayPrint Agent │
│  Web Admin   │────▶│   │ Queue +  │   │◀────│  (macOS)         │
│  (Browser)   │     │   │ Profiles │   │     └─────────────────┘
└──────────────┘     │   └──────────┘   │
                     │                  │     ┌─────────────────┐
┌──────────────┐     │   ┌──────────┐   │     │  Webhook Clients │
│  Monitoring  │────▶│   │ Webhooks │   │────▶│  (External)      │
│  Dashboard   │     │   │ Events   │   │     └─────────────────┘
└──────────────┘     └──────────────────┘
```

### Data Flow

1. **Client app** submits print request with `template` + `data` (or raw `document_base64`)
2. **Print Hub** validates data against the template's bound schema and generates PDF via the Continuous Form Engine (FPDF)
3. **Job** is stored and assigned to an online print agent (routing priority: explicit agent → profile → branch → global)
4. **Print agent** polls `/api/print-hub/queue`, downloads the PDF, sends to physical printer
5. **Agent** reports status back; webhook is fired if configured

---

## 🚀 Quick Start

### Using Docker (Recommended)

```bash
# Clone the repository
git clone https://github.com/your-org/print-hub.git
cd print-hub

# Copy environment file
cp .env.example .env
# Edit .env with your database and Reverb settings

# Build and start containers
docker compose up -d

# Run migrations with demo data
docker compose exec app php artisan migrate --seed

# Access the admin panel
open http://localhost:8000/admin
# Default credentials: admin@printhub.local / password
```

### Manual Installation

```bash
# Requirements: PHP 8.2+, Composer, Node.js 20+, SQLite/MySQL

cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm run build
php artisan serve
```

### Development Mode

```bash
composer dev
```

Starts concurrently: `php artisan serve` + `php artisan queue:listen` + `php artisan pail` + `npm run dev`.

### Running Tests

```bash
composer test
# Or manually:
php artisan test
```

---

## 📚 API Documentation

Full API documentation is available:

- **OpenAPI Spec**: [`public/sdk/openapi.yaml`](public/sdk/openapi.yaml)
- **Postman Collection**: [`public/sdk/PrintHub-Postman.json`](public/sdk/PrintHub-Postman.json)
- **SDK Documentation**: Accessible at `/admin/sdk-docs` in the admin panel

### Agent API (`/api/print-hub`)

Authenticated via `Bearer` token or `X-Agent-Key` header.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/profiles` | List print profiles for this agent |
| GET | `/queue` | Pull pending print jobs |
| POST | `/jobs` | Report job status update |
| POST | `/status` | Report agent health and printer list |
| GET | `/cors-origins` | Get allowed CORS origins |

### Client App API (`/api/v1`)

Authenticated via `X-API-Key` header.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/test` | Connection health check |
| GET | `/agents/online` | List currently online agents |
| GET | `/queues` | List print queues with depth |
| GET | `/templates` | List all print templates |
| GET | `/templates/{name}` | Get template details |
| GET | `/templates/{name}/schema` | Get template data schema |
| POST | `/schema` | Register or update a data schema |
| GET | `/schemas` | List all schemas |
| GET | `/schema/{name}/versions` | Schema version history |
| POST | `/print` | Submit a print job |
| POST | `/print/batch` | Submit batch print jobs |
| POST | `/preview` | Generate a preview PDF |
| GET | `/jobs/{job_id}` | Check job status |

### Quick API Examples

**Submit a print job:**
```bash
curl -X POST https://your-hub.com/api/v1/print \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "printer": "Office Printer",
    "template": "invoice",
    "data": {
      "customer": "ACME Corp",
      "total": 1500000
    },
    "copies": 2,
    "priority": 5
  }'
```

**Upload a document:**
```bash
curl -X POST https://your-hub.com/api/v1/documents/upload \
  -H "X-API-Key: your-api-key" \
  -F "file=@document.pdf"
```

---

## 🖥️ Admin Panel

Access the admin panel at `/admin` after installation:

| Section | Description |
|---------|-------------|
| [Dashboard](resources/views/admin/dashboard.blade.php) | Overview of system health, recent jobs, queue depth |
| [Jobs](resources/views/admin/jobs.blade.php) | View, filter, cancel, and manage print jobs |
| [Profiles](resources/views/admin/profiles.blade.php) | Create and manage print profiles with all printer control options |
| [Templates](resources/views/admin/templates/index.blade.php) | Design and manage print templates (WYSIWYG designer) |
| [Documents](resources/views/admin/documents/index.blade.php) | Upload, preview, and manage documents |
| [Agents](resources/views/admin/agents.blade.php) | View and manage TrayPrint agents |
| [Clients](resources/views/admin/clients.blade.php) | Manage client applications with API keys |
| [Approvals](resources/views/admin/approvals/index.blade.php) | Approve or reject pending print jobs |
| [Pools](resources/views/admin/pools/index.blade.php) | Create printer pools with load-balancing strategies |
| [Monitoring](resources/views/admin/monitoring/index.blade.php) | Real-time system monitoring dashboard |
| [Activity Logs](resources/views/admin/activity-logs/index.blade.php) | Audit trail of all system actions |
| [Companies](resources/views/admin/companies/index.blade.php) | Multi-tenant company management |
| [Branches](resources/views/admin/branches/index.blade.php) | Branch location management |
| [Users](resources/views/admin/users/index.blade.php) | User management with role assignment |
| [SSO](resources/views/admin/sso/index.blade.php) | SAML2 Single Sign-On configuration |
| SDK Docs | Interactive API documentation |

---

## 🗄 Database Migrations

All migrations are in [`database/migrations/`](database/migrations/). The system uses sequential, feature-organized migrations:

| Migration | Purpose |
|-----------|---------|
| `2026_04_03_000001_create_print_tables.php` | Core print tables (jobs, profiles, agents, templates) |
| `2026_04_03_094214_add_queue_fields_to_print_jobs_table.php` | Queue management fields |
| `2026_04_03_124026_create_print_templates_table.php` | Template system |
| `2026_04_04_011341_create_client_apps_table.php` | Client application management |
| `2026_04_04_022811_add_printers_to_print_agents_table.php` | Agent printer lists |
| `2026_04_05_121421_add_extra_fields_to_print_templates_table.php` | Template enhancements |
| `2026_04_05_190001_create_data_schemas_table.php` | Data schema definitions |
| `2026_04_06_010000_add_schema_versioning.php` | Schema versioning support |
| `2026_04_06_080056_add_margins_and_custom_size_to_print_profiles.php` | Custom paper sizes and margins |
| `2026_04_22_000002_create_user_sessions_table.php` | User session tracking |
| `2026_04_22_020152_add_priority_to_print_jobs_table.php` | Job priority field |
| `2026_04_29_000001_create_companies_and_branches_tables.php` | Multi-tenant structure |
| `2026_04_29_000002_create_branch_template_defaults_table.php` | Per-branch template defaults |
| `2026_04_29_000003_create_activity_logs_table.php` | Activity audit trail |
| `2026_04_30_000001_add_printer_control_fields_to_print_profiles.php` | DEVMODE/CUPS controls |
| `2026_05_04_000001_add_last_key_rotated_to_agents_and_clients.php` | Key rotation tracking |
| `2026_05_04_000002_add_scheduling_to_print_jobs.php` | Job scheduling and recurrence |
| `2026_05_04_000003_create_print_documents_table.php` | Document upload management |
| `2026_05_04_000004_add_approval_to_print_jobs.php` | Approval workflow |
| `2026_05_04_000007_create_printer_pools.php` | Printer pooling and load balancing |
| `2026_05_04_000009_add_finishing_to_print_profiles.php` | Finishing options |
| `2026_05_04_000010_add_sustainability_to_profiles.php` | Sustainability metrics |

---

## 🔧 Configuration

Key environment variables in [`.env.example`](.env.example):

```
# Database
DB_CONNECTION=sqlite          # or mysql, pgsql
QUEUE_CONNECTION=database     # recommended for production

# Broadcasting (WebSocket)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret

# Security
API_IP_WHITELIST=192.168.1.0/24,10.0.0.1

# SSO
SSO_ENABLED=false
SSO_PROVIDER=saml2

# Agent Updates
AGENT_LATEST_VERSION=1.0.0
AGENT_DOWNLOAD_URL=

# Application
APP_URL=https://your-hub.com
SESSION_DRIVER=database
```

---

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run feature tests only
php artisan test --testsuite=Feature

# Run with code coverage (requires Xdebug/PCOV)
php artisan test --coverage
```

---

## 🛠 Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | [Laravel 12](https://laravel.com), PHP 8.2 |
| Database | SQLite (configurable to MySQL/PostgreSQL) |
| PDF Engine | FPDF ([`setasign/fpdf`](https://www.fpdf.org)) |
| Frontend | Blade + [Tailwind CSS 4](https://tailwindcss.com) + Vite 7 |
| Real-time | [Laravel Reverb](https://reverb.laravel.com) / WebSockets |
| Expression Engine | Symfony ExpressionLanguage (computed columns in schemas) |
| SSO | SAML2 via `onelogin/php-saml` |

---

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## 📄 License

MIT License — see [LICENSE](LICENSE) for details.

---

## 🙏 Acknowledgments

- [Laravel](https://laravel.com) — The PHP framework
- [Laravel Reverb](https://reverb.laravel.com) — WebSocket server
- [FPDF](https://www.fpdf.org) — PDF generation library
- [Tailwind CSS](https://tailwindcss.com) — Admin UI styling
- [TrayPrint](https://github.com/your-org/trayprint) — Cross-platform print agent
