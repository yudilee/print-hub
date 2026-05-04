# TrayPrint Agent — Comprehensive UI/UX & Printer Control Analysis

---

## 1. TrayPrint UI/UX Analysis

### 1.1 System Tray Menu ([`app.py`](../trayprint/app.py:91))

The system tray menu is dynamically rebuilt each time it opens via `update_menu()`:

| Menu Item | Behaviour | UX Notes |
|-----------|-----------|----------|
| **Header** | `"Trayprint v3.0.0 - Port 49211"` — disabled label | Good for status awareness |
| **Hub Status** | `"Hub: Connected" / "Hub: Offline"` (from `get_hub_status()` — a static global) | Only updated by sync loop; may be stale up to sync interval |
| **Printers Count** | `"Printers: {n} found"` | Good quick reference |
| **Settings** | Opens [`ui_settings.py`](../trayprint/ui_settings.py:18) dialog | Modal; blocks interaction until closed |
| **Recent Jobs** | Submenu with last 10 jobs; shows ✓/✗/… icon, printer name, type, time | Read-only list; no way to retry or view details from tray |
| **View Logs** | Opens log file in OS default editor | Useful for troubleshooting |
| **Auto-start on Login** | Toggle checkbox with immediate feedback via `showMessage()` | Good; but no visual indicator after toggle (banner disappears quickly) |
| **Restart App** | `os.execl()` — hard process replacement | Works but no confirmation dialog |
| **Exit** | `os._exit(0)` | No confirmation dialog |

**Notification System:**
- Uses `QSystemTrayIcon.showMessage()` via `show_notification()` and `QTimer.singleShot(0, ...)` to ensure main-thread execution
- Three notification types: auto-start toggle, print success, print failure
- **Missing:** Notification disappears automatically (OS-dependent timeout). No user action possible on notification (e.g., "View details"). No error detail in failure notification — just "Failed on {printer}: {error_msg}" which may be truncated.

**Missing from Tray:**
- No visual "currently printing" indicator (spinner, progress bar)
- No way to pause/resume a job
- No way to cancel a pending job
- No printer status (idle/printing/error) at a glance
- No option to refresh printers list manually
- No keyboard shortcuts

### 1.2 Settings Dialog ([`ui_settings.py`](../trayprint/ui_settings.py:18))

Four tabs — **Connection**, **General**, **Printers**, **Recent Jobs**.

#### Tab 1: Connection
| Field | Widget | Notes |
|-------|--------|-------|
| Hub URL | `QLineEdit` with placeholder | No URL validation |
| Agent Key | `QLineEdit` with `PasswordEchoOnEdit` | Good for security |
| **Test Connection** | Button | Hits `/api/print-hub/profiles` on hub; validates auth |
| Status Label | Color-coded text | Green=connected, Red=offline/auth failed, Amber=unknown |

**Test Connection Flow:**
1. Validates non-empty URL + key
2. Sends `GET {hub_url}/api/print-hub/profiles` with Bearer token
3. Handles 200 (success), 401 (auth failed), other errors, network exceptions
4. Updates `server._hub_last_status` global
5. Shows count of profiles found

**Observation:** Test connection only validates auth — it doesn't verify printer compatibility or test actual print capability.

#### Tab 2: General (Local Service Settings)
| Field | Range | Notes |
|-------|-------|-------|
| Auto-start | Checkbox | Toggles immediately, no save needed |
| Local API Port | 1024–65535 | Requires restart |
| Sync Interval | 5–3600 sec (suffix " sec") | Controls hub polling frequency |
| Max Retries | 0–10 | For failed print jobs |
| Retry Delay | 5–3600 sec (suffix " sec") | Wait between retry attempts |

#### Tab 3: Printers
- Label: `"Found {n} printers on this system."`
- Read-only table listing printer names
- Auto-refreshes every 5 seconds
- **No details shown** — no status, no default indicator, no paper trays, no capabilities
- **No printer configuration possible**

#### Tab 4: Recent Jobs
- 5-column table: Time, Printer, Type, Status, Preview/Error
- Color-coded status: green (success), red (failed)
- Auto-refreshes every 5 seconds
- **No retry button** — retry only available via REST API
- **No filtering** — shows all jobs mixed together

#### General UI Issues:
- **No printer-specific settings** in the entire dialog — users cannot configure trays, color, duplex, quality, etc. from the UI
- Settings changes always require a full app restart (even non-critical ones)
- The dialog is modal and blocks the tray
- No "Apply without restart" option for settings like sync interval
- Printer list shows names only — no status indicators
- Jobs tab is read-only: can't retry, can't view details, can't clear history

### 1.3 Web-Based Settings UI ([`settings.html`](http://_home_yudi_dev_trayprint_templates_settings.html))

Served by Flask at `/settings` (rendered from config + printer data).

| Section | Content |
|---------|---------|
| Hub Connection | Form fields for hub_url, agent_key, port, sync_interval, max_retries, retry_delay |
| Connection Status | Top-right badge colored green/red/amber |
| Test Connection | AJAX POST to `/settings/test` |
| Printers | Table with Name, Default badge, Status badge |
| Recent Jobs | Table with ID, Printer, Type, Status, Time |

**Missing from Web UI (same gaps as PySide dialog):**
- No printer control settings (tray, color, quality, duplex, etc.)
- No way to preview a print job
- No job retry capability
- No printer capability display

---

## 2. Printer Control Capabilities

### 2.1 Existing [`PrintProfile`](../print-hub/app/Models/PrintProfile.php) Model Fields

Current database columns on `print_profiles` (from migration):

| Field | Type | Default | Purpose |
|-------|------|---------|---------|
| `name` | string | — | Profile name (virtual queue name) |
| `description` | text | — | Human-readable description |
| `print_agent_id` | FK | — | Links to which agent runs this queue |
| `branch_id` | FK | — | Tenant scoping |
| `paper_size` | string | — | Standard paper size name (A4, Letter, etc.) |
| `orientation` | string | — | `portrait` or `landscape` |
| `copies` | int | — | Number of copies |
| `duplex` | string | — | `none`, `two-sided-long`, `two-sided-short` |
| `default_printer` | string | — | Target printer name |
| `extra_options` | JSON | — | Free-form catch-all for advanced options |
| `is_custom` | boolean | — | Whether paper size is custom |
| `custom_width` | float | — | Custom paper width in mm |
| `custom_height` | float | — | Custom paper height in mm |
| `margin_top/bottom/left/right` | float | — | Page margins in mm |
| **`tray_source`** | string | nullable | Tray/bin selection: `auto`, `tray1`, `tray2`, `manual`, `envelope` |
| **`color_mode`** | string | `'color'` | `color` or `monochrome` |
| **`print_quality`** | string | `'normal'` | `draft`, `normal`, `high` |
| **`scaling_percentage`** | integer | `100` | Scale 1–400% |
| **`media_type`** | string | nullable | `plain`, `glossy`, `envelope`, `label`, `continuous_feed` |
| **`collate`** | boolean | `true` | Collate multiple copies |
| **`reverse_order`** | boolean | `false` | Print last page first |

### 2.2 How the Agent Receives & Applies Settings

The flow is:

1. **Print Hub API** → [`PrintHubController.php`](../print-hub/app/Http/Controllers/Api/PrintHubController.php) serves profiles via `/api/print-hub/profiles`
2. **TrayPrint Agent** polls this endpoint via `start_hub_sync()` in [`server.py:213`](../trayprint/server.py:213)
3. Profiles are saved to `config.json` as `{ "profiles": { "queue_name": { "printer": "...", "options": {...} } } }`
4. When a job arrives from hub queue, `_process_job()` at [`server.py:299`](../trayprint/server.py:299) extracts the `options` dict and passes it to:
   - `printer.print_pdf()` or `printer.print_raw()`
5. For local API jobs (`POST /print` at [`server.py:537`](../trayprint/server.py:537)), options are merged: `profile.options` (base) ← `request.options` (override)

**Critical Gap:** The options that flow from Hub→Agent are the `extra_options` JSON field from PrintProfile. The new fields (`tray_source`, `color_mode`, `print_quality`, etc.) are stored in the database but the agent **does not yet read them** — only `extra_options` is consumed. The hub-to-agent API contract needs updating to include these new fields.

### 2.3 Windows DEVMODE Properties Supported by [`printer.py`](../trayprint/printer.py)

The printer engine supports the following DEVMODE fields:

| DEVMODE Field | Options in `printer.py` | Code Location |
|---------------|------------------------|---------------|
| `dmPaperSize` | Paper ID (from `_find_windows_paper_name`), `DMPAPER_USER` (256) for custom | `_create_devmode_for_options()` line 529 |
| `dmPaperWidth` | Custom width in 0.1mm units | line 573 |
| `dmPaperLength` | Custom length in 0.1mm units | line 574 |
| `dmOrientation` | `DMORIENT_PORTRAIT` (1) or `DMORIENT_LANDSCAPE` (2) | lines 596–603 |
| `dmFields` | Bitmask flags: `DM_PAPERSIZE`, `DM_PAPERWIDTH`, `DM_PAPERLENGTH`, `DM_ORIENTATION` | throughout |

**Paper Size Matching Strategy** ([`_find_windows_paper_name()`](../trayprint/printer.py:184)):
1. `DeviceCapabilities(DC_PAPERSIZE)` — gets dimensions for all paper IDs the driver supports; matches by closest dimension (within 0.5mm tolerance)
2. `EnumForms()` — fallback for drivers that return zero sizes (e.g., Epson LQ dot-matrix); cross-references by name
3. Swapped orientation check (width↔height)
4. Falls back to `DMPAPER_USER` (256) if no match

**What's NOT set in DEVMODE:**
| Missing DEVMODE Field | Possible Constant | Impact |
|-----------------------|-------------------|--------|
| `dmCopies` | `DM_COPIES` | Copy count handled by SumatraPDF CLI or loop in GDI path, not DEVMODE |
| `dmDefaultSource` (tray) | `DM_DEFAULTSOURCE` | **Tray source not set at all** |
| `dmColor` (color vs mono) | `DM_COLOR` | Color mode not set |
| `dmPrintQuality` | `DM_PRINTQUALITY` | Print quality (draft/normal/high) not set |
| `dmYResolution` | `DM_YRESOLUTION` | Resolution in DPI not set |
| `dmDuplex` | `DM_DUPLEX` | Duplex handled via SumatraPDF `-print-settings` flag, not DEVMODE |
| `dmCollate` | `DM_COLLATE` | Collation not set |
| `dmMediaType` | `DM_MEDIATYPE` | Media type (paper type) not set |
| `dmNup` | `DM_NUP` | Pages-per-sheet not supported |
| `dmICMMethod` | `DM_ICMMETHOD` | Color management not set |
| `dmPosition` | `DM_POSITION` | Not set |

### 2.4 CUPS/lp Options Supported ([`_build_lp_options()`](../trayprint/printer.py:121))

| Option | CLI Flag | Condition |
|--------|----------|-----------|
| Copies | `-n N` | If > 1 |
| Paper (standard) | `-o media={name}` | If `paper_size` set |
| Paper (custom) | `-o media=Custom.{W}x{H}mm` | If `paper_width_mm` + `paper_height_mm` |
| Orientation | `-o orientation-requested=3|4` | Always set (3=portrait, 4=landscape) |
| Margins | `-o page-top/bottom/left/right={pts}` | If any margin > 0 (mm→pts conversion) |
| Fit to page | `-o fit-to-page` | If fit_to_page or margins set |
| Duplex | `-o sides=two-sided-long-edge|two-sided-short-edge` | If duplex is set |
| Page range | `-o page-ranges={range}` | If page_range set |

**CUPS Gaps:**
| Missing CUPS Option | CUPS Flag | Impact |
|--------------------|-----------|--------|
| Tray source | `-o InputSlot={tray}` | Can't specify paper tray |
| Color/mono | `-o ColorModel={Gray|RGB|CMYK}` | Can't force mono |
| Print quality | `-o print-quality={3|4|5}` (draft/normal/high) | Can't set resolution |
| Media type | `-o MediaType={name}` | Can't specify media |
| Collate | `-o Collate=True` | Can't collate |
| Scaling | `-o scaling={N}` | Can't scale output |

### 2.5 SumatraPDF Options Supported ([`_build_sumatra_options()`](../trayprint/printer.py:380))

| Option | Format | Notes |
|--------|--------|-------|
| Copies | `{N}x` prefix | e.g., `3x` |
| Orientation | `portrait` or `landscape` | Always set |
| Paper | `paper={name}` | Includes name mapping (Half Letter→Statement, F4→Folio) |
| Duplex | `duplex` | Only if `two-sided-*` |
| Page range | Raw string | e.g., `1-5` |
| Fit to page | `fit` | If `fit_to_page` is truthy |

**SumatraPDF Gaps:**
- No tray/input slot support
- No color mode support
- No quality/resolution support
- No collate support
- No scaling support
- No media type support

### 2.6 Feature Coverage Matrix

| Feature | DB Field Exists | Agent Reads It | DEVMODE Set | CUPS/lp Set | Sumatra Set |
|---------|:---:|:---:|:---:|:---:|:---:|
| **Paper size** (standard) | ✅ | ✅ (extra_options) | ✅ | ✅ | ✅ |
| **Paper size** (custom WxH) | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Orientation** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Copies** | ✅ | ✅ | ❌ (in code) | ✅ | ✅ |
| **Margins** | ✅ | ✅ | ❌ (app-level) | ✅ | ❌ |
| **Duplex** | ✅ | ✅ | ❌ | ✅ | ✅ |
| **Page range** | ❌ | ✅ (extra_options) | ❌ | ✅ | ✅ |
| **Fit to page** | ❌ | ✅ (extra_options) | ❌ | ✅ | ✅ |
| **Tray source** | ✅ (`tray_source`) | ❌ | ❌ | ❌ | ❌ |
| **Color mode** | ✅ (`color_mode`) | ❌ | ❌ | ❌ | ❌ |
| **Print quality** | ✅ (`print_quality`) | ❌ | ❌ | ❌ | ❌ |
| **Scaling** | ✅ (`scaling_percentage`) | ❌ | ❌ | ❌ | ❌ |
| **Media type** | ✅ (`media_type`) | ❌ | ❌ | ❌ | ❌ |
| **Collate** | ✅ (`collate`) | ❌ | ❌ | ❌ | ❌ |
| **Reverse order** | ✅ (`reverse_order`) | ❌ | ❌ | ❌ | ❌ |
| **Stapling** | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Hole punch** | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Booklet** | ❌ | ❌ | ❌ | ❌ | ❌ |

### 2.7 Summary of Gaps & Recommendations

#### Critical Gaps (Agent→Hub integration):

1. **New PrintProfile fields not propagated to agent** — The migration adds `tray_source`, `color_mode`, `print_quality`, `scaling_percentage`, `media_type`, `collate`, `reverse_order` to the database, but the agent only consumes `extra_options` (JSON). The `/api/print-hub/profiles` response needs to include these new fields, and the agent's `_process_job()` / `print_raw()` / `print_pdf()` need to read them.

2. **DEVMODE lacks printer control fields** — The agent's Windows GDI path sets paper size and orientation via DEVMODE but does NOT set:
   - `dmDefaultSource` (tray) using `DM_DEFAULTSOURCE`
   - `dmColor` (color/mono) using `DM_COLOR`
   - `dmPrintQuality` using `DM_PRINTQUALITY`
   - `dmCollate` using `DM_COLLATE`
   - `dmMediaType` using `DM_MEDIATYPE`
   - `dmCopies` using `DM_COPIES` (copy loop is in Python code instead)

3. **CUPS path lacks equivalent printer control options** — The `_build_lp_options()` function needs:
   - `-o InputSlot={tray}` for tray source
   - `-o ColorModel={Gray|RGB}` for color mode
   - `-o print-quality={3|4|5}` for quality
   - `-o scaling={N}` for scaling
   - `-o MediaType={type}` for media type
   - `-o Collate=True` for collation
   - `-o page-set=odd|even` or `OutputOrder=Reverse` for reverse order

4. **No stapling or hole punch support at any layer** — These require DEVMODE fields like `dmStaple` (Windows) or CUPS `-o StapleLocation` and `-o HolePunch`. Not in the database, not in the model, not in the agent.

#### UI/usability gaps:

5. **Settings dialog has no printer control** — Users cannot configure tray source, color mode, quality, collation, media type, or scaling from the desktop UI. All printer control is hidden in profile options synced from the hub's web dashboard.

6. **No print job preview** — Neither the PySide dialog nor the web UI provides a way to preview what will be printed before sending the job.

7. **No printer capability discovery in UI** — The UI lists printer names but doesn't query or display what each printer supports (trays, paper sizes, duplex, color, etc.).

8. **No per-queue configuration in the desktop app** — Profiles/queues are displayed as names only; the desktop has no UI to inspect or modify queue-specific printer options.
