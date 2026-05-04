# Print Hub

A centralized print management middleware for multi-branch organizations. Print Hub sits between client applications (ERP, accounting, dealership systems) and desktop print agents, managing template-based PDF generation, job routing, and print status tracking.

## Architecture

```
Client Apps (ERP, CRM, etc.)
        │
        ▼
   ┌─────────┐      ┌──────────────┐
   │ Print   │◄────►│ Print Agents │──► Physical Printers
   │ Hub     │      │ (TrayPrint)  │
   └─────────┘      └──────────────┘
        │
        ▼
   ┌─────────┐
   │ Admin   │  (Web UI for management)
   │ Panel   │
   └─────────┘
```

### Key Concepts

| Concept | Description |
|---------|-------------|
| **Company** | Top-level tenant (e.g., "HRM Auto Group") |
| **Branch** | Physical location under a company |
| **Print Agent** | Desktop PC running TrayPrint that connects to physical printers |
| **Print Profile** | Named configuration (paper size, orientation, margins, target printer) |
| **Print Template** | Drag-and-drop designed layout binding data fields to page positions |
| **Data Schema** | Versioned schema defining what data a template expects |
| **Client App** | Third-party application with API key access |

### Data Flow

1. Client app sends print request with `template` + `data` (or raw `document_base64`)
2. Print Hub validates data against the template's bound schema
3. PDF is generated via the Continuous Form Engine (FPDF)
4. Job is stored and assigned to an online print agent (routing priority: explicit agent → profile → branch → global)
5. Print agent polls `/api/print-hub/queue`, downloads the PDF, sends to printer
6. Agent reports status back; webhook is fired if configured

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12, PHP 8.2 |
| Database | SQLite (configurable to MySQL/PostgreSQL) |
| PDF Engine | FPDF (`setasign/fpdf`) |
| Frontend | Blade + Tailwind CSS 4 + Vite 7 |
| Real-time | Laravel Reverb / WebSockets |
| Expression Engine | Symfony ExpressionLanguage (computed columns) |

## Getting Started

### Prerequisites

- PHP 8.2+ with `sqlite`, `gd`, `mbstring` extensions
- Composer
- Node.js 18+

### Setup

```bash
composer setup
```

Or manually:

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --force
npm install && npm run build
```

### Development

```bash
composer dev
```

Starts concurrently: `php artisan serve` + `php artisan queue:listen` + `php artisan pail` + `npm run dev`.

### Running Tests

```bash
composer test
```

## API Reference

### Print Agent API (`/api/print-hub`)

Authenticated via `Bearer` token or `X-Agent-Key` header.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/profiles` | List print profiles for this agent |
| GET | `/queue` | Pull pending jobs |
| POST | `/jobs` | Report job status |
| POST | `/status` | Update agent printer list |
| GET | `/cors-origins` | Get allowed CORS origins |

### Client App API (`/api/v1`)

Authenticated via `X-API-Key` header.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/test` | Connection test |
| GET | `/agents/online` | List online agents |
| GET | `/queues` | List print queues |
| GET | `/templates` | List all templates |
| GET | `/templates/{name}` | Get template details |
| GET | `/templates/{name}/schema` | Get template data requirements |
| POST | `/schema` | Register/update data schema |
| GET | `/schemas` | List schemas |
| GET | `/schema/{name}/versions` | Schema version history |
| POST | `/print` | Submit print job |
| POST | `/print/batch` | Submit batch of print jobs |
| POST | `/preview` | Generate preview PDF |
| GET | `/jobs/{job_id}` | Check job status |

## Template Designer

The admin panel includes a drag-and-drop WYSIWYG template designer for creating continuous-form layouts:

- **Elements**: Fields, labels, lines, images, tables
- **Tables**: Multi-page auto-pagination with header repetition
- **Styling**: Named styles, column formatting, borders
- **Background**: Pre-printed stationery overlay (upload background image, set opacity)
- **Schema binding**: Templates bind to versioned data schemas for validation

## Data Schemas

Schemas are versioned and support:

- Field definitions (type, format, required validation)
- Table definitions (columns, computed expressions, minimum rows)
- Sample data for preview/testing
- Auto-changelog generation on schema updates

Supported field types/formats:
- `number` — `currency` (Rp formatting), `integer`, `terbilang` (Indonesian spelled-out numbers)
- `date` — custom format strings (dd/MM/yyyy, etc.)
- `boolean`
- `string`

## Deployment

### Docker

```bash
docker compose up -d
```

The image uses SQLite by default. For production, mount a volume for `database/database.sqlite` and configure `QUEUE_CONNECTION=database` and `APP_ENV=production`.

### Environment Variables

| Variable | Description |
|----------|-------------|
| `APP_URL` | Public URL of the hub |
| `DB_CONNECTION` | `sqlite`, `mysql`, or `pgsql` |
| `QUEUE_CONNECTION` | `database` recommended |
| `BROADCAST_CONNECTION` | `reverb` for real-time |
| `SESSION_DRIVER` | `database` |

## License

MIT
