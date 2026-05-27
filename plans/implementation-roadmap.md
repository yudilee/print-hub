# Print Hub & TrayPrint — Implementation Roadmap

> **Note:** All file paths are relative to the project bases:
> - Print Hub: `/home/yudi/dev/print-hub`
> - TrayPrint: `/home/yudi/dev/trayprint`

---

## Phase 0: Foundation & Quick Wins (Week 1)
**Goal:** Get the basics right — accessibility labeling, WebSocket enablement, key migration

### Task 0.1 — Enable WebSocket in TrayPrint
**Type:** TrayPrint · Effort: 15 mins · Dependencies: None

- [ ] Install `websockets` library in TrayPrint venv: `pip install websockets`
- [ ] Verify WebSocket client connects to Print Hub Reverb server (config in `config/app.php`)
- [ ] Test real-time job dispatch: submit job → TrayPrint receives immediately instead of waiting 60s poll

**Files:** [`websocket_client.py`](/home/yudi/dev/trayprint/websocket_client.py), [`app.py`](/home/yudi/dev/trayprint/app.py:660-667), [`config.json`](/home/yudi/dev/trayprint/config.json)

### Task 0.2 — Complete Agent Key Migration
**Type:** Print Hub · Effort: 30 mins · Dependencies: None

- [ ] Run `php artisan print-hub:migrate-key-hashing` to see which agents need rotation
- [ ] For each affected agent, rotate the key via admin panel or CLI
- [ ] Update each TrayPrint agent's `config.json` with the newly generated key
- [ ] Verify heartbeat passes with the new key

**Files:** [`app/Console/Commands/MigrateKeyHashing.php`](/app/Console/Commands/MigrateKeyHashing.php), [`app/Models/PrintAgent.php`](/app/Models/PrintAgent.php:72-108)

### Task 0.3 — Add `aria-label` to All Icon-Only Buttons
**Type:** Print Hub · Effort: 1 hour · Dependencies: None

- [ ] Audit all `<button>` elements that contain only icons/emoji across all admin views
- [ ] Add `aria-label="..."` to each:
  - Copy buttons → `aria-label="Copy to clipboard"`
  - Edit buttons → `aria-label="Edit [item name]"`
  - Delete buttons → `aria-label="Delete [item name]"`
  - Theme toggle → already has `aria-label="Toggle theme"` ✅
  - Hamburger menu → already has `aria-label="Toggle menu"` ✅
- [ ] Add `title` attribute as visual tooltip fallback

**Files to modify:** All `resources/views/admin/*.blade.php` files containing icon buttons

### Task 0.4 — Add Keyboard Navigation for Quick Filter Buttons
**Type:** Print Hub · Effort: 1 hour · Dependencies: None

- [ ] Add `role="tablist"` wrapper around quick filter buttons in [`jobs.blade.php`](/resources/views/admin/jobs.blade.php:69)
- [ ] Add `role="tab"`, `tabindex`, and `aria-selected` to each filter `<button>`
- [ ] Implement arrow-key navigation (Left/Right to switch filters)
- [ ] Add Esc key to clear active filter

**Files:** [`resources/views/admin/jobs.blade.php`](/resources/views/admin/jobs.blade.php)

### Task 0.5 — Hot-Reload Config Changes in TrayPrint
**Type:** TrayPrint · Effort: 2 hours · Dependencies: None

- [ ] Verify `reload_config()` in [`server.py:1253`](/home/yudi/dev/trayprint/server.py:1253) is called when settings are saved in UI
- [ ] Add a `QTimer` in the settings dialog that signals the server to reload config after save
- [ ] Test: change `sync_interval_seconds` from 60 to 30 → verify new interval takes effect without restart

**Files:** [`server.py`](/home/yudi/dev/trayprint/server.py:1248-1267), [`ui_settings.py`](/home/yudi/dev/trayprint/ui_settings.py:1234-1236), [`app.py`](/home/yudi/dev/trayprint/app.py:684-701)

---

## Phase 1: Accessibility Foundation (Week 2)
**Goal:** Meet WCAG 2.1 AA compliance for the admin UI

### Task 1.1 — Semantic Tables & ARIA Roles
**Type:** Print Hub · Effort: 2 hours · Dependencies: Phase 0

- [ ] Add `<caption class="sr-only">` to all data tables
- [ ] Add `role="table"` to `<table>` elements
- [ ] Add `scope="col"` / `scope="row"` to all `<th>` elements
- [ ] Add `aria-sort` on sortable columns

**Files:** All `resources/views/admin/*.blade.php` with `<table>` elements

### Task 1.2 — Accessible Modal Dialogs
**Type:** Print Hub · Effort: 2 hours · Dependencies: Task 1.1

- [ ] Refactor [`<x-modal>`](/resources/views/components/modal.blade.php) component to include:
  - `role="dialog"` and `aria-modal="true"`
  - `aria-labelledby` pointing to the modal title element
  - `aria-describedby` pointing to the modal body
- [ ] Add focus trapping: Tab cycles within modal, does not escape to background
- [ ] Add Esc key to close modal
- [ ] Add `aria-hidden="true"` on background content when modal is open

**Files:** [`resources/views/components/modal.blade.php`](/resources/views/components/modal.blade.php)

### Task 1.3 — Screen Reader Announcements
**Type:** Print Hub · Effort: 1.5 hours · Dependencies: Task 1.1

- [ ] Add `role="alert"` or `aria-live="polite"` to:
  - Toast/notification containers
  - Form validation error summaries
  - Async loading status messages
- [ ] Add `aria-busy="true"` on containers during async data fetches
- [ ] Add `aria-label` description for the auto-refresh indicator (pulsing green dot)

**Files:** [`resources/views/admin/layout.blade.php`](/resources/views/admin/layout.blade.php), [`resources/views/admin/dashboard.blade.php`](/resources/views/admin/dashboard.blade.php), individual view files

### Task 1.4 — Sidebar Navigation Accessibility
**Type:** Print Hub · Effort: 1 hour · Dependencies: Task 1.1

- [ ] Add `aria-current="page"` on the active sidebar link using a Blade conditional
- [ ] Add `aria-label="Main navigation"` on `<nav>` element
- [ ] Add `role="navigation"` if not already present
- [ ] Ensure sidebar collapse button announces expanded/collapsed state via `aria-expanded`

**Files:** [`resources/views/admin/layout.blade.php`](/resources/views/admin/layout.blade.php)

### Task 1.5 — Color & Contrast Audit
**Type:** Print Hub · Effort: 1 hour · Dependencies: None

- [ ] Check `--text-muted` CSS variable against `--bg` background for WCAG AA (4.5:1 ratio)
- [ ] Ensure status badges have icon prefix + text (already partially done with emoji ✅)
- [ ] Increase `help-tip` popover font size minimum to 12px
- [ ] Add `:focus-visible` outlines on all interactive elements

**Files:** [`resources/views/admin/layout.blade.php`](/resources/views/admin/layout.blade.php) (CSS section)

---

## Phase 2: Usability Enhancements (Weeks 3-4)
**Goal:** Reduce friction in common workflows, add missing features

### Task 2.1 — Agent Capability Visualization
**Type:** Print Hub · Effort: 4 hours · Dependencies: None

**Reference:** [`plans/capabilities-visibility-plan.md`](/plans/capabilities-visibility-plan.md)

- [ ] Add a "Capabilities" tab/panel in agent detail/edit view
- [ ] Parse `print_agents.capabilities` JSON column into human-readable tables:
  - Paper sizes list per printer
  - Available trays
  - Color modes supported
  - Duplex options
  - Resolutions
- [ ] Add "Last Capability Refresh" timestamp
- [ ] Show raw JSON as collapsible for debugging

**Files:** [`resources/views/admin/agents.blade.php`](/resources/views/admin/agents.blade.php), [`app/Models/PrintAgent.php`](/app/Models/PrintAgent.php)

### Task 2.2 — Inline Job Detail Expansion
**Type:** Print Hub · Effort: 3 hours · Dependencies: None

- [ ] Add Alpine.js `x-data="{ expanded: null }"` on jobs table
- [ ] For each job row, add `x-show` expandable sub-row showing:
  - Full job metadata (reference_id, client, template)
  - Error message (if failed) with stack trace
  - Document preview thumbnail (if PDF)
  - Timestamps: created, processing, completed
- [ ] Add `aria-expanded` on the expand toggle button

**Files:** [`resources/views/admin/jobs.blade.php`](/resources/views/admin/jobs.blade.php)

### Task 2.3 — Bulk Job Retry with Checkbox Selection
**Type:** Print Hub · Effort: 2 hours · Dependencies: Task 2.2

- [ ] Add checkbox column to jobs table (`<input type="checkbox" x-model="selectedJobs">`)
- [ ] Add "Select All" / "Deselect All" header checkbox
- [ ] Add "Retry Selected (N)" button that appears when ≥1 job selected
- [ ] Add POST route for bulk retry: `POST /admin/jobs/bulk-retry`
- [ ] Add confirmation dialog showing count of jobs to retry

**Files:** [`resources/views/admin/jobs.blade.php`](/resources/views/admin/jobs.blade.php), [`routes/web.php`](/routes/web.php), new controller method or existing [`JobController`](/app/Http/Controllers/Admin/JobController.php)

### Task 2.4 — Profile Duplicate (Clone) Function
**Type:** Print Hub · Effort: 1.5 hours · Dependencies: None

- [ ] Add "Clone" button to each profile row in profiles list
- [ ] Clone route: `GET /admin/profiles/{id}/clone` — pre-fills create form with existing values
- [ ] Add hidden `cloned_from` field for audit trail

**Files:** [`resources/views/admin/profiles.blade.php`](/resources/views/admin/profiles.blade.php), [`ProfileController`](/app/Http/Controllers/Admin/ProfileController.php)

### Task 2.5 — Live Watermark Preview
**Type:** Print Hub · Effort: 3 hours · Dependencies: None

- [ ] Add a `<canvas>` element in the profile edit form
- [ ] On input change (text, opacity, rotation, position), redraw the canvas
- [ ] Show a sample document page with the watermark overlaid
- [ ] Include "Show preview" toggle button

**Files:** [`resources/views/admin/edit_profile.blade.php`](/resources/views/admin/edit_profile.blade.php)

### Task 2.6 — Agent Activity Timeline
**Type:** Print Hub · Effort: 4 hours · Dependencies: None

- [ ] Create an activity log view for each agent showing:
  - Agent registered
  - Key rotated
  - Heartbeat received (last seen)
  - Printers changed
  - Status changed (active/inactive)
  - Version updated
- [ ] Use existing [`ActivityLog`](/app/Models/ActivityLog.php) model or add agent-specific events

**Files:** New view `resources/views/admin/agents/activity.blade.php`, [`AgentController`](/app/Http/Controllers/Admin/AgentController.php)

### Task 2.7 — Dashboard Recent Failures Widget
**Type:** Print Hub · Effort: 1.5 hours · Dependencies: None

- [ ] Add "Recent Failures" card to dashboard showing last 5 failed jobs
- [ ] Each entry shows: job_id, printer, error message (truncated), timestamp
- [ ] Add "Retry" button per entry
- [ ] Add "View All Failed Jobs" link

**Files:** [`resources/views/admin/dashboard.blade.php`](/resources/views/admin/dashboard.blade.php), [`AdminController`](/app/Http/Controllers/AdminController.php)

---

## Phase 3: Feature Complete (Weeks 5-6)
**Goal:** Fill feature gaps in both apps

### Task 3.1 — TrayPrint Auto-Update
**Type:** TrayPrint · Effort: 4 hours · Dependencies: None

**Reference:** [`updater.py`](/home/yudi/dev/trayprint/updater.py) — already partially implemented

- [ ] Verify update checker queries `GET /api/v1/agents/version` correctly
- [ ] Implement download logic: fetch new MSI from `download_url`
- [ ] Implement install logic: download → verify SHA-256 → launch installer silently
- [ ] Add "Update Available" notification in system tray
- [ ] Add "Check for Updates" button in Settings dialog
- [ ] Update `latest_version` and `download_url` fields in Print Hub settings

**Files:** [`updater.py`](/home/yudi/dev/trayprint/updater.py), [`app.py`](/home/yudi/dev/trayprint/app.py:653-667), [`ui_settings.py`](/home/yudi/dev/trayprint/ui_settings.py)

### Task 3.2 — Enable WebSocket Real-Time Updates (Full)
**Type:** Both · Effort: 3 hours · Dependencies: Task 0.1

- [ ] Install `websockets` in TrayPrint venv
- [ ] Verify Reverb server config in Print Hub [`config/reverb.php`](/config/reverb.php)
- [ ] Create `config.json` WebSocket section: `{ "websocket": { "host": "...", "port": 8080, "app_id": "...", "key": "..." } }`
- [ ] Test: submit job via API → TrayPrint receives within <1s

**Files:** [`websocket_client.py`](/home/yudi/dev/trayprint/websocket_client.py), [`config/reverb.php`](/config/reverb.php)

### Task 3.3 — TrayPrint Retry from Tray Menu
**Type:** TrayPrint · Effort: 2 hours · Dependencies: None

- [ ] In the "Recent Jobs" submenu, add "Retry" action for failed jobs
- [ ] Retry logic: re-submit the same job data to the local Flask API `/print`
- [ ] Update the tray menu dynamically when job status changes

**Files:** [`app.py`](/home/yudi/dev/trayprint/app.py) (tray menu section), [`server.py`](/home/yudi/dev/trayprint/server.py:574-686) (job retry)

### Task 3.4 — Printer Capabilities Drill-Down in TrayPrint UI
**Type:** TrayPrint · Effort: 3 hours · Dependencies: None

- [ ] Add a "Printer Details" dialog accessible from system tray
- [ ] Show per-printer: name, status, location, is_default
- [ ] Show capabilities: paper sizes, trays, color modes, duplex, resolutions (data from [`capabilities.py`](/home/yudi/dev/trayprint/capabilities.py))
- [ ] Add "Test Print" button that prints a test page

**Files:** New file or add to [`ui_settings.py`](/home/yudi/dev/trayprint/ui_settings.py), [`capabilities.py`](/home/yudi/dev/trayprint/capabilities.py)

### Task 3.5 — Prometheus Metrics Endpoint
**Type:** Both · Effort: 4 hours · Dependencies: None

**Print Hub:**
- [ ] Add `GET /metrics` route returning Prometheus-format text
- [ ] Metrics: `print_hub_jobs_total{status,agent,branch}`, `print_hub_agents_online`, `print_hub_queue_depth`
- [ ] Use `prometheus_client_exporter` package or manual text format

**TrayPrint:**
- [ ] Add `/metrics` endpoint to Flask server
- [ ] Metrics: `trayprint_jobs_total{status,printer}`, `trayprint_hub_connected`, `trayprint_uptime_seconds`

**Files:** New controller or route for Print Hub, [`server.py`](/home/yudi/dev/trayprint/server.py) for TrayPrint

### Task 3.6 — Structured JSON Logging
**Type:** Both · Effort: 2 hours · Dependencies: None

**Print Hub:**
- [ ] Configure Laravel to use JSON logging: set `LOG_CHANNEL=json` or use custom formatter
- [ ] Each log line: `{"timestamp":"...", "level":"...", "message":"...", "job_id":"...", ...}`

**TrayPrint:**
- [ ] Refactor [`log_utils.py`](/home/yudi/dev/trayprint/log_utils.py) to support JSON output mode
- [ ] Add `log_format: "json"` config option

**Files:** [`config/logging.php`](/config/logging.php), [`log_utils.py`](/home/yudi/dev/trayprint/log_utils.py)

### Task 3.7 — Email/Slack/Telegram Alerts
**Type:** Print Hub · Effort: 4 hours · Dependencies: None

- [ ] Add notification channels config in Settings page
- [ ] Events to notify on:
  - Agent offline >5 min
  - Job failure rate > threshold (e.g., 3 failures in 5 min)
  - Key rotation due in <7 days
  - New agent registered
- [ ] Use existing [`Notification`](/app/Models/Notification.php) model and [`NotificationController`](/app/Http/Controllers/Admin/NotificationController.php)

**Files:** [`config/services.php`](/config/services.php), new notification classes in `app/Notifications/`

---

## Phase 4: Security & Performance (Weeks 7-8)
**Goal:** Harden the system and optimize performance

### Task 4.1 — IP Whitelist Enforcement in Agent Auth
**Type:** Print Hub · Effort: 2 hours · Dependencies: None

- [ ] In [`authenticateAgent()`](/app/Http/Controllers/Api/PrintHubController.php:21), add IP check:
  - If `$agent->allowed_ips` is not empty, verify `$request->ip()` is in list
  - Support CIDR notation (already stored that way)
  - Return 403 if IP not allowed

**Files:** [`app/Http/Controllers/Api/PrintHubController.php`](/app/Http/Controllers/Api/PrintHubController.php)

### Task 4.2 — Per-Key Rate Limiting
**Type:** Print Hub · Effort: 2 hours · Dependencies: None

- [ ] Add rate limiter keyed by agent_key / api_key (not just IP)
- [ ] Use Laravel's `RateLimiter::for('agent-key', ...)` facade
- [ ] Configure limits in Settings page

**Files:** [`app/Http/Middleware/ThrottleApiKeys.php`](/app/Http/Middleware/ThrottleApiKeys.php), [`config/app.php`](/config/app.php)

### Task 4.3 — Server-Side Pagination for Jobs Page
**Type:** Print Hub · Effort: 3 hours · Dependencies: None

- [ ] Replace client-side filtering with server-side pagination using `lengthAwarePaginator`
- [ ] Add page size selector: 10/25/50/100
- [ ] Add sort by column (click header to sort)
- [ ] Preserve filter state in URL query params

**Files:** [`JobController`](/app/Http/Controllers/Admin/JobController.php), [`resources/views/admin/jobs.blade.php`](/resources/views/admin/jobs.blade.php)

### Task 4.4 — Force TLS in Production
**Type:** Print Hub · Effort: 1 hour · Dependencies: None

- [ ] Enable [`ForceTls`](/app/Http/Middleware/ForceTls.php) middleware for all production routes
- [ ] Make sure `TrustProxies` is configured for reverse proxy (already done in recent commit `1ca27bd`)
- [ ] Add HSTS header

**Files:** [`bootstrap/app.php`](/bootstrap/app.php), [`app/Http/Middleware/ForceTls.php`](/app/Http/Middleware/ForceTls.php)

### Task 4.5 — Windows MSI Installer Completion
**Type:** TrayPrint · Effort: 8 hours · Dependencies: None

**Reference:** [`plans/windows-installer-scheduled-task-plan.md`](/plans/windows-installer-scheduled-task-plan.md)

- [ ] Complete WiX source file [`installer/trayprint.wxs`](/home/yudi/dev/trayprint/installer/trayprint.wxs)
- [ ] Create `Register-TrayPrintTask.ps1` and `Unregister-TrayPrintTask.ps1`
- [ ] Update `build.py` to generate MSI after PyInstaller build
- [ ] Test: install MSI → verify Scheduled Task created → verify app starts on reboot

**Files:** [`installer/`](/home/yudi/dev/trayprint/installer/) directory, [`build.py`](/home/yudi/dev/trayprint/build.py), [`app.py`](/home/yudi/dev/trayprint/app.py)

---

## Dependencies Graph

```
Phase 0 (Foundation)
├── Task 0.1 WebSocket     ← independent
├── Task 0.2 Key Migration ← independent
├── Task 0.3 aria-label    ← independent
├── Task 0.4 Keyboard Nav  ← independent
└── Task 0.5 Hot-Reload    ← independent

Phase 1 (Accessibility)
├── Task 1.1 Tables/ARIA   ← independent
├── Task 1.2 Modals        ← depends on 1.1 (modal component)
├── Task 1.3 Screen Reader ← depends on 1.1 (uses modal)
├── Task 1.4 Sidebar       ← independent
└── Task 1.5 Color Audit   ← independent

Phase 2 (Usability)
├── Task 2.1 Capabilities  ← independent
├── Task 2.2 Job Detail    ← independent
├── Task 2.3 Bulk Retry    ← depends on 2.2 (same view)
├── Task 2.4 Clone Profile ← independent
├── Task 2.5 Watermark     ← independent
├── Task 2.6 Activity      ← independent
└── Task 2.7 Dashboard     ← independent

Phase 3 (Features)
├── Task 3.1 Auto-Update   ← independent
├── Task 3.2 WebSocket     ← depends on 0.1
├── Task 3.3 Tray Retry    ← independent
├── Task 3.4 Capabilities  ← independent
├── Task 3.5 Metrics       ← independent
├── Task 3.6 JSON Logging  ← independent
└── Task 3.7 Alerts        ← independent

Phase 4 (Security/Perf)
├── Task 4.1 IP Whitelist  ← depends on Phase 0.2
├── Task 4.2 Rate Limit    ← independent
├── Task 4.3 Pagination    ← independent
├── Task 4.4 Force TLS     ← independent
└── Task 4.5 MSI Installer ← independent
```

## Phase 0: Quick Wins — Immediate Todo List

```yaml
sprint_0:
  name: "Foundation & Quick Wins"
  duration: "1 week"
  tasks:
    - Enable WebSocket in TrayPrint (15 min)
    - Run key migration & rotate keys (30 min)
    - Add aria-label to all icon buttons (1 hr)
    - Add keyboard navigation for filter buttons (1 hr)
    - Hot-reload config changes (2 hr)

sprint_1:
  name: "Accessibility Foundation"
  duration: "1 week"
  tasks:
    - Semantic tables & ARIA roles (2 hr)
    - Accessible modal dialogs (2 hr)
    - Screen reader announcements (1.5 hr)
    - Sidebar navigation accessibility (1 hr)
    - Color & contrast audit (1 hr)

sprint_2:
  name: "Usability Enhancements"
  duration: "2 weeks"
  tasks:
    - Agent capability visualization (4 hr)
    - Inline job detail expansion (3 hr)
    - Bulk job retry with checkboxes (2 hr)
    - Profile duplicate/clone (1.5 hr)
    - Live watermark preview (3 hr)
    - Agent activity timeline (4 hr)
    - Dashboard failures widget (1.5 hr)

sprint_3:
  name: "Feature Complete"
  duration: "2 weeks"
  tasks:
    - TrayPrint auto-update (4 hr)
    - Full WebSocket real-time (3 hr)
    - TrayPrint retry from menu (2 hr)
    - Printer capabilities drill-down (3 hr)
    - Prometheus metrics (4 hr)
    - Structured JSON logging (2 hr)
    - Alert notifications (4 hr)

sprint_4:
  name: "Security & Performance"
  duration: "2 weeks"
  tasks:
    - IP whitelist in agent auth (2 hr)
    - Per-key rate limiting (2 hr)
    - Server-side pagination (3 hr)
    - Force TLS in production (1 hr)
    - Complete MSI installer (8 hr)