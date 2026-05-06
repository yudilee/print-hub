# Template Designer Improvement Plan — Crystal Reports-Level Feature Set

## Current State Assessment

The existing WYSIWYG template designer at [`resources/views/admin/templates/designer.blade.php`](../resources/views/admin/templates/designer.blade.php) is already a sophisticated drag-and-drop tool with:

- Canvas rendering at 4x scale with 8 resize handles
- Snap-to-grid (2mm), smart alignment guides (2mm proximity)
- Rubber-band selection, multi-select, grouping
- Undo/redo stack (60 levels)
- Layers panel with lock/visibility toggles
- Zoom (20%-300%), minimap, rulers
- Context menu (duplicate, bring/send to front, lock, visibility)
- Property inspector with formatting (date, number, currency, terbilang)
- Schema integration with field type badges and validation
- Live data preview mode with [`formatValueJS()`](../resources/views/admin/templates/designer.blade.php:~1150)
- Table column management with computed column expressions
- Background image overlay for trace design
- Export/import JSON template files
- Paper presets for common sizes
- Keyboard shortcuts (Ctrl+Z/Y/S, arrows, Delete)
- Coordinate tooltip during drag/resize

The PDF generation engine [`ContinuousFormEngine.php`](../app/Services/ContinuousFormEngine.php) supports:
- Custom paper sizes with margins
- Multipage table rendering with auto page breaks
- Computed columns via Symfony ExpressionLanguage
- Watermarking (rotation, opacity, tiling, multiple positions)
- Eco mode (N-up, grayscale, duplex, image removal)
- Schema-based formatting

However, compared to Crystal Reports or modern enterprise report designers, significant gaps exist in data manipulation, visualization, layout structure, and expression capabilities.

---

## 1. Report Structure & Page Sections

### 1.1 Section-Based Layout Designer

**Current:** All elements are absolute-positioned on a single canvas area. No separation of headers, body, or footers.

**Proposed:** Replace the flat canvas with a structured section-based layout:

```
┌─────────────────────────────┐
│  Page Header                │ ← Repeats on every page
├─────────────────────────────┤
│  Report Header              │ ← Appears once at start
├─────────────────────────────┤
│  Group Header (by field)    │ ← Appears per group (optional)
├─────────────────────────────┤
│  Detail (Body)              │ ← Main data rows
├─────────────────────────────┤
│  Group Footer (by field)    │ ← Appears per group (optional)
├─────────────────────────────┤
│  Report Footer              │ ← Appears once at end
├─────────────────────────────┤
│  Page Footer                │ ← Repeats on every page
└─────────────────────────────┘
```

Each section is a collapsible/resizable band with its own properties:
- Visibility toggle (show/hide per section)
- Suppress if blank (hide section when no data)
- Keep together (prevent page break inside)
- New page before/after (force page breaks)
- Background color / border
- Min height override

**Files to modify:**
- [`designer.blade.php`](../resources/views/admin/templates/designer.blade.php) — Major UI overhaul to section-based canvas
- [`ContinuousFormEngine.php`](../app/Services/ContinuousFormEngine.php) — Render sections in correct order with page break logic
- Migration — Add `section` field to element JSON structure

### 1.2 Multi-Column Report Layout

**Proposed:** Support newspaper-style multi-column layouts (2-column, 3-column) where detail rows flow left-to-right, top-to-bottom within columns.

- Column count and spacing configurable per template
- Column breaks (force next column)
- Applies only to detail/body section

---

## 2. Data Manipulation & Binding

### 2.1 Sorting, Grouping & Filtering (SGF) Panel

**Current:** No data-level sorting, grouping, or filtering. Data is rendered as-is from the API response.

**Proposed:** Add a dedicated SGF panel (like Crystal Reports' "Group Expert"):

**Sorting:**
- Add/remove sort fields with direction (ascending/descending)
- Multi-level sorting (sort by department, then name)
- Custom sort order (enum-based)

**Grouping:**
- Group by one or more fields
- Group header/footer sections (see 1.1)
- Group options:
  - Keep group together
  - Repeat group header on each page
  - Start each group on a new page
  - Group sort (in specified order, or by summary)
- Group hierarchy (drill-down nesting)

**Filtering:**
- Record selection formula builder (visual)
- Pre-filter data before rendering
- Filter by field values, date ranges, computed expressions
- Parameterized filters (prompt user at runtime)

**Implementation approach:**
```json
// In template JSON
{
  "dataOptions": {
    "sortFields": [
      { "field": "department", "direction": "asc" },
      { "field": "employee.name", "direction": "asc" }
    ],
    "groupFields": [
      { "field": "department", "sortDirection": "asc", "keepTogether": true }
    ],
    "filterExpression": "totalAmount > 1000 AND status != 'cancelled'",
    "parameters": ["startDate", "endDate", "departmentId"]
  }
}
```

**Files to modify:**
- [`designer.blade.php`](../resources/views/admin/templates/designer.blade.php) — Add SGF panel UI
- [`ContinuousFormEngine.php`](../app/Services/ContinuousFormEngine.php) — Implement sorting, grouping, filtering logic before rendering
- [`PrintTemplate`](../app/Models/PrintTemplate.php) — Add `data_options` JSON column

### 2.2 Runtime Parameters & Prompts

**Current:** No support for user-input parameters when generating a report.

**Proposed:** Add parameter prompting system:

- Define parameters at design time (name, type, label, default value)
- Supported types: string, number, date, date range, boolean, dropdown (from schema or static list), multi-select
- Parameters can be used in:
  - Filter expressions (record selection)
  - Element visibility conditions
  - Computed column expressions
  - Watermark text
  - Labels and titles
- At preview/print time, show a parameter input dialog before rendering
- Cascading parameters (select department → filters employees)

**Files to modify:**
- [`designer.blade.php`](../resources/views/admin/templates/designer.blade.php) — Parameter definition panel
- [`TemplateController.php`](../app/Http/Controllers/Admin/TemplateController.php) — Parameter input dialog in preview/test-print
- [`ContinuousFormEngine.php`](../app/Services/ContinuousFormEngine.php) — Accept parameters and inject into expressions
- [`ClientAppController.php`](../app/Http/Controllers/Api/ClientAppController.php) — Pass parameters via API

### 2.3 Master-Detail / Subreports

**Current:** Tables are flat. No way to render parent-child data relationships.

**Proposed:** Add subreport support:

- Define subreport within the same template (nested template) or as a reference to another template
- Subreport binds to a field from the parent (e.g., `order_id`)
- Subreport executes with the parent field value as parameter
- Subreport renders as an element in the parent's detail section
- Supports: invoice with line items, order with shipments, customer with orders

**Implementation:**
```json
{
  "type": "subreport",
  "templateId": 5,  // or inline
  "linkField": "order_id",
  "parameterMapping": { "parentOrderId": "order_id" },
  "x": 10, "y": 0,
  "width": 180, "minHeight": 20
}
```

**Files to modify:**
- [`designer.blade.php`](../resources/views/admin/templates/designer.blade.php) — Subreport element type, inline editing
- [`ContinuousFormEngine.php`](../app/Services/ContinuousFormEngine.php) — Recursive rendering, pass filter context
- [`PrintTemplate`](../app/Models/PrintTemplate.php) — Add `is_subreport` flag

---

## 3. Expression & Formula Engine

### 3.1 Visual Formula/Expression Editor

**Current:** Computed columns use raw ExpressionLanguage syntax with no visual editor.

**Proposed:** Add a dedicated formula editor with:

- Syntax highlighting for expressions
- Field picker (drag field name into expression)
- Function browser with categories:
  - **Math**: Sum, Average, Count, Min, Max, Round, Abs, Ceil, Floor, Mod
  - **String**: Upper, Lower, Trim, Substring, Replace, Concatenate, Length
  - **Date**: Now, DateDiff, DateAdd, DatePart, FormatDate, Day, Month, Year
  - **Logical**: If-Then-Else, Switch, Choose, IsNull, Previous, Next
  - **Aggregate**: Sum(field, group), Count, Average, Max, Min (per group)
  - **Running**: RunningSum(field), RunningCount
  - **Type conversion**: ToString, ToNumber, ToDate, Format
- Expression validation with error highlighting
- Live preview of computed value with sample data
- Formula library (save reusable formulas)

**Implementation approach:**
- Build on existing Symfony ExpressionLanguage in [`ContinuousFormEngine.php`](../app/Services/ContinuousFormEngine.php)
- Register custom functions as ExpressionLanguage extensions
- Frontend formula editor as a modal/code editor component

### 3.2 Conditional Formatting (Highlighting Expert)

**Current:** Formatting (`format_type`) is static per element. No conditional rules.

**Proposed:** Add conditional formatting rules per element:

```
If [field] [operator] [value] then apply [format]
```

- Multiple rules evaluated in order (first match wins)
- Operators: equals, not equals, greater than, less than, between, contains, starts with, is null
- Format actions: font color, background color, bold/italic/underline, font size, border
- Examples:
  - If `balance > 1000000` then font color = red, bold
  - If `status == 'overdue'` then background = #FFEEEE
  - If `amount < 0` then font color = red

**Implementation:**
```json
{
  "conditionalFormats": [
    {
      "field": "balance",
      "operator": ">",
      "value": "1000000",
      "style": { "color": "#FF0000", "bold": true }
    }
  ]
}
```

**Files to modify:**
- [`designer.blade.php`](../resources/views/admin/templates/designer.blade.php) — Conditional format editor in property inspector
- [`ContinuousFormEngine.php`](../app/Services/ContinuousFormEngine.php) — Apply conditional formats during rendering

---

## 4. Visualization Elements

### 4.1 Charting Engine

**Current:** No chart/graph support whatsoever.

**Proposed:** Integrate a lightweight chart rendering library for PDF output:

**Chart types:**
- Bar/Column (vertical, horizontal, stacked, grouped)
- Line (single, multiple series, with markers)
- Pie/Doughnut (2D, 3D effect, exploded)
- Area (stacked, percentage)
- Scatter/Bubble
- Radar/Spider

**Chart data binding:**
- Data source: from main dataset or separate query
- Category field (X-axis)
- Value fields (Y-axis, multiple series)
- Group-based aggregation (Sum per group, Count per group)
- Chart options: title, legend position, axis labels, gridlines, color palette

**Implementation notes:**
- Since FPDF has no native charting, use one of:
  - Render SVG charts on the backend, convert to PNG, embed as images
  - Use a PHP charting library (CpChart, PHPLotChart, or similar)
  - For frontend preview, use Chart.js or similar JS library
- Chart as an element type in the designer with drag-resize

### 4.2 Barcode & QR Code Elements

**Current:** No barcode/QR code element types.

**Proposed:** Add barcode/QR code as native element types:

**Supported symbologies:**
- 1D: Code 128, Code 39, EAN-13, EAN-8, UPC-A, UPC-E, ITF-14
- 2D: QR Code, Data Matrix, PDF417
- GS1-128, GS1 DataMatrix for supply chain

**Properties:**
- Data source: field binding or static text
- Symbology selector
- Human-readable text (show/hide below barcode)
- Module size (narrow bar width)
- Height (for 1D barcodes)
- Error correction level (QR: L/M/Q/H)
- Quiet zone margin
- Color (default black)

**Implementation:**
- Use [`picqer/php-barcode-generator`](https://github.com/picqer/php-barcode-generator) (already in many Laravel projects via Composer) or similar
- For QR, use [`simplesoftwareio/simple-qrcode`](https://github.com/SimpleSoftwareIO/simple-qrcode) (BaconQrCode wrapper)
- Render barcode as embedded image in FPDF
- Frontend: preview canvas element renders as SVG/Canvas

### 4.3 Hyperlinks & Drill-Down

**Current:** No clickable links in generated PDF.

**Proposed:** Add hyperlink support to elements:

- **URL link** — Click opens URL in browser
  - Static URL or dynamic (expression-based)
  - `https://example.com/invoice/{invoice_id}`
- **Email link** — Click opens email client
  - `mailto:support@example.com?subject=Order {order_no}`
- **Internal drill-down** — In preview mode, click navigates to detail
  - Requires grouping/subreport infrastructure
- **Page navigation** — Link to specific page within document (table of contents)

**Implementation:**
- FPDF has `SetLink()` and `AddLink()` for internal links
- External links via `write()` with URL
- Frontend: link tooltip, click behavior in preview mode

---

## 5. Designer UX Enhancements

### 5.1 Cross-Tab / Pivot Table

**Current:** Only simple columnar tables.

**Proposed:** Add cross-tab (pivot table) element:

- **Rows:** One or more fields for row grouping
- **Columns:** One field for column grouping
- **Summarized Field:** The value to aggregate
- **Summary Operation:** Sum, Count, Average, Min, Max
- **Layout:** Row grand totals, column grand totals, percentage of total
- **Example:**
  ```
           | Q1    | Q2    | Q3    | Q4    | Total
  ---------+-------+-------+-------+-------+-------
  North    | 100   | 150   | 120   | 180   | 550
  South    | 80    | 90    | 110   | 130   | 410
  ---------+-------+-------+-------+-------+-------
  Total    | 180   | 240   | 230   | 310   | 960
  ```

### 5.2 Running Total Fields

**Current:** No running/accumulating totals.

**Proposed:** Add running total field type:

- **Type:** Sum, Count, Average, Max, Min, Standard Deviation
- **Reset:** Never, on change of group, on each page
- **Field:** The field to evaluate
- **Evaluation:** On each record, use value from evaluated field
- **Example:** Running invoice total that resets per customer group

### 5.3 Formula/Calculated Fields

**Current:** Computed columns exist only within tables.

**Proposed:** Add formula fields as first-class elements:

- Define a formula field in the designer (like Crystal Reports' Formula Fields)
- Formula uses expression syntax with all registered functions
- Formula field can be placed anywhere like a regular field element
- Reusable across multiple elements (define once, use in many places)
- Examples: `TotalWithTax = Subtotal * (1 + TaxRate/100)`, `FullName = FirstName + ' ' + LastName`

### 5.4 Template Version History & Compare

**Current:** No versioning in the designer.

**Proposed:** Add version control directly in the UI:

- Auto-save versions on each save (with SNAPSHOT_INTERVAL)
- Manual version snapshots with labels ("Pre-release v2", "Live")
- Version diff viewer (JSON diff or visual side-by-side)
- Rollback to any previous version
- Version comments/changelog
- Migration: Add `template_versions` table with full JSON snapshot

### 5.5 Advanced Element Properties

**Proposed additions to property inspector:**

- **Rotation** — Rotate any element by degrees (0, 90, 180, 270, custom)
- **Opacity** — Element opacity slider (0-100%)
- **Border** — Full border control per side (color, width, style: solid/dashed/dotted)
- **Padding** — Inner padding for text elements
- **Can Grow** — Element expands vertically to fit content (vs. fixed height)
- **Suppress if Duplicate** — Hide element if value equals previous row
- **Tooltip** — Hover text (for interactive preview)
- **Print when** — Condition expression for showing/hiding per row
- **Keep Together** — Prevent page break within element

### 5.6 Layout & Alignment Tools

**Proposed additions:**

- **Auto-arrange** — Automatically space selected elements evenly (horizontal/vertical)
- **Distribute** — Distribute selected elements evenly across available space
- **Nudge** — Arrow key nudge distance configurable (1px, 2mm, snap)
- **Snap to other elements** — Snap edges to edges of sibling elements
- **Size to Content** — Auto-resize element to fit its content
- **Same Width/Height** — Make selected elements same width/height
- **Tab Order** — For input/report parameter fields (future interactive PDF)
- **Guide lines** — User-defined guide lines (persistent, not just alignment hints)
- **Ruler context menu** — Add/remove guide lines from ruler

### 5.7 Element Templates & Library

**Proposed:**

- **Element templates** — Save a styled element (e.g., a formatted header label) to reuse
- **Snippets** — Save groups of elements as reusable snippets
- **Company branding kit** — Pre-defined colors, fonts, logo placement
- **Import snippet** — Import from JSON file
- **Drag from library** — Drag pre-built elements from a library panel

---

## 6. PDF Engine Upgrades

### 6.1 Upgrade from FPDF to mPDF or TCPDF

**Current:** Uses `setasign/fpdf` (FPDF) which lacks:
- Unicode/UTF-8 support (no CJK characters, accented characters)
- Embedded font support
- HTML/CSS rendering
- SVG support
- PDF/A compliance
- Advanced typography

**Proposed:** Migrate to [`mpdf/mpdf`](https://github.com/mpdf/mpdf) or [`tecnickcom/tcpdf`](https://github.com/tecnickcom/tcpdf):

**mPDF advantages:**
- Full UTF-8/Unicode support with font subsetting
- CSS 2.1 styling for rich text formatting
- Table of contents generation
- Bookmarks/outline
- Watermarks (native)
- Barcode support (QR, Code 128, etc.)
- PDF/A-1b compliance
- Active form fields (text input, checkboxes)
- Annotation support
- Font embedding (any .ttf/.otf font)
- Much larger community and active maintenance

**Migration strategy:**
1. Wrap current [`ContinuousFormEngine.php`](../app/Services/ContinuousFormEngine.php) behind an interface
2. Create `MpdfContinuousFormEngine.php` as alternative implementation
3. Add config option to switch between engines
4. Reimplement element rendering methods for mPDF
5. Leverage mPDF's WriteHTML() for table rendering with CSS
6. Gradual migration: new templates use mPDF, old ones can use FPDF for backward compatibility

### 6.2 Custom Font Management

**Current:** FPDF only uses built-in Arial (via AddFont).

**Proposed:** Add font management system:

- Upload .ttf/.otf font files via admin panel
- Font preview (character map, sample text)
- Font embedding options (all, subset, none)
- Font pairing suggestions
- Designer font dropdown populated from uploaded fonts
- Font subsetting to reduce PDF file size (automatic with mPDF)
- Niche fonts: monospace (for code listings), handwriting (for signatures)

### 6.3 Rich Text Formatting

**Current:** Elements render plain text only.

**Proposed:** Add rich text support:

- **Inline styling:** Bold, italic, underline, strikethrough within a single text element
- **Mixed fonts:** Different fonts/sizes within same element
- **Superscript/subscript:** For footnotes, formulas, chemical notation
- **Bullet lists and numbered lists**
- **Paragraph alignment:** Left, center, right, justify (per paragraph)
- **Tab stops** — For aligning text within an element

**Implementation:**
- Use a lightweight markup (Markdown subset) or HTML subset
- Frontend: Rich text toolbar for label elements
- Backend: Parse and render with mPDF's WriteHTML()

---

## 7. Preview & Testing

### 7.1 Multi-Page Preview with Navigation

**Current:** Preview generates full PDF, user downloads to view.

**Proposed:** Add in-browser paginated preview:

- Page-by-page navigation (previous/next/jump to page)
- Page thumbnail strip
- Zoom in/out on preview (fit width, fit page, custom zoom)
- Page count indicator
- "Print to PDF" button from preview
- "Generate PDF" button to download
- Loading state with progress indicator for large reports

### 7.2 Sample Data Editor

**Current:** Uses job history for sample data. No way to edit test data.

**Proposed:** Add inline sample data editor:

- Table-based editor to add/edit/remove rows of test data
- Import sample data from CSV/JSON
- Generate random test data (based on schema field types)
- Save multiple sample datasets per template
- "Fill with realistic data" using Faker

### 7.3 Report Debug Mode

**Proposed:** Add debug/development mode that shows:

- Element bounding boxes (outlines + coordinates)
- Section boundaries with labels
- Data field values rendered inline (fieldname: value)
- Unbound field warnings (red highlight for missing fields)
- Expression evaluation errors with line numbers
- Page break preview indicators
- Overlapping element warnings

---

## 8. Performance & Scalability

### 8.1 Lazy Element Rendering

**Current:** All elements on canvas are rendered/measured.

**Proposed:** Virtual rendering for the designer canvas:

- Only render elements visible in the current viewport
- Use canvas clipping for off-screen elements
- Deferred measurement for complex tables
- Skeleton loading for image elements

### 8.2 PDF Generation Queue for Large Reports

**Current:** PDF generation is synchronous in the request.

**Proposed:** For reports with large datasets:

- Offload PDF generation to a queue job
- Show progress via WebSocket/broadcasting
- Notify user when PDF is ready for download
- Streaming PDF generation (send headers, flush partial content)
- Progress indicator: "Processing page 47 of 312..."

---

## 9. Schema & Integration

### 9.1 Live Schema Browser

**Current:** Schema fields shown in a static list in the left panel.

**Proposed:** Enhanced schema browser:

- Tree view for nested/related schemas (expandable)
- Search/filter fields by name
- Field type icons (string, number, date, boolean, object, array)
- Field description tooltip
- "Used in template" indicator (which elements reference this field)
- Drag a schema field onto canvas → automatically creates field element
- Drag a table schema → automatically creates table element with columns
- Schema version diff (show what changed between versions)

### 9.2 Multiple Data Sources

**Current:** Single data source per template (the data passed at print time).

**Proposed:** Multiple data sources per report:

- Primary dataset (main detail rows)
- Secondary datasets (lookup tables, reference data)
- Cross-dataset lookups (VLOOKUP-like: get value from another dataset by key)
- SQL-like joins defined in designer (left join dataset2 on dataset1.dept_id = dataset2.id)
- Dataset alias system for expressions

### 9.3 Database/API Query Builder

**Proposed:** Visual query builder for data source:

- Connect to database or API endpoint directly
- Visual query builder (drag tables, select fields, add conditions)
- Preview query results in designer
- Parameterize queries with report parameters
- Caching configuration (cache query results for N minutes)
- Supports: MySQL, PostgreSQL, SQLite, REST API, JSON files

---

## 10. Export & Output

### 10.1 Multiple Export Formats

**Current:** PDF only.

**Proposed:** Support additional export formats:

- **PDF** (existing, enhanced)
- **Excel (.xlsx)** — With formatting, merged cells, column widths
- **CSV** — Raw data export
- **HTML** — Web-responsive report view
- **Word (.docx)** — For mail merge-style documents
- **Image (PNG/JPEG)** — Single page or per-page images
- **ZPL** — For Zebra label printers
- **Print directly** — Send to printer without saving

### 10.2 Batch/Mass Print

**Current:** Batch print via API accepts array of data sets.

**Proposed:** Enhanced batch processing:

- Visual batch job builder in admin panel
- Upload CSV → map columns → generate one report per row
- Merge multiple reports into single PDF (burst N-up)
- Variable data printing (VDP) — name badges, certificates, letters
- Progress tracking for large batches

---

## 11. Implementation Roadmap

### Phase 1 — Quick Wins (Week 1-2)

| Item | Effort | Impact |
|------|--------|--------|
| Custom fonts upload & management | Medium | High |
| Sample data editor in designer | Small | Medium |
| Element rotation (0/90/180/270) | Small | Medium |
| Page navigation multi-page preview | Medium | High |
| Barcode element (Code 128, QR) | Medium | High |
| Running total field type | Medium | Medium |

### Phase 2 — Core Enhancement (Week 3-4)

| Item | Effort | Impact |
|------|--------|--------|
| Section-based layout (headers/footers) | Large | Very High |
| Visual formula editor with functions | Large | Very High |
| Conditional formatting engine | Medium | High |
| SGF Panel (sort, group, filter) | Large | Very High |
| Template version history | Medium | High |

### Phase 3 — Advanced Features (Week 5-8)

| Item | Effort | Impact |
|------|--------|--------|
| mPDF migration (PDF engine upgrade) | Large | Very High |
| Charting engine integration | Large | Very High |
| Runtime parameters & prompts | Medium | High |
| Cross-tab / pivot table | Large | High |
| Rich text formatting | Medium | Medium |

### Phase 4 — Enterprise (Week 9-12)

| Item | Effort | Impact |
|------|--------|--------|
| Subreports / master-detail | Large | Very High |
| Multiple data sources & query builder | Large | Very High |
| Multiple export formats (Excel, HTML, DOCX) | Large | High |
| Database/API direct connection | Large | Very High |
| Batch/VDP printing UI | Medium | Medium |

---

## 12. Technical Considerations

### Frontend Architecture

The current designer is a single Blade file with ~1000+ lines of inline JavaScript. For the proposed enhancements, consider:

- **Short term:** Keep as Blade file but organize JS into well-defined modules/objects
- **Medium term:** Extract into Vue.js or Alpine.js components for maintainability
- **Long term:** Build as standalone SPA with REST API, embedded via iframe or route

### PDF Engine Decision

| Criteria | FPDF (current) | mPDF | TCPDF |
|----------|---------------|------|-------|
| UTF-8/Unicode | ❌ | ✅ | ✅ |
| CSS styling | ❌ | ✅ | Partial |
| Barcode native | ❌ | ✅ | ✅ |
| Font embedding | ❌ | ✅ | ✅ |
| PDF/A | ❌ | ✅ | ✅ |
| Form fields | ❌ | ✅ | ✅ |
| Performance | Fast | Medium | Medium |
| Learning curve | N/A | Moderate | Moderate |

**Recommendation:** Migrate to mPDF. It offers the best balance of features, performance, and ease of migration from FPDF. The `WriteHTML()` method alone saves significant development time for table/complex layouts.

### Backward Compatibility

- Existing templates must continue to work
- FPDF engine should remain as a fallback option
- Migration tools to convert old templates to new section-based format
- Old API endpoints continue to function with FPDF
- New features opt-in at template level

---

## 13. Crystal Reports Feature Comparison Matrix

| Feature | Current | Target | Priority |
|---------|---------|--------|----------|
| Absolute positioning | ✅ | ✅ | — |
| Drag-and-drop designer | ✅ | ✅ | — |
| Snap-to-grid | ✅ | ✅ | — |
| Multi-select / grouping | ✅ | ✅ | — |
| Undo/redo | ✅ (60 levels) | ✅ (unlimited) | Low |
| Layers panel | ✅ | ✅ | — |
| Property inspector | ✅ | ✅ | — |
| Zoom / minimap / rulers | ✅ | ✅ | — |
| Keyboard shortcuts | ✅ | ✅ | — |
| Schema integration | ✅ | ✅ | — |
| Multipage tables | ✅ | ✅ | — |
| Computed columns | ✅ (ExpressionLanguage) | ✅ (visual editor) | **P1** |
| **Page sections (header/footer)** | **❌** | ✅ | **P1** |
| **Sort / group / filter** | **❌** | ✅ | **P1** |
| **Conditional formatting** | **❌** | ✅ | **P1** |
| **Formula editor (visual)** | **❌** | ✅ | **P1** |
| **Barcode / QR code** | **❌** | ✅ | **P1** |
| **Template versioning** | **❌** | ✅ | **P1** |
| **Runtime parameters** | **❌** | ✅ | **P2** |
| **Charts / graphs** | **❌** | ✅ | **P2** |
| **Subreports** | **❌** | ✅ | **P2** |
| **Cross-tab / pivot** | **❌** | ✅ | **P2** |
| **Custom fonts** | **❌** | ✅ | **P2** |
| **Multi-page preview** | **❌** | ✅ | **P2** |
| Rich text | ❌ | ✅ | P3 |
| Unicode / UTF-8 | ❌ | ✅ | P3 |
| Hyperlinks | ❌ | ✅ | P3 |
| Running totals | ❌ | ✅ | P3 |
| Multiple data sources | ❌ | ✅ | P4 |
| Export to Excel/HTML | ❌ | ✅ | P4 |
| Database query builder | ❌ | ✅ | P4 |
| Drill-down navigation | ❌ | ✅ | P4 |
| Report alerting | ❌ | ✅ | P4 |
| Parameter cascading | ❌ | ✅ | P4 |

---

## 14. Files Requiring Changes

| File | Change Scope | Impact |
|------|-------------|--------|
| [`resources/views/admin/templates/designer.blade.php`](../resources/views/admin/templates/designer.blade.php) | Major — section-based canvas, new element types, panels | Very High |
| [`app/Services/ContinuousFormEngine.php`](../app/Services/ContinuousFormEngine.php) | Major — SGF, conditional formatting, sections, new element rendering | Very High |
| [`app/Http/Controllers/Admin/TemplateController.php`](../app/Http/Controllers/Admin/TemplateController.php) | Medium — version management, parameter dialog, sample data | Medium |
| [`app/Models/PrintTemplate.php`](../app/Models/PrintTemplate.php) | Medium — new JSON columns for data_options, parameters, conditional_formats | Medium |
| [`app/Http/Controllers/Api/ClientAppController.php`](../app/Http/Controllers/Api/ClientAppController.php) | Medium — pass parameters, accept multiple data sources | Medium |
| New: [`app/Services/FormulaEngine.php`](../app/Services/FormulaEngine.php) | New — formula evaluation service wrapping ExpressionLanguage | New |
| New: [`app/Services/MpdfContinuousFormEngine.php`](../app/Services/MpdfContinuousFormEngine.php) | New — mPDF-based engine implementation | New |
| New migration files | New — template_versions, print_template parameters/fonts | New |
| [`composer.json`](../composer.json) | Minor — add mPDF, barcode, charting libraries | Minor |
| [`package.json`](../package.json) | Minor — add frontend charting lib if needed | Minor |
