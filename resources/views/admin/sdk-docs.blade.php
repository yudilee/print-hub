@extends('admin.layout')
@section('title', 'API & SDK Documentation')

@section('content')
<div class="page-header">
    <h1>📖 Print Hub API & Developer Documentation</h1>
    <p>Complete reference for building client applications that integrate with Print Hub's print management middleware.</p>
</div>

<div class="docs-layout">
    {{-- ===================================================================== --}}
    {{-- Sidebar Navigation --}}
    {{-- ===================================================================== --}}
    <aside class="docs-sidebar" id="docs-sidebar">
        <div class="card toc-card">
            <div class="toc-header">Contents</div>
            <nav class="toc-nav" id="toc-nav">
                <a href="#overview" class="toc-link" data-section="overview">1. Overview</a>
                <a href="#architecture" class="toc-link sub" data-section="architecture">Architecture</a>
                <a href="#getting-started" class="toc-link" data-section="getting-started">2. Getting Started</a>
                <a href="#gs-register" class="toc-link sub" data-section="gs-register">Register Client App</a>
                <a href="#gs-quickstart" class="toc-link sub" data-section="gs-quickstart">Quick Start (cURL)</a>
                <a href="#authentication" class="toc-link" data-section="authentication">3. Authentication</a>
                <a href="#endpoints" class="toc-link" data-section="endpoints">4. API Endpoints</a>
                <a href="#ep-connection" class="toc-link sub" data-section="ep-connection">Connection & Health</a>
                <a href="#ep-discovery" class="toc-link sub" data-section="ep-discovery">Discovery</a>
                <a href="#ep-templates" class="toc-link sub" data-section="ep-templates">Templates</a>
                <a href="#ep-schemas" class="toc-link sub" data-section="ep-schemas">Data Schemas</a>
                <a href="#ep-printing" class="toc-link sub" data-section="ep-printing">Printing</a>
                <a href="#ep-jobs" class="toc-link sub" data-section="ep-jobs">Job Management</a>
                <a href="#ep-documents" class="toc-link sub" data-section="ep-documents">Document Management</a>
                <a href="#ep-approvals" class="toc-link sub" data-section="ep-approvals">Approvals</a>
                <a href="#ep-connectors" class="toc-link sub" data-section="ep-connectors">Connectors</a>
                <a href="#ep-agent-system" class="toc-link sub" data-section="ep-agent-system">Agent & System</a>
                <a href="#ep-formula" class="toc-link sub" data-section="ep-formula">Formula Editor</a>
                <a href="#print-flow" class="toc-link" data-section="print-flow">5. Print Job Flow</a>
                <a href="#template-guide" class="toc-link" data-section="template-guide">6. Template Designer Guide</a>
                <a href="#webhooks" class="toc-link" data-section="webhooks">7. Webhooks</a>
                <a href="#errors" class="toc-link" data-section="errors">8. Error Reference</a>
                <a href="#sdk-php" class="toc-link" data-section="sdk-php">9. SDK — PHP</a>
                <a href="#sdk-python" class="toc-link" data-section="sdk-python">10. SDK — Python</a>
                <a href="#sdk-nodejs" class="toc-link" data-section="sdk-nodejs">11. SDK — Node.js</a>
                <a href="#openapi" class="toc-link" data-section="openapi">12. OpenAPI / Swagger</a>
                <a href="#postman" class="toc-link" data-section="postman">13. Postman Collection</a>
                <a href="#rate-limiting" class="toc-link" data-section="rate-limiting">14. Rate Limiting</a>
                <a href="#trayprint" class="toc-link" data-section="trayprint">15. TrayPrint Agent</a>
            </nav>
            <div class="toc-download">
                <a href="{{ asset('sdk/PrintHubClient.php') }}" class="btn btn-primary" style="width:100%;justify-content:center;text-decoration:none;margin-bottom:0.25rem;" download>
                    ⬇️ PHP SDK
                </a>
                <a href="{{ asset('sdk/PrintHubClient.py') }}" class="btn btn-primary" style="width:100%;justify-content:center;text-decoration:none;margin-bottom:0.25rem;" download>
                    ⬇️ Python SDK
                </a>
                <a href="{{ asset('sdk/PrintHubClient.mjs') }}" class="btn btn-primary" style="width:100%;justify-content:center;text-decoration:none;margin-bottom:0.25rem;" download>
                    ⬇️ Node.js SDK
                </a>
                <a href="{{ asset('sdk/openapi.yaml') }}" class="btn btn-secondary" style="width:100%;justify-content:center;text-decoration:none;margin-bottom:0.25rem;" download>
                    📄 OpenAPI Spec
                </a>
                <a href="{{ asset('sdk/PrintHub-Postman.json') }}" class="btn btn-secondary" style="width:100%;justify-content:center;text-decoration:none;" download>
                    📮 Postman Collection
                </a>
            </div>
        </div>
    </aside>

    {{-- ===================================================================== --}}
    {{-- Main Content --}}
    {{-- ===================================================================== --}}
    <div class="docs-content">

        {{-- ================================================================= --}}
        {{-- 1. OVERVIEW --}}
        {{-- ================================================================= --}}
        <section class="card doc-section" id="overview">
            <div class="card-header"><h2>1. Overview</h2></div>

            <p>Print Hub is a <strong>centralized print management middleware</strong> that decouples client applications from physical printers. Instead of each app needing direct printer access, they submit print jobs to Print Hub via a REST API. Print Hub routes those jobs to the correct print agent (a lightweight Windows/Linux service called <strong>TrayPrint</strong>) which prints the document on the designated printer.</p>

            <h3 id="architecture">Architecture</h3>
            <div class="arch-diagram">
                <div class="arch-node">
                    <div class="arch-box client">🖥️ Client App<br><small>Your app (POS, ERP, etc.)</small></div>
                    <div class="arch-arrow">→ REST API (JSON) →</div>
                </div>
                <div class="arch-node">
                    <div class="arch-box hub">📡 Print Hub<br><small>Laravel server</small></div>
                    <div class="arch-arrow">→ WebSocket / Polling →</div>
                </div>
                <div class="arch-node">
                    <div class="arch-box agent">🖨️ Print Agent<br><small>TrayPrint service</small></div>
                    <div class="arch-arrow">→ USB / Network →</div>
                </div>
                <div class="arch-node">
                    <div class="arch-box printer">📄 Printer</div>
                </div>
            </div>

            <h3>Authentication</h3>
            <p>Authentication uses a two-tier model:</p>
            <table>
                <thead>
                    <tr><th>Role</th><th>Auth Method</th><th>Header</th><th>Rate Limit</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="badge badge-info">Client App</span></td>
                        <td>API Key</td>
                        <td><code>X-API-Key</code></td>
                        <td>60 req/min</td>
                    </tr>
                    <tr>
                        <td><span class="badge badge-warning">Print Agent</span></td>
                        <td>Bearer Token</td>
                        <td><code>Authorization: Bearer {agent_key}</code></td>
                        <td>120 req/min</td>
                    </tr>
                </tbody>
            </table>
            <p>This documentation covers the <strong>Client App API</strong> — used by external applications to submit print jobs, discover templates, and manage print queues.</p>
        </section>

        {{-- ================================================================= --}}
        {{-- 2. GETTING STARTED --}}
        {{-- ================================================================= --}}
        <section class="card doc-section" id="getting-started">
            <div class="card-header"><h2>2. Getting Started</h2></div>

            <h3 id="gs-register">Register a Client App</h3>
            <ol>
                <li>Log in to the Print Hub admin panel as a super-admin.</li>
                <li>Navigate to <strong>Client Apps</strong> in the sidebar.</li>
                <li>Click <strong>Add Client App</strong>, enter a name (e.g. "My POS System").</li>
                <li>Set the <strong>Allowed Origins</strong> if you need CORS access (comma-separated URLs).</li>
                <li>Click <strong>Save</strong> — an API key is generated automatically.</li>
                <li>Copy the API key and store it securely. You will not be able to see it again.</li>
            </ol>

            <div class="tip-box">
                <strong>💡 Tip:</strong> You can create multiple client apps with different API keys for different environments (development, staging, production).
            </div>

            <h3 id="gs-quickstart">Quick Start — Your First Print Request</h3>
            <p>Make a test connection to verify your API key works:</p>

            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>curl -H "X-API-Key: your-api-key-here" \
    {{ config('app.url') }}/api/v1/test</code></pre>
            </div>

            <p>Then submit a simple print job using a template:</p>
            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>curl -X POST {{ config('app.url') }}/api/v1/print \
    -H "X-API-Key: your-api-key-here" \
    -H "Content-Type: application/json" \
    -d '{
        "template": "invoice_sewa",
        "data": {
            "no_invoice": "INV-001",
            "customer": "PT Example",
            "total": 150000
        },
        "branch_code": "SDP-MAIN",
        "reference_id": "INV-001"
    }'</code></pre>
            </div>

            <h3>Base URL</h3>
            <p>All API endpoints are prefixed with the base URL:</p>
            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>{{ config('app.url') }}/api/v1</code></pre>
            </div>
            <p>Replace <code>{{ config('app.url') }}</code> with your actual Print Hub server URL in production.</p>
        </section>

        {{-- ================================================================= --}}
        {{-- 3. AUTHENTICATION --}}
        {{-- ================================================================= --}}
        <section class="card doc-section" id="authentication">
            <div class="card-header"><h2>3. Authentication</h2></div>

            <p>Every request to the Client App API must include the <code>X-API-Key</code> header with a valid API key obtained from the admin panel.</p>

            <h3>cURL Example</h3>
            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>curl -H "X-API-Key: ph_live_abc123def456" \
    {{ config('app.url') }}/api/v1/branches</code></pre>
            </div>

            <h3>PHP Example</h3>
            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>&lt;?php
$ch = curl_init('{{ config('app.url') }}/api/v1/branches');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => [
        'X-API-Key: ph_live_abc123def456',
        'Accept: application/json',
    ],
    CURLOPT_RETURNTRANSFER => true,
]);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
print_r($data);</code></pre>
            </div>

            <h3>JavaScript / Axios Example</h3>
            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>const response = await fetch('{{ config('app.url') }}/api/v1/branches', {
    headers: {
        'X-API-Key': 'ph_live_abc123def456',
        'Accept': 'application/json',
    }
});
const data = await response.json();
console.log(data);</code></pre>
            </div>

            <div class="tip-box warning">
                <strong>⚠️ Security:</strong> Never expose your API key in client-side code. Always proxy requests through your backend server.
            </div>
        </section>

        {{-- ================================================================= --}}
        {{-- 4. API ENDPOINTS — FULL REFERENCE --}}
        {{-- ================================================================= --}}
        <section class="card doc-section" id="endpoints">
            <div class="card-header"><h2>4. API Endpoints — Full Reference</h2></div>
            <p>All endpoints are prefixed with <code>{{ config('app.url') }}/api/v1</code>. All responses follow a standard envelope format (see <a href="#errors">Error Reference</a>).</p>

            {{-- ============================================================= --}}
            {{-- Connection & Health --}}
            {{-- ============================================================= --}}
            <div class="endpoint-group">
                <h3 id="ep-connection" class="expandable" onclick="toggleSection(this)">
                    <span class="expandable-arrow">▸</span> Connection & Health
                </h3>
                <div class="expandable-content">

                    {{-- GET /test --}}
                    <div class="endpoint-block" id="ep-test">
                        <div class="endpoint-header">
                            <span class="method method-get">GET</span>
                            <code class="endpoint-url">/test</code>
                            <span class="endpoint-tag">Test Connection</span>
                        </div>
                        <p>Verify your API key is valid and the server is reachable.</p>

                        <h4>Response</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>{
    "success": true,
    "data": {
        "message": "Connected successfully.",
        "app_name": "My POS System",
        "agents": 3,
        "server_time": "2026-05-04T05:00:00+00:00"
    }
}</code></pre>
                        </div>

                        <h4>Error Codes</h4>
                        <ul class="error-list">
                            <li><code>401</code> — <code>MISSING_API_KEY</code> / <code>INVALID_API_KEY</code></li>
                        </ul>
                    </div>

                    {{-- GET /health --}}
                    <div class="endpoint-block" id="ep-health">
                        <div class="endpoint-header">
                            <span class="method method-get">GET</span>
                            <code class="endpoint-url">/health</code>
                            <span class="endpoint-tag">System Health</span>
                        </div>
                        <p>Get system health status including online agents and pending jobs.</p>

                        <h4>Response</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>{
    "success": true,
    "data": {
        "status": "ok",
        "agents_online": 3,
        "agents_total": 5,
        "jobs_pending": 12,
        "jobs_processing": 2,
        "server_time": "2026-05-04T05:00:00+00:00"
    }
}</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============================================================= --}}
            {{-- Discovery --}}
            {{-- ============================================================= --}}
            <div class="endpoint-group">
                <h3 id="ep-discovery" class="expandable" onclick="toggleSection(this)">
                    <span class="expandable-arrow">▸</span> Discovery
                </h3>
                <div class="expandable-content">

                    {{-- GET /agents/online --}}
                    <div class="endpoint-block" id="ep-agents">
                        <div class="endpoint-header">
                            <span class="method method-get">GET</span>
                            <code class="endpoint-url">/agents/online</code>
                            <span class="endpoint-tag">List Online Agents</span>
                        </div>
                        <p>List all currently online print agents. Agents are considered online if they have sent a heartbeat within the last 60 seconds.</p>

                        <h4>Query Parameters</h4>
                        <table>
                            <thead><tr><th>Parameter</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>branch_code</code></td><td>string</td><td>No</td><td>Filter by branch code (e.g. <code>SDP-SBY</code>)</td></tr>
                                <tr><td><code>branch_id</code></td><td>integer</td><td>No</td><td>Filter by branch ID</td></tr>
                            </tbody>
                        </table>

                        <h4>Response</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>{
    "success": true,
    "data": {
        "agents": [
            {
                "id": 1,
                "name": "PC-SBY-01",
                "printers": ["HP LaserJet M404", "Epson L3110"],
                "branch": {
                    "id": 1,
                    "code": "SDP-SBY",
                    "name": "SDP - Surabaya"
                },
                "location": "Counter 3",
                "department": "Cashier"
            }
        ]
    }
}</code></pre>
                        </div>
                    </div>

                    {{-- GET /branches --}}
                    <div class="endpoint-block" id="ep-branches">
                        <div class="endpoint-header">
                            <span class="method method-get">GET</span>
                            <code class="endpoint-url">/branches</code>
                            <span class="endpoint-tag">List Branches</span>
                        </div>
                        <p>List all active branches with company information.</p>

                        <h4>Response</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>{
    "success": true,
    "data": {
        "branches": [
            {
                "id": 1,
                "code": "SDP-MAIN",
                "name": "SDP - Main",
                "address": "Jl. Example No. 1",
                "company": {
                    "id": 1,
                    "code": "SDP",
                    "name": "Sinar Dinamika Pratama"
                }
            }
        ]
    }
}</code></pre>
                        </div>
                    </div>

                    {{-- GET /queues --}}
                    <div class="endpoint-block" id="ep-queues">
                        <div class="endpoint-header">
                            <span class="method method-get">GET</span>
                            <code class="endpoint-url">/queues</code>
                            <span class="endpoint-tag">List Print Queues</span>
                        </div>
                        <p>List all print queues (profiles), optionally filtered by branch.</p>

                        <h4>Query Parameters</h4>
                        <table>
                            <thead><tr><th>Parameter</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>branch_code</code></td><td>string</td><td>No</td><td>Filter by branch code</td></tr>
                                <tr><td><code>branch_id</code></td><td>integer</td><td>No</td><td>Filter by branch ID</td></tr>
                                <tr><td><code>detailed</code></td><td>boolean</td><td>No</td><td>Set to <code>1</code> or <code>true</code> for full configuration details</td></tr>
                            </tbody>
                        </table>

                        <h4>Response (basic)</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>{
    "success": true,
    "data": {
        "queues": [
            {
                "name": "sby-invoice",
                "description": "Invoice queue for Surabaya",
                "printer": "HP LaserJet M404",
                "is_online": true,
                "agent_name": "PC-SBY-01",
                "branch_id": 1
            }
        ]
    }
}</code></pre>
                        </div>

                        <h4>Response (detailed)</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>{
    "queues": [{
        "name": "sby-invoice",
        "description": "Invoice queue for Surabaya",
        "printer": "HP LaserJet M404",
        "is_online": true,
        "agent_name": "PC-SBY-01",
        "branch_id": 1,
        "paper_size": "A4",
        "orientation": "portrait",
        "copies": 1,
        "duplex": false,
        "margins": { "top": 10, "bottom": 10, "left": 10, "right": 10 },
        "tray_source": "auto",
        "color_mode": "color",
        "print_quality": "normal",
        "scaling_percentage": 100,
        "media_type": "plain",
        "collate": true,
        "reverse_order": false
    }]
}</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============================================================= --}}
            {{-- Templates --}}
            {{-- ============================================================= --}}
            <div class="endpoint-group">
                <h3 id="ep-templates" class="expandable" onclick="toggleSection(this)">
                    <span class="expandable-arrow">▸</span> Templates
                </h3>
                <div class="expandable-content">

                    {{-- GET /templates --}}
                    <div class="endpoint-block" id="ep-templates-list">
                        <div class="endpoint-header">
                            <span class="method method-get">GET</span>
                            <code class="endpoint-url">/templates</code>
                            <span class="endpoint-tag">List Templates</span>
                        </div>
                        <p>List all available print templates with pagination.</p>

                        <h4>Query Parameters</h4>
                        <table>
                            <thead><tr><th>Parameter</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>per_page</code></td><td>integer</td><td>No</td><td>Items per page (max 100, default 25)</td></tr>
                            </tbody>
                        </table>

                        <h4>Response</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>{
    "success": true,
    "data": {
        "templates": [
            {
                "name": "invoice_sewa",
                "paper_width_mm": 210,
                "paper_height_mm": 297,
                "fields": ["no_invoice", "customer", "total"],
                "tables": [
                    {
                        "key": "items",
                        "columns": [
                            { "label": "Description", "key": "description" },
                            { "label": "Qty", "key": "qty" },
                            { "label": "Price", "key": "price" }
                        ]
                    }
                ],
                "schema": { "name": "invoice_sewa", "version": 3 }
            }
        ],
        "meta": {
            "current_page": 1,
            "per_page": 25,
            "total": 10,
            "last_page": 1
        }
    }
}</code></pre>
                        </div>
                    </div>

                    {{-- GET /templates/{name} --}}
                    <div class="endpoint-block" id="ep-templates-detail">
                        <div class="endpoint-header">
                            <span class="method method-get">GET</span>
                            <code class="endpoint-url">/templates/{name}</code>
                            <span class="endpoint-tag">Get Template Details</span>
                        </div>
                        <p>Get detailed information about a specific template, including field positions and dimensions.</p>

                        <h4>Parameters</h4>
                        <table>
                            <thead><tr><th>Parameter</th><th>Type</th><th>Location</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>name</code></td><td>string</td><td>URL</td><td>Template name (e.g. <code>invoice_sewa</code>)</td></tr>
                            </tbody>
                        </table>

                        <h4>Response</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>{
    "success": true,
    "data": {
        "name": "invoice_sewa",
        "paper_width_mm": 210,
        "paper_height_mm": 297,
        "fields": [
            {
                "key": "no_invoice",
                "font_size": 10,
                "bold": false,
                "border": false,
                "align": "L",
                "x": 10, "y": 10,
                "width": 80, "height": 6
            }
        ],
        "tables": [
            {
                "key": "items",
                "x": 10, "y": 30,
                "columns": [
                    { "label": "Description", "key": "description", "width": 100 },
                    { "label": "Qty", "key": "qty", "width": 20 },
                    { "label": "Price", "key": "price", "width": 30 }
                ]
            }
        ],
        "schema": { "name": "invoice_sewa", "version": 3 }
    }
}</code></pre>
                        </div>

                        <h4>Error Codes</h4>
                        <ul class="error-list">
                            <li><code>404</code> — <code>TEMPLATE_NOT_FOUND</code></li>
                        </ul>
                    </div>

                    {{-- GET /templates/{name}/schema --}}
                    <div class="endpoint-block" id="ep-templates-schema">
                        <div class="endpoint-header">
                            <span class="method method-get">GET</span>
                            <code class="endpoint-url">/templates/{name}/schema</code>
                            <span class="endpoint-tag">Get Template Schema</span>
                        </div>
                        <p>Get the required data schema for a template, including field types, validation rules, and table structures.</p>

                        <h4>Parameters</h4>
                        <table>
                            <thead><tr><th>Parameter</th><th>Type</th><th>Location</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>name</code></td><td>string</td><td>URL</td><td>Template name</td></tr>
                            </tbody>
                        </table>

                        <h4>Response</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>{
    "success": true,
    "data": {
        "required_fields": {
            "no_invoice": {
                "label": "Invoice Number",
                "type": "string",
                "required": true,
                "max_length": 50
            },
            "customer": {
                "label": "Customer Name",
                "type": "string",
                "required": true
            },
            "total": {
                "label": "Total Amount",
                "type": "number",
                "required": true
            }
        },
        "required_tables": {
            "items": {
                "label": "Invoice Items",
                "min_rows": 1,
                "columns": {
                    "description": { "label": "Description", "type": "string", "required": true },
                    "qty": { "label": "Quantity", "type": "number", "required": true },
                    "price": { "label": "Unit Price", "type": "number", "required": true }
                }
            }
        }
    }
}</code></pre>
                        </div>

                        <h4>Error Codes</h4>
                        <ul class="error-list">
                            <li><code>404</code> — <code>TEMPLATE_NOT_FOUND</code></li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- ============================================================= --}}
            {{-- Data Schemas --}}
            {{-- ============================================================= --}}
            <div class="endpoint-group">
                <h3 id="ep-schemas" class="expandable" onclick="toggleSection(this)">
                    <span class="expandable-arrow">▸</span> Data Schemas
                </h3>
                <div class="expandable-content">

                    {{-- POST /schema --}}
                    <div class="endpoint-block" id="ep-schema-register">
                        <div class="endpoint-header">
                            <span class="method method-post">POST</span>
                            <code class="endpoint-url">/schema</code>
                            <span class="endpoint-tag">Register/Update Schema</span>
                        </div>
                        <p>Register a new data schema or update an existing one. Schemas define the structure of data that templates can bind to. If the schema fields/tables haven't changed, no new version is created.</p>

                        <h4>Request Body</h4>
                        <table>
                            <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>schema_name</code></td><td>string</td><td>Yes</td><td>Unique schema identifier</td></tr>
                                <tr><td><code>label</code></td><td>string</td><td>No</td><td>Human-readable label</td></tr>
                                <tr><td><code>fields</code></td><td>object</td><td>No</td><td>Field definitions (key → {label, type, required})</td></tr>
                                <tr><td><code>tables</code></td><td>object</td><td>No</td><td>Table definitions (key → {label, columns})</td></tr>
                                <tr><td><code>sample_data</code></td><td>object</td><td>No</td><td>Sample data for preview/testing</td></tr>
                            </tbody>
                        </table>

                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>curl -X POST {{ config('app.url') }}/api/v1/schema \
    -H "X-API-Key: your-api-key" \
    -H "Content-Type: application/json" \
    -d '{
        "schema_name": "invoice_sewa",
        "label": "Sewa Invoice",
        "fields": {
            "no_invoice": { "label": "Invoice No", "type": "string", "required": true },
            "customer": { "label": "Customer", "type": "string", "required": true },
            "total": { "label": "Total", "type": "number", "required": true }
        },
        "tables": {
            "items": {
                "label": "Items",
                "columns": {
                    "description": { "label": "Description", "type": "string" },
                    "qty": { "label": "Qty", "type": "number" },
                    "price": { "label": "Price", "type": "number" }
                }
            }
        },
        "sample_data": {
            "no_invoice": "INV-001",
            "customer": "PT ABC",
            "total": 150000,
            "items": [
                { "description": "Service Fee", "qty": 1, "price": 150000 }
            ]
        }
    }'</code></pre>
                        </div>

                        <h4>Response</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>{
    "success": true,
    "data": {
        "schema_name": "invoice_sewa",
        "version": 3,
        "is_new": true,
        "message": "Schema v3 created."
    }
}</code></pre>
                        </div>
                    </div>

                    {{-- GET /schemas --}}
                    <div class="endpoint-block" id="ep-schemas-list">
                        <div class="endpoint-header">
                            <span class="method method-get">GET</span>
                            <code class="endpoint-url">/schemas</code>
                            <span class="endpoint-tag">List Schemas</span>
                        </div>
                        <p>List all registered data schemas with pagination.</p>

                        <h4>Query Parameters</h4>
                        <table>
                            <thead><tr><th>Parameter</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>latest</code></td><td>boolean</td><td>No</td><td>Only return latest version per schema (default: <code>true</code>)</td></tr>
                                <tr><td><code>per_page</code></td><td>integer</td><td>No</td><td>Items per page (max 100, default 25)</td></tr>
                            </tbody>
                        </table>

                        <h4>Response</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>{
    "success": true,
    "data": {
        "schemas": [
            {
                "id": 1,
                "schema_name": "invoice_sewa",
                "version": 3,
                "is_latest": true,
                "label": "Sewa Invoice",
                "client_app": "My POS System",
                "fields": { "no_invoice": {...}, "customer": {...}, "total": {...} },
                "tables": { "items": {...} },
                "has_sample": true,
                "changelog": "Added signature field",
                "updated_at": "2026-05-04T05:00:00.000000Z"
            }
        ],
        "meta": { "current_page": 1, "per_page": 25, "total": 5, "last_page": 1 }
    }
}</code></pre>
                        </div>
                    </div>

                    {{-- GET /schema/{name}/versions --}}
                    <div class="endpoint-block" id="ep-schema-versions">
                        <div class="endpoint-header">
                            <span class="method method-get">GET</span>
                            <code class="endpoint-url">/schema/{name}/versions</code>
                            <span class="endpoint-tag">Schema Version History</span>
                        </div>
                        <p>Get the full version history of a specific data schema.</p>

                        <h4>Parameters</h4>
                        <table>
                            <thead><tr><th>Parameter</th><th>Type</th><th>Location</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>name</code></td><td>string</td><td>URL</td><td>Schema name</td></tr>
                            </tbody>
                        </table>

                        <h4>Response</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>{
    "success": true,
    "data": {
        "schema_name": "invoice_sewa",
        "versions": [
            {
                "version": 3,
                "is_latest": true,
                "changelog": "Added signature field",
                "fields": ["no_invoice", "customer", "total", "signature"],
                "tables": ["items"],
                "updated_at": "2026-05-04T05:00:00.000000Z"
            },
            {
                "version": 2,
                "is_latest": false,
                "changelog": "Added items table",
                "fields": ["no_invoice", "customer", "total"],
                "tables": ["items"],
                "updated_at": "2026-04-28T10:00:00.000000Z"
            }
        ]
    }
}</code></pre>
                        </div>

                        <h4>Error Codes</h4>
                        <ul class="error-list">
                            <li><code>404</code> — <code>SCHEMA_NOT_FOUND</code></li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- ============================================================= --}}
            {{-- Printing --}}
            {{-- ============================================================= --}}
            <div class="endpoint-group">
                <h3 id="ep-printing" class="expandable" onclick="toggleSection(this)">
                    <span class="expandable-arrow">▸</span> Printing
                </h3>
                <div class="expandable-content">

                    {{-- POST /print --}}
                    <div class="endpoint-block" id="ep-print">
                        <div class="endpoint-header">
                            <span class="method method-post">POST</span>
                            <code class="endpoint-url">/print</code>
                            <span class="endpoint-tag">Unified Print Endpoint</span>
                        </div>
                        <p>The main endpoint for submitting print jobs. Supports both <strong>template-based</strong> printing (rendered server-side) and <strong>raw document</strong> printing (base64-encoded PDF).</p>

                        <h4>Request Body</h4>
                        <table>
                            <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>template</code></td><td>string</td><td>See note*</td><td>Template name for template-based printing</td></tr>
                                <tr><td><code>data</code></td><td>object</td><td>See note*</td><td>Key-value data for template fields</td></tr>
                                <tr><td><code>document_base64</code></td><td>string</td><td>See note*</td><td>Base64-encoded PDF for raw document printing</td></tr>
                                <tr><td><code>document_id</code></td><td>integer</td><td>No</td><td>ID of a previously uploaded document to print</td></tr>
                                <tr><td><code>type</code></td><td>string</td><td>No</td><td>Document type (e.g. <code>pdf</code>, <code>raw</code>)</td></tr>
                                <tr><td><code>branch_code</code></td><td>string</td><td>No</td><td>Target branch code for routing</td></tr>
                                <tr><td><code>branch_id</code></td><td>integer</td><td>No</td><td>Target branch ID (alternative to branch_code)</td></tr>
                                <tr><td><code>queue</code></td><td>string</td><td>No</td><td>Explicit queue/profile name</td></tr>
                                <tr><td><code>profile</code></td><td>string</td><td>No</td><td>Alias for <code>queue</code></td></tr>
                                <tr><td><code>agent_id</code></td><td>integer</td><td>No</td><td>Pin to a specific agent ID</td></tr>
                                <tr><td><code>printer</code></td><td>string</td><td>No</td><td>Override printer name</td></tr>
                                <tr><td><code>pool_id</code></td><td>integer</td><td>No</td><td>Printer pool ID for automatic printer selection</td></tr>
                                <tr><td><code>reference_id</code></td><td>string</td><td>No</td><td>Your app's reference ID for tracking</td></tr>
                                <tr><td><code>webhook_url</code></td><td>string (URL)</td><td>No</td><td>URL to receive async status update</td></tr>
                                <tr><td><code>skip_validation</code></td><td>boolean</td><td>No</td><td>Skip schema validation (default: <code>false</code>)</td></tr>
                                <tr><td><code>parameters</code></td><td>object</td><td>No</td><td>Additional template parameters (key-value pairs)</td></tr>
                                <tr><td><code>priority</code></td><td>integer</td><td>No</td><td>Job priority (higher = processed first, default: 0)</td></tr>
                                <tr><td><code>scheduled_at</code></td><td>string</td><td>No</td><td>Schedule for future printing (format: <code>Y-m-d H:i:s</code>)</td></tr>
                                <tr><td><code>recurrence</code></td><td>string</td><td>No</td><td>Recurrence pattern: <code>none</code>, <code>daily</code>, <code>weekly</code>, <code>monthly</code></td></tr>
                                <tr><td><code>recurrence_end_at</code></td><td>string</td><td>No</td><td>End date for recurring schedule (<code>Y-m-d H:i:s</code>)</td></tr>
                                <tr><td><code>recurrence_count</code></td><td>integer</td><td>No</td><td>Max occurrences for recurring schedule</td></tr>
                                <tr><td><code>options</code></td><td>object</td><td>No</td><td>Printer options (copies, tray_source, etc.)</td></tr>
                            </tbody>
                        </table>
                        <p class="note">* You must provide either <code>template</code>+<code>data</code> (template-based) or <code>document_base64</code> (raw document).</p>

                        <h4>Print Options</h4>
                        <table>
                            <thead><tr><th>Option</th><th>Type</th><th>Default</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>copies</code></td><td>integer</td><td>1</td><td>Number of copies (1–99)</td></tr>
                                <tr><td><code>priority</code></td><td>integer</td><td>0</td><td>Job priority (higher = processed first)</td></tr>
                                <tr><td><code>tray_source</code></td><td>string</td><td><code>auto</code></td><td>Paper tray: <code>auto</code>, <code>tray1</code>, <code>tray2</code>, <code>manual</code>, <code>envelope</code></td></tr>
                                <tr><td><code>color_mode</code></td><td>string</td><td><code>color</code></td><td><code>color</code> or <code>monochrome</code></td></tr>
                                <tr><td><code>print_quality</code></td><td>string</td><td><code>normal</code></td><td><code>draft</code>, <code>normal</code>, <code>high</code></td></tr>
                                <tr><td><code>scaling_percentage</code></td><td>integer</td><td>100</td><td>Scale (1–400%)</td></tr>
                                <tr><td><code>media_type</code></td><td>string</td><td><code>plain</code></td><td><code>plain</code>, <code>glossy</code>, <code>envelope</code>, <code>label</code>, <code>continuous_feed</code></td></tr>
                                <tr><td><code>collate</code></td><td>boolean</td><td><code>true</code></td><td>Collate multiple copies</td></tr>
                                <tr><td><code>reverse_order</code></td><td>boolean</td><td><code>false</code></td><td>Print pages in reverse order</td></tr>
                            </tbody>
                        </table>

                        <h4>Template-based Print Request</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>curl -X POST {{ config('app.url') }}/api/v1/print \
    -H "X-API-Key: your-api-key" \
    -H "Content-Type: application/json" \
    -d '{
        "template": "invoice_sewa",
        "data": {
            "no_invoice": "INV-001",
            "customer": "PT ABC",
            "total": 150000,
            "items": [
                { "description": "Service Fee", "qty": 1, "price": 150000 }
            ]
        },
        "branch_code": "SDP-SBY",
        "reference_id": "INV-001",
        "options": {
            "copies": 2,
            "color_mode": "monochrome"
        }
    }'</code></pre>
                        </div>

                        <h4>Raw Document Print Request</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>curl -X POST {{ config('app.url') }}/api/v1/print \
    -H "X-API-Key: your-api-key" \
    -H "Content-Type: application/json" \
    -d '{
        "document_base64": "JVBERi0xLjcN...base64-encoded-pdf...",
        "branch_code": "SDP-SBY",
        "reference_id": "DOC-001"
    }'</code></pre>
                        </div>

                        <h4>Success Response (202 Accepted)</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>{
    "success": true,
    "data": {
        "status": "queued",
        "job_id": "550e8400-e29b-41d4-a716-446655440000",
        "agent": "PC-SBY-01",
        "printer": "HP LaserJet M404",
        "template": "invoice_sewa",
        "queue": "sby-invoice"
    }
}</code></pre>
                        </div>

                        <h4>Error Codes</h4>
                        <ul class="error-list">
                            <li><code>400</code> — <code>VALIDATION_FAILED</code> (missing template/document_base64)</li>
                            <li><code>404</code> — <code>TEMPLATE_NOT_FOUND</code> / <code>BRANCH_NOT_FOUND</code></li>
                            <li><code>422</code> — <code>VALIDATION_FAILED</code> (schema validation errors)</li>
                            <li><code>503</code> — <code>NO_AGENT_AVAILABLE</code></li>
                        </ul>
                    </div>

                    {{-- POST /print/batch --}}
                    <div class="endpoint-block" id="ep-batch">
                        <div class="endpoint-header">
                            <span class="method method-post">POST</span>
                            <code class="endpoint-url">/print/batch</code>
                            <span class="endpoint-tag">Batch Print</span>
                        </div>
                        <p>Submit up to <strong>50 print jobs</strong> in a single request. Each job can have its own template, data, branch, and options. The batch is processed atomically — if any job fails, the entire batch is rolled back.</p>

                        <h4>Request Body</h4>
                        <table>
                            <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>jobs</code></td><td>array</td><td>Yes</td><td>Array of job objects (2–50 items)</td></tr>
                                <tr><td><code>dry_run</code></td><td>boolean</td><td>No</td><td>Validate only, don't queue (default: <code>false</code>)</td></tr>
                            </tbody>
                        </table>

                        <p>Each job object supports the same fields as <a href="#ep-print"><code>POST /print</code></a>: <code>template</code>, <code>data</code>, <code>document_base64</code>, <code>branch_code</code>, <code>branch_id</code>, <code>queue</code>, <code>printer</code>, <code>reference_id</code>, <code>options</code>.</p>

                        <h4>Request Example</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>curl -X POST {{ config('app.url') }}/api/v1/print/batch \
    -H "X-API-Key: your-api-key" \
    -H "Content-Type: application/json" \
    -d '{
        "jobs": [
            {
                "template": "invoice_sewa",
                "data": { "no_invoice": "INV-001", "customer": "PT ABC", "total": 150000 },
                "reference_id": "INV-001",
                "branch_code": "SDP-SBY"
            },
            {
                "template": "receipt",
                "data": { "receipt_no": "RCP-001", "amount": 50000 },
                "reference_id": "RCP-001",
                "branch_code": "SDP-SBY"
            }
        ],
        "dry_run": true
    }'</code></pre>
                        </div>

                        <h4>Response</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>{
    "success": true,
    "data": {
        "batch_id": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
        "total": 2,
        "results": [
            { "index": 0, "success": true, "job_id": "job-uuid-1", "reference": "INV-001" },
            { "index": 1, "success": true, "job_id": "job-uuid-2", "reference": "RCP-001" }
        ]
    }
}</code></pre>
                        </div>

                        <div class="tip-box">
                            <strong>💡 Tip:</strong> Use <code>"dry_run": true</code> to validate all jobs before committing. The dry run checks schema compliance, branch existence, and agent availability without actually queuing the jobs.
                        </div>

                        <h4>Error Codes</h4>
                        <ul class="error-list">
                            <li><code>422</code> — <code>VALIDATION_FAILED</code> (one or more jobs invalid)</li>
                            <li><code>422</code> — <code>BATCH_FAILED</code> (atomic batch failure, all rolled back)</li>
                        </ul>
                    </div>

                    {{-- POST /preview --}}
                    <div class="endpoint-block" id="ep-preview">
                        <div class="endpoint-header">
                            <span class="method method-post">POST</span>
                            <code class="endpoint-url">/preview</code>
                            <span class="endpoint-tag">Generate Preview</span>
                        </div>
                        <p>Generate a PDF preview of a template with data, without sending it to a printer. Returns the raw PDF binary (not JSON).</p>

                        <h4>Request Body</h4>
                        <table>
                            <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>template</code></td><td>string</td><td>Yes</td><td>Template name</td></tr>
                                <tr><td><code>data</code></td><td>object</td><td>No</td><td>Template data (use sample data to test layout)</td></tr>
                                <tr><td><code>options</code></td><td>object</td><td>No</td><td>Paper size, orientation, margins</td></tr>
                            </tbody>
                        </table>

                        <h4>Request Example</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>curl -X POST {{ config('app.url') }}/api/v1/preview \
    -H "X-API-Key: your-api-key" \
    -H "Content-Type: application/json" \
    -d '{
        "template": "invoice_sewa",
        "data": {
            "no_invoice": "INV-001",
            "customer": "PT ABC",
            "total": 150000
        }
    }' \
    --output preview.pdf</code></pre>
                        </div>

                        <h4>Response</h4>
                        <p>Returns <code>200 OK</code> with <code>Content-Type: application/pdf</code>. The body is the raw PDF binary.</p>

                        <h4>Error Codes</h4>
                        <ul class="error-list">
                            <li><code>404</code> — <code>TEMPLATE_NOT_FOUND</code></li>
                            <li><code>422</code> — <code>VALIDATION_FAILED</code></li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- ============================================================= --}}
            {{-- Job Management --}}
            {{-- ============================================================= --}}
            <div class="endpoint-group">
                <h3 id="ep-jobs" class="expandable" onclick="toggleSection(this)">
                    <span class="expandable-arrow">▸</span> Job Management
                </h3>
                <div class="expandable-content">

                    {{-- POST /jobs (legacy) --}}
                    <div class="endpoint-block legacy" id="ep-jobs-legacy">
                        <div class="endpoint-header">
                            <span class="method method-post">POST</span>
                            <code class="endpoint-url">/jobs</code>
                            <span class="endpoint-tag legacy-tag">Legacy</span>
                        </div>
                        <p>Legacy print submission endpoint, maintained for backwards compatibility. Internally delegates to <a href="#ep-print"><code>POST /print</code></a>. If you send <code>template_data</code> instead of <code>data</code>, it will be remapped automatically.</p>
                        <p><strong>New integrations should use <code>POST /print</code> instead.</strong></p>

                        <h4>Request Body</h4>
                        <p>Same as <code>POST /print</code>, with the addition that <code>template_data</code> is accepted as an alias for <code>data</code>.</p>
                    </div>

                    {{-- GET /jobs/{job_id} --}}
                    <div class="endpoint-block" id="ep-jobs-status">
                        <div class="endpoint-header">
                            <span class="method method-get">GET</span>
                            <code class="endpoint-url">/jobs/{job_id}</code>
                            <span class="endpoint-tag">Check Job Status</span>
                        </div>
                        <p>Get the current status of a print job by its UUID.</p>

                        <h4>Parameters</h4>
                        <table>
                            <thead><tr><th>Parameter</th><th>Type</th><th>Location</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>job_id</code></td><td>string (UUID)</td><td>URL</td><td>The job UUID returned from the print endpoint</td></tr>
                            </tbody>
                        </table>

                        <h4>Response</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>{
    "success": true,
    "data": {
        "job_id": "550e8400-e29b-41d4-a716-446655440000",
        "status": "success",
        "reference_id": "INV-001",
        "printer": "HP LaserJet M404",
        "template": "invoice_sewa",
        "error": null,
        "created_at": "2026-05-04T05:00:00.000000Z",
        "completed_at": "2026-05-04T05:00:05.000000Z"
    }
}</code></pre>
                        </div>

                        <h4>Job Status Values</h4>
                        <table>
                            <thead><tr><th>Status</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><span class="badge badge-warning">pending</span></td><td>Job is queued and waiting for agent pickup</td></tr>
                                <tr><td><span class="badge badge-info">processing</span></td><td>Agent has acknowledged the job and is printing</td></tr>
                                <tr><td><span class="badge badge-success">success</span></td><td>Job completed successfully</td></tr>
                                <tr><td><span class="badge badge-danger">failed</span></td><td>Job failed (check <code>error</code> field for details)</td></tr>
                                <tr><td><span class="badge badge-danger">cancelled</span></td><td>Job was cancelled by the client</td></tr>
                            </tbody>
                        </table>

                        <h4>Error Codes</h4>
                        <ul class="error-list">
                            <li><code>404</code> — <code>JOB_NOT_FOUND</code></li>
                        </ul>
                    </div>

                    {{-- DELETE /jobs/{job_id} --}}
                    <div class="endpoint-block" id="ep-jobs-cancel">
                        <div class="endpoint-header">
                            <span class="method method-delete">DELETE</span>
                            <code class="endpoint-url">/jobs/{job_id}</code>
                            <span class="endpoint-tag">Cancel Job</span>
                        </div>
                        <p>Cancel a pending print job. Only jobs in <code>pending</code> status can be cancelled. Once an agent has started processing a job, it cannot be cancelled.</p>

                        <h4>Parameters</h4>
                        <table>
                            <thead><tr><th>Parameter</th><th>Type</th><th>Location</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>job_id</code></td><td>string (UUID)</td><td>URL</td><td>The job UUID to cancel</td></tr>
                            </tbody>
                        </table>

                        <h4>Response</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>{
    "success": true,
    "data": {
        "job_id": "550e8400-e29b-41d4-a716-446655440000",
        "status": "cancelled",
        "message": "Job cancelled successfully."
    }
}</code></pre>
                        </div>

                        <h4>Error Codes</h4>
                        <ul class="error-list">
                            <li><code>404</code> — <code>JOB_NOT_FOUND</code></li>
                            <li><code>409</code> — <code>JOB_NOT_CANCELLABLE</code> (job is processing/success/failed)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        {{-- ================================================================= --}}
        {{-- 4.7 Document Management --}}
        {{-- ================================================================= --}}
        <section class="card doc-section" id="ep-documents">
            <div class="card-header"><h3>Document Management</h3></div>

            <p>Print Hub supports uploading and managing documents that can be attached to print jobs. Documents are stored as base64-encoded files and can be previewed, downloaded, or deleted.</p>

            <div class="endpoint-group">
                <h3 onclick="toggleSection(this)"><span class="expandable-arrow">▸</span> Upload Document</h3>
                <div class="expandable-content">
                    <div class="endpoint-block">
                        <div class="endpoint-header">
                            <span class="method method-post">POST</span>
                            <span class="endpoint-url">/api/v1/documents/upload</span>
                            <span class="endpoint-tag">Document Management</span>
                        </div>
                        <p>Upload a document (base64-encoded) for later use in print jobs.</p>

                        <h4>Request Body</h4>
                        <table>
                            <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>filename</code></td><td>string</td><td>Yes</td><td>Document filename (e.g. <code>logo.png</code>)</td></tr>
                                <tr><td><code>file_data</code></td><td>string</td><td>Yes</td><td>Base64-encoded file content</td></tr>
                                <tr><td><code>mime_type</code></td><td>string</td><td>No</td><td>MIME type (auto-detected if omitted)</td></tr>
                            </tbody>
                        </table>

                        <h4>Response</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>{
    "success": true,
    "data": {
        "id": 1,
        "filename": "logo.png",
        "mime_type": "image/png",
        "file_size": 102400,
        "created_at": "2026-05-04T12:00:00Z"
    }
}</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            <div class="endpoint-group">
                <h3 onclick="toggleSection(this)"><span class="expandable-arrow">▸</span> List Documents</h3>
                <div class="expandable-content">
                    <div class="endpoint-block">
                        <div class="endpoint-header">
                            <span class="method method-get">GET</span>
                            <span class="endpoint-url">/api/v1/documents</span>
                            <span class="endpoint-tag">Document Management</span>
                        </div>
                        <p>List all uploaded documents.</p>

                        <h4>Response</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>{
    "success": true,
    "data": [
        {
            "id": 1,
            "filename": "logo.png",
            "mime_type": "image/png",
            "file_size": 102400,
            "created_at": "2026-05-04T12:00:00Z"
        }
    ]
}</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            <div class="endpoint-group">
                <h3 onclick="toggleSection(this)"><span class="expandable-arrow">▸</span> Get Document</h3>
                <div class="expandable-content">
                    <div class="endpoint-block">
                        <div class="endpoint-header">
                            <span class="method method-get">GET</span>
                            <span class="endpoint-url">/api/v1/documents/{id}</span>
                            <span class="endpoint-tag">Document Management</span>
                        </div>
                        <p>Get details of a specific document.</p>

                        <h4>Parameters</h4>
                        <table>
                            <thead><tr><th>Parameter</th><th>Type</th><th>Location</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>id</code></td><td>integer</td><td>URL</td><td>Document ID</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="endpoint-group">
                <h3 onclick="toggleSection(this)"><span class="expandable-arrow">▸</span> Preview Document</h3>
                <div class="expandable-content">
                    <div class="endpoint-block">
                        <div class="endpoint-header">
                            <span class="method method-get">GET</span>
                            <span class="endpoint-url">/api/v1/documents/{id}/preview</span>
                            <span class="endpoint-tag">Document Management</span>
                        </div>
                        <p>Preview a document rendered as PDF. Returns raw PDF binary.</p>
                    </div>
                </div>
            </div>

            <div class="endpoint-group">
                <h3 onclick="toggleSection(this)"><span class="expandable-arrow">▸</span> Download Document</h3>
                <div class="expandable-content">
                    <div class="endpoint-block">
                        <div class="endpoint-header">
                            <span class="method method-get">GET</span>
                            <span class="endpoint-url">/api/v1/documents/{id}/download</span>
                            <span class="endpoint-tag">Document Management</span>
                        </div>
                        <p>Download a document's raw file content.</p>
                    </div>
                </div>
            </div>

            <div class="endpoint-group">
                <h3 onclick="toggleSection(this)"><span class="expandable-arrow">▸</span> Delete Document</h3>
                <div class="expandable-content">
                    <div class="endpoint-block">
                        <div class="endpoint-header">
                            <span class="method method-delete">DELETE</span>
                            <span class="endpoint-url">/api/v1/documents/{id}</span>
                            <span class="endpoint-tag">Document Management</span>
                        </div>
                        <p>Delete a document.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ================================================================= --}}
        {{-- 4.8 Approvals --}}
        {{-- ================================================================= --}}
        <section class="card doc-section" id="ep-approvals">
            <div class="card-header"><h3>Approvals</h3></div>

            <p>Print jobs can be configured to require approval before being sent to a printer. Use the approvals endpoints to list pending jobs and approve or reject them.</p>

            <div class="endpoint-group">
                <h3 onclick="toggleSection(this)"><span class="expandable-arrow">▸</span> List Pending Approvals</h3>
                <div class="expandable-content">
                    <div class="endpoint-block">
                        <div class="endpoint-header">
                            <span class="method method-get">GET</span>
                            <span class="endpoint-url">/api/v1/approvals/pending</span>
                            <span class="endpoint-tag">Approvals</span>
                        </div>
                        <p>List all print jobs that are pending approval.</p>

                        <h4>Response</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>{
    "success": true,
    "data": [
        {
            "id": "550e8400-e29b-41d4-a716-446655440000",
            "template_name": "invoice_sewa",
            "reference_id": "INV-001",
            "status": "pending_approval",
            "submitted_by": "Client App Name",
            "created_at": "2026-05-04T12:00:00Z"
        }
    ]
}</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            <div class="endpoint-group">
                <h3 onclick="toggleSection(this)"><span class="expandable-arrow">▸</span> Approve Job</h3>
                <div class="expandable-content">
                    <div class="endpoint-block">
                        <div class="endpoint-header">
                            <span class="method method-post">POST</span>
                            <span class="endpoint-url">/api/v1/approvals/{id}/approve</span>
                            <span class="endpoint-tag">Approvals</span>
                        </div>
                        <p>Approve a pending print job, allowing it to proceed to the printer.</p>

                        <h4>Parameters</h4>
                        <table>
                            <thead><tr><th>Parameter</th><th>Type</th><th>Location</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>id</code></td><td>string (UUID)</td><td>URL</td><td>The job UUID to approve</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="endpoint-group">
                <h3 onclick="toggleSection(this)"><span class="expandable-arrow">▸</span> Reject Job</h3>
                <div class="expandable-content">
                    <div class="endpoint-block">
                        <div class="endpoint-header">
                            <span class="method method-post">POST</span>
                            <span class="endpoint-url">/api/v1/approvals/{id}/reject</span>
                            <span class="endpoint-tag">Approvals</span>
                        </div>
                        <p>Reject a pending print job.</p>

                        <h4>Parameters</h4>
                        <table>
                            <thead><tr><th>Parameter</th><th>Type</th><th>Location</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>id</code></td><td>string (UUID)</td><td>URL</td><td>The job UUID to reject</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        {{-- ================================================================= --}}
        {{-- 4.9 Connectors --}}
        {{-- ================================================================= --}}
        <section class="card doc-section" id="ep-connectors">
            <div class="card-header"><h3>Connectors</h3></div>

            <p>Connectors allow client apps to register external data sources (APIs, webhooks, Odoo, custom) that can provide live data for print previews and template rendering.</p>

            <div class="endpoint-group">
                <h3 onclick="toggleSection(this)"><span class="expandable-arrow">▸</span> Register Connector</h3>
                <div class="expandable-content">
                    <div class="endpoint-block">
                        <div class="endpoint-header">
                            <span class="method method-post">POST</span>
                            <span class="endpoint-url">/api/v1/connectors</span>
                            <span class="endpoint-tag">Connectors</span>
                        </div>
                        <p>Register a new data-source connector.</p>

                        <h4>Request Body</h4>
                        <table>
                            <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>name</code></td><td>string</td><td>Yes</td><td>Human-readable name (e.g. "SDP Finance ERP")</td></tr>
                                <tr><td><code>type</code></td><td>string</td><td>Yes</td><td>One of: <code>api</code>, <code>webhook</code>, <code>odoo</code>, <code>custom</code></td></tr>
                                <tr><td><code>config</code></td><td>object</td><td>Yes</td><td>Configuration: endpoint URL, auth type, headers, etc.</td></tr>
                                <tr><td><code>icon</code></td><td>string|null</td><td>No</td><td>Optional emoji or icon URL</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="endpoint-group">
                <h3 onclick="toggleSection(this)"><span class="expandable-arrow">▸</span> List Connectors</h3>
                <div class="expandable-content">
                    <div class="endpoint-block">
                        <div class="endpoint-header">
                            <span class="method method-get">GET</span>
                            <span class="endpoint-url">/api/v1/connectors</span>
                            <span class="endpoint-tag">Connectors</span>
                        </div>
                        <p>List all connectors registered for this client app.</p>
                    </div>
                </div>
            </div>

            <div class="endpoint-group">
                <h3 onclick="toggleSection(this)"><span class="expandable-arrow">▸</span> Update Connector</h3>
                <div class="expandable-content">
                    <div class="endpoint-block">
                        <div class="endpoint-header">
                            <span class="method method-put">PUT</span>
                            <span class="endpoint-url">/api/v1/connectors/{id}</span>
                            <span class="endpoint-tag">Connectors</span>
                        </div>
                        <p>Update an existing connector's fields (name, type, config, icon, is_active).</p>
                    </div>
                </div>
            </div>

            <div class="endpoint-group">
                <h3 onclick="toggleSection(this)"><span class="expandable-arrow">▸</span> Test Connector</h3>
                <div class="expandable-content">
                    <div class="endpoint-block">
                        <div class="endpoint-header">
                            <span class="method method-post">POST</span>
                            <span class="endpoint-url">/api/v1/connectors/{id}/test</span>
                            <span class="endpoint-tag">Connectors</span>
                        </div>
                        <p>Test a connector by sending a HEAD request to its configured URL.</p>
                    </div>
                </div>
            </div>

            <div class="endpoint-group">
                <h3 onclick="toggleSection(this)"><span class="expandable-arrow">▸</span> Delete Connector</h3>
                <div class="expandable-content">
                    <div class="endpoint-block">
                        <div class="endpoint-header">
                            <span class="method method-delete">DELETE</span>
                            <span class="endpoint-url">/api/v1/connectors/{id}</span>
                            <span class="endpoint-tag">Connectors</span>
                        </div>
                        <p>Delete a connector.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ================================================================= --}}
        {{-- 4.10 Agent & System --}}
        {{-- ================================================================= --}}
        <section class="card doc-section" id="ep-agent-system">
            <div class="card-header"><h3>Agent & System</h3></div>

            <p>Endpoints for querying agent version information and available system fonts.</p>

            <div class="endpoint-group">
                <h3 onclick="toggleSection(this)"><span class="expandable-arrow">▸</span> Get Agent Version</h3>
                <div class="expandable-content">
                    <div class="endpoint-block">
                        <div class="endpoint-header">
                            <span class="method method-get">GET</span>
                            <span class="endpoint-url">/api/v1/agents/version</span>
                            <span class="endpoint-tag">Agent & System</span>
                        </div>
                        <p>Get the latest available TrayPrint agent version.</p>

                        <h4>Response</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>{
    "success": true,
    "data": {
        "version": "2.1.0",
        "release_date": "2026-04-15",
        "download_url": "https://..."
    }
}</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            <div class="endpoint-group">
                <h3 onclick="toggleSection(this)"><span class="expandable-arrow">▸</span> List Fonts</h3>
                <div class="expandable-content">
                    <div class="endpoint-block">
                        <div class="endpoint-header">
                            <span class="method method-get">GET</span>
                            <span class="endpoint-url">/api/v1/fonts</span>
                            <span class="endpoint-tag">Agent & System</span>
                        </div>
                        <p>List all available fonts that can be used in template designs.</p>

                        <h4>Response</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "Arial",
            "family": "Arial, sans-serif",
            "category": "sans-serif"
        }
    ]
}</code></pre>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ================================================================= --}}
        {{-- 4.11 Formula Editor --}}
        {{-- ================================================================= --}}
        <section class="card doc-section" id="ep-formula">
            <div class="card-header"><h3>Formula Editor</h3></div>

            <p>Print Hub includes a formula engine for dynamic template content. Use these endpoints to list available functions, validate expressions, and evaluate formulas with context data.</p>

            <div class="endpoint-group">
                <h3 onclick="toggleSection(this)"><span class="expandable-arrow">▸</span> List Formula Functions</h3>
                <div class="expandable-content">
                    <div class="endpoint-block">
                        <div class="endpoint-header">
                            <span class="method method-get">GET</span>
                            <span class="endpoint-url">/api/v1/formula/functions</span>
                            <span class="endpoint-tag">Formula Editor</span>
                        </div>
                        <p>List all available formula functions with their signatures and descriptions.</p>

                        <h4>Response</h4>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>{
    "success": true,
    "data": [
        {
            "name": "UPPER",
            "description": "Convert text to uppercase",
            "signature": "UPPER(text: string): string"
        }
    ]
}</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            <div class="endpoint-group">
                <h3 onclick="toggleSection(this)"><span class="expandable-arrow">▸</span> Validate Formula</h3>
                <div class="expandable-content">
                    <div class="endpoint-block">
                        <div class="endpoint-header">
                            <span class="method method-post">POST</span>
                            <span class="endpoint-url">/api/v1/formula/validate</span>
                            <span class="endpoint-tag">Formula Editor</span>
                        </div>
                        <p>Validate a formula expression for syntax correctness.</p>

                        <h4>Request Body</h4>
                        <table>
                            <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>expression</code></td><td>string</td><td>Yes</td><td>The formula expression to validate</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="endpoint-group">
                <h3 onclick="toggleSection(this)"><span class="expandable-arrow">▸</span> Evaluate Formula</h3>
                <div class="expandable-content">
                    <div class="endpoint-block">
                        <div class="endpoint-header">
                            <span class="method method-post">POST</span>
                            <span class="endpoint-url">/api/v1/formula/evaluate</span>
                            <span class="endpoint-tag">Formula Editor</span>
                        </div>
                        <p>Evaluate a formula expression with given context variables.</p>

                        <h4>Request Body</h4>
                        <table>
                            <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>Description</th></tr></thead>
                            <tbody>
                                <tr><td><code>expression</code></td><td>string</td><td>Yes</td><td>The formula expression to evaluate</td></tr>
                                <tr><td><code>context</code></td><td>object</td><td>No</td><td>Context variables available to the formula</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        {{-- ================================================================= --}}
        {{-- 5. PRINT JOB FLOW --}}
        {{-- ================================================================= --}}
        <section class="card doc-section" id="print-flow">
            <div class="card-header"><h2>5. Print Job Flow — Step by Step</h2></div>

            <div class="flow-steps">
                <div class="flow-step">
                    <div class="flow-step-num">1</div>
                    <div class="flow-step-content">
                        <h4>Get an API Key</h4>
                        <p>Register a Client App in the admin panel. Copy the generated API key and store it securely.</p>
                    </div>
                </div>

                <div class="flow-step">
                    <div class="flow-step-num">2</div>
                    <div class="flow-step-content">
                        <h4>Design a Template</h4>
                        <p>Use the <strong>Template Designer</strong> in the admin panel to create a print template, or register a data schema with <a href="#ep-schema-register"><code>POST /schema</code></a> and bind it to a template. For raw document printing, skip this step.</p>
                    </div>
                </div>

                <div class="flow-step">
                    <div class="flow-step-num">3</div>
                    <div class="flow-step-content">
                        <h4>Look Up Available Queues / Agents</h4>
                        <p>Discover where to route your job:</p>
                        <ul>
                            <li><a href="#ep-branches"><code>GET /branches</code></a> — List available branches</li>
                            <li><a href="#ep-queues"><code>GET /queues</code></a> — List print queues with agent/printer info</li>
                            <li><a href="#ep-agents"><code>GET /agents/online</code></a> — Check which agents are currently online</li>
                        </ul>
                    </div>
                </div>

                <div class="flow-step">
                    <div class="flow-step-num">4</div>
                    <div class="flow-step-content">
                        <h4>Submit Print Job</h4>
                        <p>Send a <code>POST</code> request to <a href="#ep-print"><code>/print</code></a> with the template name, data, and target branch. The server validates the data against the template schema, generates the PDF, and queues the job for the appropriate agent.</p>
                        <div class="code-block-wrapper">
                            <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                            <pre class="code-block"><code>curl -X POST {{ config('app.url') }}/api/v1/print \
    -H "X-API-Key: your-api-key" \
    -H "Content-Type: application/json" \
    -d '{
        "template": "invoice_sewa",
        "data": { "no_invoice": "INV-001", "customer": "PT ABC", "total": 150000 },
        "branch_code": "SDP-SBY",
        "reference_id": "INV-001",
        "webhook_url": "https://myapp.com/webhook/print-status"
    }'</code></pre>
                        </div>
                    </div>
                </div>

                <div class="flow-step">
                    <div class="flow-step-num">5</div>
                    <div class="flow-step-content">
                        <h4>Poll Job Status (or Use Webhook)</h4>
                        <p>After submission, you can either:</p>
                        <ul>
                            <li><strong>Poll:</strong> Call <a href="#ep-jobs-status"><code>GET /jobs/{job_id}</code></a> at intervals until status is <code>success</code> or <code>failed</code>.</li>
                            <li><strong>Webhook:</strong> Provide a <code>webhook_url</code> in the print request. The server will POST a status update when the job completes.</li>
                        </ul>
                    </div>
                </div>

                <div class="flow-step">
                    <div class="flow-step-num">6</div>
                    <div class="flow-step-content">
                        <h4>Receive Status Update</h4>
                        <p>When the print agent completes (or fails) the job, the server updates the status. If you provided a webhook URL, your endpoint receives a payload with the final status, error details, and reference ID.</p>
                    </div>
                </div>
            </div>

            {{-- Multi-Dispatcher Scenario --}}
            <div class="tip-box" style="margin-top:1.5rem;">
                <strong>🏢 Multi-Branch / Multi-Dispatcher Scenario</strong>
                <p>In setups where each branch has multiple dispatchers, each with their own PC and printer, you can override which agent and printer handles each job by passing <code>agent_id</code> and <code>printer</code> in the request:</p>
                <div class="code-block-wrapper">
                    <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                    <pre class="code-block"><code>// Dispatcher A — routes to agent 1, printer "EPSON-LQ-2190"
curl -X POST {{ config('app.url') }}/api/v1/print \
    -H "X-API-Key: your-api-key" \
    -H "Content-Type: application/json" \
    -d '{
        "template": "invoice_sewa",
        "data": { "no_invoice": "INV-001", "customer": "PT ABC", "total": 150000 },
        "branch_code": "SDP-SBY",
        "agent_id": 1,
        "printer": "EPSON-LQ-2190",
        "queue": "sby-invoice"
    }'

// Dispatcher B — routes to agent 2, printer "HP-LaserJet-4000"
curl -X POST {{ config('app.url') }}/api/v1/print \
    -H "X-API-Key: your-api-key" \
    -H "Content-Type: application/json" \
    -d '{
        "template": "invoice_sewa",
        "data": { "no_invoice": "INV-002", "customer": "PT XYZ", "total": 250000 },
        "branch_code": "SDP-SBY",
        "agent_id": 2,
        "printer": "HP-LaserJet-4000",
        "queue": "sby-invoice"
    }'</code></pre>
                </div>
                <p><strong>How it works:</strong></p>
                <ol>
                    <li>Each dispatcher's PC runs a <strong>Print Agent</strong> with its own agent ID and locally connected printer.</li>
                    <li>Profiles are <strong>branch-scoped</strong> (not pinned to a specific agent), so all agents in the same branch see the same set of profiles.</li>
                    <li>The client app passes the correct <code>agent_id</code> and <code>printer</code> for the current dispatcher.</li>
                    <li>The server's <a href="#agent-selection"><code>AgentSelectionService</code></a> uses the explicit <code>agent_id</code> as highest priority, bypassing any profile pinning.</li>
                    <li>Only the targeted agent picks up the job from its queue and prints to the specified printer.</li>
                </ol>
                <p>See the <a href="#sdk-php-multi-dispatcher">PHP SDK example</a> for the programmatic approach.</p>
            </div>
        </section>

        {{-- ================================================================= --}}
        {{-- 6. TEMPLATE DESIGNER GUIDE --}}
        {{-- ================================================================= --}}
        <section class="card doc-section" id="template-guide">
            <div class="card-header"><h2>6. Template Designer Guide</h2></div>

            <p>Print Hub templates are PDF layouts designed using the built-in <strong>Template Designer</strong> in the admin panel. Templates define the visual structure of a printed document and use data schemas to bind dynamic content.</p>

            <h3>How Templates Work</h3>
            <p>A template consists of <strong>elements</strong> positioned on a virtual canvas with defined paper dimensions. When a print job is submitted with data, the rendering engine (<code>ContinuousFormEngine</code>) merges the data into the template to produce a PDF.</p>

            <h3>Element Types</h3>
            <table>
                <thead><tr><th>Element</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><span class="badge badge-info">Field</span></td><td>A single data value placed at a specific position (e.g. invoice number, customer name, date). Supports configurable font size, bold, border, and alignment.</td></tr>
                    <tr><td><span class="badge badge-info">Label</span></td><td>Static text that doesn't change between jobs (e.g. column headers, "Invoice", "Total").</td></tr>
                    <tr><td><span class="badge badge-info">Line</span></td><td>Horizontal or vertical separator lines for visual structure.</td></tr>
                    <tr><td><span class="badge badge-info">Image</span></td><td>A static image (e.g. company logo) embedded in the template.</td></tr>
                    <tr><td><span class="badge badge-info">Table</span></td><td>A dynamic table with defined columns that renders multiple rows of data. Tables auto-expand and support computed columns.</td></tr>
                </tbody>
            </table>

            <h3>Field Configuration</h3>
            <table>
                <thead><tr><th>Setting</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>key</code></td><td>The data key this field binds to (e.g. <code>no_invoice</code>)</td></tr>
                    <tr><td><code>x, y</code></td><td>Position in mm from top-left corner</td></tr>
                    <tr><td><code>width, height</code></td><td>Field dimensions in mm</td></tr>
                    <tr><td><code>font_size</code></td><td>Font size in points (default: 10)</td></tr>
                    <tr><td><code>bold</code></td><td>Whether text is bold</td></tr>
                    <tr><td><code>border</code></td><td>Whether to draw a border around the field</td></tr>
                    <tr><td><code>align</code></td><td>Text alignment: <code>L</code> (left), <code>C</code> (center), <code>R</code> (right)</td></tr>
                </tbody>
            </table>

            <h3>Data Schemas & Validation</h3>
            <p>Each template can be linked to a <strong>data schema</strong> that defines:</p>
            <ul>
                <li><strong>Required fields</strong> — data that must be provided (e.g. <code>no_invoice</code>)</li>
                <li><strong>Field types</strong> — <code>string</code>, <code>number</code>, or <code>date</code></li>
                <li><strong>Table definitions</strong> — column names, types, and minimum row counts</li>
            </ul>
            <p>When you submit a print job, the server validates your data against the template's schema. If validation fails, you'll receive a <code>422</code> error with details. You can bypass validation with <code>"skip_validation": true</code>.</p>

            <h3>Computed Columns</h3>
            <p>Tables can have computed columns that calculate values from other columns. For example, a <code>subtotal</code> column that multiplies <code>qty × price</code>. Computed columns are defined in the template designer and executed server-side during PDF generation.</p>

            <h3>Sample Data for Preview</h3>
            <p>When registering a schema via <a href="#ep-schema-register"><code>POST /schema</code></a>, you can include <code>sample_data</code>. This sample data can be used in the <a href="#ep-preview"><code>POST /preview</code></a> endpoint to test template layouts without printing.</p>

            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>// Example sample_data for an invoice schema
{
    "no_invoice": "INV-2026-0001",
    "customer": "PT Contoh Perusahaan",
    "date": "2026-05-04",
    "total": 2750000,
    "items": [
        { "description": "Web Design Service", "qty": 1, "price": 1500000 },
        { "description": "Hosting (12 months)", "qty": 1, "price": 750000 },
        { "description": "Domain Registration", "qty": 1, "price": 500000 }
    ]
}</code></pre>
            </div>
        </section>

        {{-- ================================================================= --}}
        {{-- 7. WEBHOOKS --}}
        {{-- ================================================================= --}}
        <section class="card doc-section" id="webhooks">
            <div class="card-header"><h2>7. Webhooks</h2></div>

            <p>Print Hub supports asynchronous status notifications via webhooks. When you submit a print job with a <code>webhook_url</code>, the server will POST a status update to your URL when the agent completes or fails the job.</p>

            <h3>How to Configure</h3>
            <p>Simply include a <code>webhook_url</code> field in your <a href="#ep-print"><code>POST /print</code></a> request body:</p>
            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>curl -X POST {{ config('app.url') }}/api/v1/print \
    -H "X-API-Key: your-api-key" \
    -H "Content-Type: application/json" \
    -d '{
        "template": "invoice_sewa",
        "data": { "no_invoice": "INV-001", "customer": "PT ABC", "total": 150000 },
        "branch_code": "SDP-SBY",
        "webhook_url": "https://myapp.com/webhook/print-status"
    }'</code></pre>
            </div>

            <h3>Webhook Payload</h3>
            <p>The server sends a <code>POST</code> request to your webhook URL with the following JSON payload:</p>
            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>{
    "reference_id": "INV-001",
    "job_id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "success",
    "error": null
}</code></pre>
            </div>

            <h4>Payload Fields</h4>
            <table>
                <thead><tr><th>Field</th><th>Type</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>reference_id</code></td><td>string|null</td><td>Your external reference ID (if provided)</td></tr>
                    <tr><td><code>job_id</code></td><td>string (UUID)</td><td>The Print Hub job UUID</td></tr>
                    <tr><td><code>status</code></td><td>string</td><td>One of: <code>success</code>, <code>failed</code></td></tr>
                    <tr><td><code>error</code></td><td>string|null</td><td>Error message if the job failed</td></tr>
                </tbody>
            </table>

            <h3>Retry Behavior</h3>
            <p>Webhook delivery is attempted once with a <strong>5-second timeout</strong>. If your endpoint is unreachable or returns a non-2xx response, the webhook delivery fails silently and the error is logged server-side. There is currently no automatic retry mechanism; you should implement a fallback polling strategy using <a href="#ep-jobs-status"><code>GET /jobs/{job_id}</code></a>.</p>

            <div class="tip-box warning">
                <strong>⚠️ Important:</strong> Your webhook endpoint should respond quickly (<code>200 OK</code> within 5 seconds). Do not perform long-running operations in your webhook handler — process the notification asynchronously (e.g. by dispatching a job queue task).
            </div>

            <h3>Webhook Security</h3>
            <p>The webhook payload is not cryptographically signed. If you need to verify that the request came from Print Hub, you can:</p>
            <ul>
                <li>Check the <code>job_id</code> matches a known job you submitted.</li>
                <li>Use the <code>reference_id</code> to correlate the webhook with your internal record.</li>
                <li>Optionally, ignore the webhook and use polling as the authoritative source of truth.</li>
            </ul>
        </section>

        {{-- ================================================================= --}}
        {{-- 8. ERROR REFERENCE --}}
        {{-- ================================================================= --}}
        <section class="card doc-section" id="errors">
            <div class="card-header"><h2>8. Error Reference</h2></div>

            <h3>Response Envelope</h3>
            <p>All API responses follow a standard envelope format:</p>

            <h4>Success</h4>
            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>{
    "success": true,
    "data": { ... }
}</code></pre>
            </div>

            <h4>Error</h4>
            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>{
    "success": false,
    "error": {
        "code": "ERROR_CODE",
        "message": "Human-readable description of the error."
    }
}</code></pre>
            </div>

            <h3>HTTP Status Codes</h3>
            <table>
                <thead><tr><th>Status</th><th>Meaning</th></tr></thead>
                <tbody>
                    <tr><td><code>200</code></td><td>Success — request completed normally</td></tr>
                    <tr><td><code>201</code></td><td>Created — resource was created (e.g. schema registered)</td></tr>
                    <tr><td><code>202</td></td><td>Accepted — job has been queued (not yet complete)</td></tr>
                    <tr><td><code>400</code></td><td>Bad Request — validation error or missing parameter</td></tr>
                    <tr><td><code>401</code></td><td>Unauthorized — missing or invalid API key</td></tr>
                    <tr><td><code>404</code></td><td>Not Found — resource (template, branch, job) not found</td></tr>
                    <tr><td><code>409</code></td><td>Conflict — operation not allowed in current state (e.g. cancel processing job)</td></tr>
                    <tr><td><code>422</code></td><td>Unprocessable Entity — data failed schema validation</td></tr>
                    <tr><td><code>429</code></td><td>Too Many Requests — rate limit exceeded</td></tr>
                    <tr><td><code>500</code></td><td>Internal Server Error — unexpected server issue</td></tr>
                    <tr><td><code>503</code></td><td>Service Unavailable — no online agents available</td></tr>
                </tbody>
            </table>

            <h3>Error Codes</h3>
            <table>
                <thead><tr><th>Code</th><th>HTTP Status</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>MISSING_API_KEY</code></td><td>401</td><td><code>X-API-Key</code> header was not provided</td></tr>
                    <tr><td><code>INVALID_API_KEY</code></td><td>401</td><td>API key not found or the client app is inactive</td></tr>
                    <tr><td><code>INVALID_AGENT_KEY</code></td><td>401</td><td>Agent Bearer token is invalid (agent API)</td></tr>
                    <tr><td><code>TEMPLATE_NOT_FOUND</code></td><td>404</td><td>The specified template name does not exist</td></tr>
                    <tr><td><code>SCHEMA_NOT_FOUND</code></td><td>404</td><td>The specified schema name does not exist</td></tr>
                    <tr><td><code>BRANCH_NOT_FOUND</code></td><td>404</td><td>The specified branch code or ID was not found</td></tr>
                    <tr><td><code>JOB_NOT_FOUND</code></td><td>404</td><td>The specified job ID does not exist</td></tr>
                    <tr><td><code>NO_AGENT_AVAILABLE</code></td><td>503</td><td>No online print agent is available to handle the job in the target branch</td></tr>
                    <tr><td><code>AGENT_OFFLINE</code></td><td>503</td><td>The pinned agent (by agent_id) is currently offline</td></tr>
                    <tr><td><code>VALIDATION_FAILED</code></td><td>422</td><td>Request payload failed validation (includes details)</td></tr>
                    <tr><td><code>JOB_NOT_CANCELLABLE</code></td><td>409</td><td>Job is not in <code>pending</code> status, cannot be cancelled</td></tr>
                    <tr><td><code>BATCH_FAILED</code></td><td>422</td><td>One or more jobs in a batch failed (all rolled back)</td></tr>
                    <tr><td><code>INVALID_DOCUMENT</code></td><td>400</td><td>Base64 document could not be decoded or is invalid</td></tr>
                </tbody>
            </table>
        </section>

        {{-- ================================================================= --}}
        {{-- 9. SDK CLIENT (PHP) --}}
        {{-- ================================================================= --}}
        <section class="card doc-section" id="sdk-php">
            <div class="card-header"><h2>9. SDK — PHP</h2></div>

            <p>Print Hub provides a <strong>PHP SDK client</strong> for easy integration with PHP applications. The SDK is a single-file class that wraps the REST API with convenient methods, caching, retry logic, and schema validation.</p>

            <h3>Installation</h3>
            <p>The SDK requires PHP 8.1+ and the <a href="https://docs.guzzlephp.org" target="_blank">Guzzle HTTP client</a>.</p>
            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code># Install Guzzle via Composer
composer require guzzlehttp/guzzle

# Download PrintHubClient.php from the admin panel:
# Client Apps → Download SDK</code></pre>
            </div>

            <h3>Initialization</h3>
            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>&lt;?php
require_once 'PrintHubClient.php';

$printHub = new PrintHubClient(
    baseUrl: '{{ config('app.url') }}',  // Your Print Hub server URL
    apiKey:  'your-api-key-here',        // From Client Apps page
    timeout: 15,                          // Request timeout (seconds)
    cacheDir: '/tmp',                     // Schema cache directory
    cacheTtl: 600,                        // Schema cache TTL (default 600s)
    maxRetries: 2,                        // Retry on transient failures
    retryDelayMs: 200,                    // Initial retry delay (doubles each attempt)
);

// (Optional) Set a default branch for all subsequent calls
$printHub->setBranch('SDP-SBY');</code></pre>
            </div>

            <h3>Constructor Parameters</h3>
            <table>
                <thead><tr><th>Parameter</th><th>Type</th><th>Default</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>$baseUrl</code></td><td>string</td><td>—</td><td>Print Hub server URL (required)</td></tr>
                    <tr><td><code>$apiKey</code></td><td>string</td><td>—</td><td>Client app API key (required)</td></tr>
                    <tr><td><code>$timeout</code></td><td>int</td><td><code>15</code></td><td>Request timeout in seconds</td></tr>
                    <tr><td><code>$cacheDir</code></td><td>string</td><td><code>/tmp</code></td><td>Directory for caching template schemas</td></tr>
                    <tr><td><code>$cacheTtl</code></td><td>int</td><td><code>600</code></td><td>Schema cache TTL in seconds</td></tr>
                    <tr><td><code>$maxRetries</code></td><td>int</td><td><code>2</code></td><td>Max retries on network/server errors</td></tr>
                    <tr><td><code>$retryDelayMs</code></td><td>int</td><td><code>200</code></td><td>Initial retry delay (exponential backoff)</td></tr>
                    <tr><td><code>$logger</code></td><td>LoggerInterface|null</td><td><code>null</code></td><td>PSR-3 logger for debugging</td></tr>
                </tbody>
            </table>

            <h3>Available Methods</h3>

            <div class="method-block">
                <div class="method-sig"><code>setBranch(string $branchCode): self</code></div>
                <p>Set the default branch for all subsequent calls. Can be overridden per-call by passing <code>branchCode</code> to individual methods.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getBranchCode(): ?string</code></div>
                <p>Returns the currently configured default branch code.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>testConnection(): array</code></div>
                <p>Test connection to Print Hub. Returns server info and online agent count.</p>
                <div class="code-block-wrapper">
                    <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                    <pre class="code-block"><code>$result = $printHub->testConnection();
// ['message' => 'Connected successfully.', 'app_name' => '...', 'agents' => 3]</code></pre>
                </div>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getBranches(): array</code></div>
                <p>List all active branches.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getOnlineAgents(?string $branchCode = null): array</code></div>
                <p>List online agents, optionally filtered by branch.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getQueues(?string $branchCode = null, bool $detailed = false): array</code></div>
                <p>List print queues. Use <code>$detailed = true</code> for full printer configuration details.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getTemplates(): array</code></div>
                <p>List all available print templates.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getTemplate(string $name): array</code></div>
                <p>Get detailed info for a specific template, including field positions and dimensions.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getTemplateSchema(string $name, bool $useCache = true): array</code></div>
                <p>Get the required data schema for a template. Schemas are cached locally for <code>$cacheTtl</code> seconds.</p>
                <div class="code-block-wrapper">
                    <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                    <pre class="code-block"><code>$schema = $printHub->getTemplateSchema('invoice_sewa');
// Clears cache for a specific template:
$printHub->clearCache('invoice_sewa');
// Clears all cached schemas:
$printHub->clearCache();</code></pre>
                </div>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>registerSchema(string $schemaName, array $schemaData): array</code></div>
                <p>Register or update a data schema for template data binding.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>validateData(string $templateName, array $data): array</code></div>
                <p>Validate data against a template's schema client-side. Returns an array of error messages (empty = valid).</p>
                <div class="code-block-wrapper">
                    <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                    <pre class="code-block"><code>$errors = $printHub->validateData('invoice_sewa', $data);
if (!empty($errors)) {
    foreach ($errors as $err) echo "⚠️ $err\n";
}</code></pre>
                </div>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>printWithTemplate(string $template, array $data, string $referenceId = '', string $queue = '', ?string $branchCode = null, array $options = [], array $parameters = [], ?int $poolId = null, ?int $documentId = null, ?string $webhookUrl = null, ?int $agentId = null, ?string $printer = null, ?int $branchId = null, ?int $priority = null, ?string $scheduledAt = null, ?string $recurrence = null, ?string $recurrenceEndAt = null, ?int $recurrenceCount = null): array</code></div>
                <p>The primary method for template-based printing. Automatically validates data against the template schema before submitting. Supports printer pool selection, scheduling, webhook notifications, and agent pinning.</p>
                <div class="code-block-wrapper">
                    <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                    <pre class="code-block"><code>$result = $printHub->printWithTemplate(
    template:    'invoice_sewa',
    data:        ['no_invoice' => 'INV-001', 'customer' => 'PT ABC', 'total' => 150000],
    referenceId: 'INV-001',
    branchCode:  'SDP-SBY',
    options:     ['copies' => 2, 'color_mode' => 'monochrome'],
    poolId:      1,
    webhookUrl:  'https://myapp.com/webhook/print-status',
    priority:    5,
);
// ['status' => 'queued', 'job_id' => '...', 'agent' => 'PC-SBY-01', ...]</code></pre>
                </div>
            </div>

            {{-- Multi-Dispatcher Example --}}
            <div class="method-block" id="sdk-php-multi-dispatcher">
                <div class="method-sig"><code>Multi-Dispatcher Example</code></div>
                <p>In a multi-branch setup where each dispatcher has their own PC and printer, pass <code>agent_id</code> and <code>printer</code> in the <code>$options</code> array to route jobs to the correct agent:</p>
                <div class="code-block-wrapper">
                    <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                    <pre class="code-block"><code>&lt;?php
// Each dispatcher's client app knows its own agent ID and printer name.
// Profiles are branch-scoped, so all agents in the same branch see the same queues.

// Dispatcher A — routes to agent 1, printer "EPSON-LQ-2190"
$resultA = $printHub->printWithTemplate(
    template:    'invoice_sewa',
    data:        ['no_invoice' => 'INV-001', 'customer' => 'PT ABC', 'total' => 150000],
    referenceId: 'INV-001',
    queue:       'sby-invoice',
    branchCode:  'SDP-SBY',
    options:     [
        'agent_id' => 1,
        'printer'  => 'EPSON-LQ-2190',
    ]
);

// Dispatcher B — routes to agent 2, printer "HP-LaserJet-4000"
$resultB = $printHub->printWithTemplate(
    template:    'invoice_sewa',
    data:        ['no_invoice' => 'INV-002', 'customer' => 'PT XYZ', 'total' => 250000],
    referenceId: 'INV-002',
    queue:       'sby-invoice',
    branchCode:  'SDP-SBY',
    options:     [
        'agent_id' => 2,
        'printer'  => 'HP-LaserJet-4000',
    ]
);

// Each job is queued only to the specified agent's queue.
// The agent picks it up and prints to the specified printer.</code></pre>
                </div>
                <p><strong>Agent Selection Priority:</strong></p>
                <ol>
                    <li><strong>Explicit <code>agent_id</code></strong> (from <code>$options</code>) — highest priority</li>
                    <li><strong>Profile's pinned agent</strong> (<code>print_agent_id</code>) — used if no explicit agent_id given</li>
                    <li><strong>Any online agent in the branch</strong> — fallback if no agent specified and profile has no pinned agent</li>
                    <li><strong>Any online agent globally</strong> — last resort</li>
                </ol>
                <p>See the <a href="#print-flow">Print Job Flow</a> section for the cURL equivalent.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>printRawPdf(string $documentBase64, string $referenceId = '', string $queue = '', ?string $branchCode = null, array $options = [], ?int $poolId = null, ?int $documentId = null, ?string $webhookUrl = null, ?int $agentId = null, ?string $printer = null, ?int $branchId = null, ?int $priority = null, ?string $scheduledAt = null, ?string $recurrence = null, ?string $recurrenceEndAt = null, ?int $recurrenceCount = null): array</code></div>
                <p>Print a raw base64-encoded PDF document without using a template.</p>
                <div class="code-block-wrapper">
                    <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                    <pre class="code-block"><code>$pdfBase64 = base64_encode(file_get_contents('report.pdf'));
$result = $printHub->printRawPdf($pdfBase64, referenceId: 'RPT-001', poolId: 1);</code></pre>
                </div>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>printAsync(string $template, array $data, string $referenceId = '', string $queue = '', ?string $branchCode = null, array $options = [], array $parameters = [], ?int $poolId = null, ?int $documentId = null, ?string $webhookUrl = null, ?int $agentId = null, ?string $printer = null, ?int $branchId = null, ?int $priority = null, ?string $scheduledAt = null, ?string $recurrence = null, ?string $recurrenceEndAt = null, ?int $recurrenceCount = null): PromiseInterface</code></div>
                <p>Same as <code>printWithTemplate</code> but returns a Guzzle Promise for non-blocking execution.</p>
                <div class="code-block-wrapper">
                    <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                    <pre class="code-block"><code>$promise = $printHub->printAsync('invoice_sewa', $data, referenceId: 'INV-001');
$promise->then(function ($result) {
    echo "Queued: " . $result['job_id'];
});</code></pre>
                </div>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>printBatch(array $jobs): array</code></div>
                <p>Submit multiple print jobs in a single request. Up to 50 jobs. Automatically fills in the default branch for jobs that don't specify one.</p>
                <div class="code-block-wrapper">
                    <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                    <pre class="code-block"><code>$result = $printHub->printBatch([
    ['template' => 'invoice_sewa', 'data' => $inv1, 'reference_id' => 'INV-001'],
    ['template' => 'invoice_sewa', 'data' => $inv2, 'reference_id' => 'INV-002'],
    ['template' => 'receipt',      'data' => $recpt, 'branch_code' => 'SDP-JKT'],
]);</code></pre>
                </div>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>preview(string $template, array $data, array $options = []): string</code></div>
                <p>Generate a PDF preview without sending to a printer. Returns the raw PDF binary content.</p>
                <div class="code-block-wrapper">
                    <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                    <pre class="code-block"><code>$pdfBytes = $printHub->preview('invoice_sewa', $data);
file_put_contents('preview.pdf', $pdfBytes);</code></pre>
                </div>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>jobStatus(string $jobId): array</code></div>
                <p>Check the current status of a print job.</p>
                <div class="code-block-wrapper">
                    <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                    <pre class="code-block"><code>$status = $printHub->jobStatus('abc-123');
// ['job_id' => '...', 'status' => 'success', 'printer' => 'HP LaserJet', ...]</code></pre>
                </div>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>waitForJob(string $jobId, int $timeoutSeconds = 30, int $pollIntervalMs = 500): array</code></div>
                <p>Poll until a job reaches a terminal status (<code>success</code> or <code>failed</code>). Throws an exception on timeout.</p>
                <div class="code-block-wrapper">
                    <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                    <pre class="code-block"><code>try {
    $result = $printHub->waitForJob('abc-123', timeoutSeconds: 60);
    echo "Final status: " . $result['status'];
} catch (PrintHubException $e) {
    echo "Timeout: " . $e->getMessage();
}</code></pre>
                </div>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>listSchemas(): array</code></div>
                <p>List all registered data schemas with pagination.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>schemaVersions(string $name): array</code></div>
                <p>Get version history for a specific schema.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>schemaVersionDiff(string $name, int $fromVersion, int $toVersion): array</code></div>
                <p>Get the diff between two versions of a schema.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>validateTemplateData(string $templateName, array $data): array</code></div>
                <p>Validate data against a template's schema server-side.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>health(): array</code></div>
                <p>Get system health information (online agents, pending jobs).</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>uploadDocument(string $filename, string $base64Data, ?string $mimeType = null): array</code></div>
                <p>Upload a document for later use in print jobs.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>listDocuments(): array</code></div>
                <p>List all uploaded documents.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getDocument(int $id): array</code></div>
                <p>Get document metadata by ID.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>previewDocument(int $id): string</code></div>
                <p>Get a preview (thumbnail) of a document. Returns raw PDF binary.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>downloadDocument(int $id): string</code></div>
                <p>Download the original document file. Returns raw binary content.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>deleteDocument(int $id): array</code></div>
                <p>Delete an uploaded document.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getPendingApprovals(): array</code></div>
                <p>Get all print jobs pending approval.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>approveJob(string $jobId): array</code></div>
                <p>Approve a pending print job.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>rejectJob(string $jobId): array</code></div>
                <p>Reject a pending print job.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getAgentVersion(): array</code></div>
                <p>Get the latest available TrayPrint agent version.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getFonts(): array</code></div>
                <p>List all available fonts for template design.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getFormulaFunctions(): array</code></div>
                <p>Get all available formula functions for computed columns.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>validateFormula(string $expression): array</code></div>
                <p>Validate a formula expression syntax.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>evaluateFormula(string $expression, array $context = []): array</code></div>
                <p>Evaluate a formula expression with sample context data.</p>
            </div>

            <h3>Exception Classes</h3>
            <table>
                <thead><tr><th>Exception</th><th>When Thrown</th></tr></thead>
                <tbody>
                    <tr><td><code>PrintHubException</code></td><td>Base exception for all SDK errors (4xx/5xx responses, invalid JSON)</td></tr>
                    <tr><td><code>PrintHubConnectionException</code></td><td>Network-level errors (connection refused, DNS failure, timeout)</td></tr>
                    <tr><td><code>PrintHubValidationException</code></td><td>Schema validation failed — check <code>$e->errors</code> for details</td></tr>
                </tbody>
            </table>

            <h3>Laravel Integration Example</h3>
            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>// config/services.php
'printhub' => [
    'url'         => env('PRINTHUB_URL', '{{ config('app.url') }}'),
    'key'         => env('PRINTHUB_API_KEY'),
    'branch_code' => env('PRINTHUB_BRANCH', 'SDP-MAIN'),
],

// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->singleton(PrintHubClient::class, function () {
        $client = new PrintHubClient(
            baseUrl: config('services.printhub.url'),
            apiKey:  config('services.printhub.key'),
        );
        if ($branch = config('services.printhub.branch_code')) {
            $client->setBranch($branch);
        }
        return $client;
    });
}

// Usage in a controller:
public function print(Invoice $invoice, PrintHubClient $printHub)
{
    $result = $printHub->printWithTemplate(
        template:    'invoice_sewa',
        data:        $invoice->toPrintData(),
        referenceId: (string) $invoice->id,
    );
    return back()->with('success', "Print queued: {$result['job_id']}");
}</code></pre>
            </div>

            <h3>Error Handling</h3>
            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>try {
    $result = $printHub->printWithTemplate('invoice_sewa', $data);
} catch (PrintHubValidationException $e) {
    // Schema validation failed — check $e->errors
    foreach ($e->errors as $err) {
        Log::warning("Validation: $err");
    }
} catch (PrintHubConnectionException $e) {
    // Network error — Print Hub unreachable
    Log::error("Print Hub offline: " . $e->getMessage());
    // → Queue for retry / notify admin
} catch (PrintHubException $e) {
    // API error (4xx/5xx) — invalid API key, template not found, etc.
    Log::error("Print Hub error: " . $e->getMessage());
}</code></pre>
            </div>
        </section>

        {{-- ================================================================= --}}
        {{-- 10. SDK — Python --}}
        {{-- ================================================================= --}}
        <section class="card doc-section" id="sdk-python">
            <div class="card-header"><h2>10. SDK — Python</h2></div>

            <p>Print Hub provides a <strong>Python SDK client</strong> for Python 3.8+ applications. The SDK is a single-file module that wraps the REST API with convenient methods, automatic retry with exponential backoff, and custom exception classes.</p>

            <h3>Installation</h3>
            <p>Requires the <code>requests</code> library.</p>
            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>pip install requests

# Download PrintHubClient.py:
# {{ asset('sdk/PrintHubClient.py') }}</code></pre>
            </div>

            <h3>Initialization</h3>
            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>from printhub_client import PrintHubClient

client = PrintHubClient(
    base_url="https://print-hub.example.com",
    api_key="your-api-key-here",
    timeout=15,
    max_retries=2,
    retry_delay_ms=200,
)

# (Optional) Set a default branch
client.set_branch("SDP-SBY")</code></pre>
            </div>

            <h3>Constructor Parameters</h3>
            <table>
                <thead><tr><th>Parameter</th><th>Type</th><th>Default</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>base_url</code></td><td>str</td><td>—</td><td>Print Hub server URL (required)</td></tr>
                    <tr><td><code>api_key</code></td><td>str</td><td>—</td><td>Client app API key (required)</td></tr>
                    <tr><td><code>timeout</code></td><td>int</td><td><code>15</code></td><td>Request timeout in seconds</td></tr>
                    <tr><td><code>max_retries</code></td><td>int</td><td><code>2</code></td><td>Max retries on transient failures</td></tr>
                    <tr><td><code>retry_delay_ms</code></td><td>int</td><td><code>200</code></td><td>Initial retry delay (exponential backoff)</td></tr>
                </tbody>
            </table>

            <h3>Available Methods</h3>

            <div class="method-block">
                <div class="method-sig"><code>set_branch(branch_code) → PrintHubClient</code></div>
                <p>Set the default branch for all subsequent calls.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>get_branch_code() → str | None</code></div>
                <p>Returns the currently configured default branch code.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>test_connection() → dict</code></div>
                <p>Test connection to Print Hub.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>health() → dict</code></div>
                <p>Get system health information.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>get_branches() → list</code></div>
                <p>List all active branches.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>get_online_agents(branch_code=None) → list</code></div>
                <p>List online agents, optionally filtered by branch.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>get_queues(branch_code=None, detailed=False) → list</code></div>
                <p>List print queues.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>get_templates() → list</code></div>
                <p>List all available print templates.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>get_template(name) → dict</code></div>
                <p>Get detailed info for a specific template.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>get_template_schema(name) → dict</code></div>
                <p>Get the required data schema for a template.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>register_schema(schema_name, schema_data) → dict</code></div>
                <p>Register or update a data schema.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>list_schemas() → list</code></div>
                <p>List all registered data schemas.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>schema_versions(schema_name) → list</code></div>
                <p>Get version history for a schema.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>print_with_template(template, data, reference_id='', branch_code=None, options=None, parameters=None, pool_id=None, document_id=None, webhook_url=None, agent_id=None, printer=None, branch_id=None, priority=None, scheduled_at=None, recurrence=None, recurrence_end_at=None, recurrence_count=None) → dict</code></div>
                <p>The primary method for template-based printing. Supports printer pool selection, scheduling, webhook notifications, and agent pinning.</p>
                <div class="code-block-wrapper">
                    <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                    <pre class="code-block"><code>result = client.print_with_template(
    template="invoice_sewa",
    data={"no_invoice": "INV-001", "customer": "PT ABC", "total": 150000},
    reference_id="INV-001",
    branch_code="SDP-SBY",
    options={"copies": 2, "color_mode": "monochrome"},
    pool_id=1,
    webhook_url="https://myapp.com/webhook/print-status",
)
print(f"Job queued: {result['job_id']}")</code></pre>
                </div>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>print_raw_pdf(document_base64, reference_id='', branch_code=None, options=None, printer=None, pool_id=None, document_id=None, webhook_url=None, agent_id=None, branch_id=None, priority=None, scheduled_at=None, recurrence=None, recurrence_end_at=None, recurrence_count=None) → dict</code></div>
                <p>Print a raw base64-encoded PDF without using a template.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>print_batch(jobs) → dict</code></div>
                <p>Submit up to 50 print jobs in a single request.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>preview(template, data, options=None) → bytes</code></div>
                <p>Generate a PDF preview. Returns raw PDF binary content.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>job_status(job_id) → dict</code></div>
                <p>Check the current status of a print job.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>cancel_job(job_id) → dict</code></div>
                <p>Cancel a pending print job.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>wait_for_job(job_id, timeout_seconds=30, poll_interval_ms=500) → dict</code></div>
                <p>Poll until a job reaches a terminal status.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>schema_version_diff(schema_name, from_version, to_version) → dict</code></div>
                <p>Get the diff between two versions of a schema.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>validate_template_data(template_name, data) → dict</code></div>
                <p>Validate data against a template's schema server-side.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>upload_document(filename, file_data, mime_type=None) → dict</code></div>
                <p>Upload a document for later use in print jobs.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>list_documents() → dict</code></div>
                <p>List all uploaded documents.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>get_document(doc_id) → dict</code></div>
                <p>Get document metadata by ID.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>preview_document(doc_id) → bytes</code></div>
                <p>Get a preview (thumbnail) of a document. Returns raw PDF binary.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>download_document(doc_id) → bytes</code></div>
                <p>Download the original document file. Returns raw binary content.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>delete_document(doc_id) → dict</code></div>
                <p>Delete an uploaded document.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>register_connector(name, connector_type, config, icon=None) → dict</code></div>
                <p>Register a new data-source connector.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>list_connectors() → list</code></div>
                <p>List all registered connectors.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>update_connector(connector_id, data) → dict</code></div>
                <p>Update an existing connector's configuration.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>test_connector(connector_id) → dict</code></div>
                <p>Test a connector's connection.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>delete_connector(connector_id) → dict</code></div>
                <p>Delete a connector.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>get_pending_approvals() → dict</code></div>
                <p>Get all print jobs pending approval.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>approve_job(job_id) → dict</code></div>
                <p>Approve a pending print job.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>reject_job(job_id) → dict</code></div>
                <p>Reject a pending print job.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>get_agent_version() → dict</code></div>
                <p>Get the latest available TrayPrint agent version.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>get_fonts() → list</code></div>
                <p>List all available fonts for template design.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>get_formula_functions() → list</code></div>
                <p>Get all available formula functions for computed columns.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>validate_formula(expression) → dict</code></div>
                <p>Validate a formula expression syntax.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>evaluate_formula(expression, context=None) → dict</code></div>
                <p>Evaluate a formula expression with sample context data.</p>
            </div>

            <h3>Exception Classes</h3>
            <table>
                <thead><tr><th>Exception</th><th>When Thrown</th></tr></thead>
                <tbody>
                    <tr><td><code>PrintHubError</code></td><td>Base exception for all SDK errors</td></tr>
                    <tr><td><code>PrintHubConnectionError</code></td><td>Network-level errors (connection refused, timeout, DNS failure)</td></tr>
                    <tr><td><code>PrintHubValidationError</code></td><td>Schema validation failed — check <code>.errors</code> for details</td></tr>
                </tbody>
            </table>

            <h3>Example Usage</h3>
            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>from printhub_client import PrintHubClient, PrintHubError, PrintHubValidationError

client = PrintHubClient(
    base_url="https://print-hub.example.com",
    api_key="your-api-key-here",
)

try:
    # Check connection
    status = client.test_connection()
    print(f"Connected: {status['message']}")

    # Print a document
    result = client.print_with_template(
        template="invoice_sewa",
        data={"no_invoice": "INV-001", "customer": "PT ABC", "total": 150000},
        reference_id="INV-001",
        options={"copies": 1, "color_mode": "monochrome"},
    )
    print(f"Job ID: {result['job_id']}")

    # Wait for completion
    final = client.wait_for_job(result['job_id'], timeout_seconds=60)
    print(f"Final status: {final['status']}")

except PrintHubValidationError as e:
    print(f"Validation errors: {e.errors}")
except PrintHubError as e:
    print(f"Error: {e}")</code></pre>
            </div>
        </section>

        {{-- ================================================================= --}}
        {{-- 11. SDK — Node.js --}}
        {{-- ================================================================= --}}
        <section class="card doc-section" id="sdk-nodejs">
            <div class="card-header"><h2>11. SDK — Node.js (ESM)</h2></div>

            <p>Print Hub provides a <strong>Node.js SDK client</strong> in ESM format. Requires Node.js 18+ (native <code>fetch</code>) or 16+ with <code>node-fetch</code>. The SDK uses modern <code>async/await</code>, automatic retry with exponential backoff, and typed error classes.</p>

            <h3>Installation</h3>
            <p>No external dependencies required for Node.js 18+. For Node.js 16, install <code>node-fetch</code>:</p>
            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>npm install node-fetch  # Only for Node.js 16

# Download PrintHubClient.mjs:
# {{ asset('sdk/PrintHubClient.mjs') }}</code></pre>
            </div>

            <h3>Initialization</h3>
            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>import { PrintHubClient } from './PrintHubClient.mjs';

const client = new PrintHubClient({
    baseUrl: 'https://print-hub.example.com',
    apiKey:  'your-api-key-here',
    timeout: 15,
    maxRetries: 2,
    retryDelayMs: 200,
});

// (Optional) Set a default branch
client.setBranch('SDP-SBY');</code></pre>
            </div>

            <h3>Constructor Options</h3>
            <table>
                <thead><tr><th>Option</th><th>Type</th><th>Default</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>baseUrl</code></td><td>string</td><td>—</td><td>Print Hub server URL (required)</td></tr>
                    <tr><td><code>apiKey</code></td><td>string</td><td>—</td><td>Client app API key (required)</td></tr>
                    <tr><td><code>timeout</code></td><td>number</td><td><code>15</code></td><td>Request timeout in seconds</td></tr>
                    <tr><td><code>maxRetries</code></td><td>number</td><td><code>2</code></td><td>Max retries on transient failures</td></tr>
                    <tr><td><code>retryDelayMs</code></td><td>number</td><td><code>200</code></td><td>Initial retry delay (exponential backoff)</td></tr>
                </tbody>
            </table>

            <h3>Available Methods</h3>

            <div class="method-block">
                <div class="method-sig"><code>setBranch(branchCode) → PrintHubClient</code></div>
                <p>Set the default branch for all subsequent calls.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getBranchCode() → string | null</code></div>
                <p>Returns the currently configured default branch code.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>testConnection() → Promise<object></code></div>
                <p>Test connection to Print Hub.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>health() → Promise<object></code></div>
                <p>Get system health information.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getBranches() → Promise<array></code></div>
                <p>List all active branches.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getOnlineAgents(branchCode?) → Promise<array></code></div>
                <p>List online agents, optionally filtered by branch.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getQueues({ branchCode, detailed }) → Promise<array></code></div>
                <p>List print queues.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getTemplates() → Promise<array></code></div>
                <p>List all available print templates.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getTemplate(name) → Promise<object></code></div>
                <p>Get detailed info for a specific template.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getTemplateSchema(name) → Promise<object></code></div>
                <p>Get the required data schema for a template.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>registerSchema(schemaName, schemaData) → Promise<object></code></div>
                <p>Register or update a data schema.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>listSchemas() → Promise<array></code></div>
                <p>List all registered data schemas.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>schemaVersions(schemaName) → Promise<array></code></div>
                <p>Get version history for a schema.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>printWithTemplate({ template, data, referenceId, branchCode, options, parameters, poolId, documentId, webhookUrl, agentId, printer, branchId, priority, scheduledAt, recurrence, recurrenceEndAt, recurrenceCount }) → Promise<object></code></div>
                <p>The primary method for template-based printing. Supports scheduling, printer pools, document attachment, approval workflow, and recurrence.</p>
                <div class="code-block-wrapper">
                    <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                    <pre class="code-block"><code>const result = await client.printWithTemplate({
    template: 'invoice_sewa',
    data: { no_invoice: 'INV-001', customer: 'PT ABC', total: 150000 },
    referenceId: 'INV-001',
    branchCode: 'SDP-SBY',
    options: { copies: 2, color_mode: 'monochrome' },
    parameters: { company_name: 'PT ABC' },
    poolId: 1,
    priority: 5,
    scheduledAt: '2026-05-10T08:00:00Z',
    recurrence: 'daily',
    recurrenceCount: 5,
});
console.log(`Job queued: ${result.job_id}`);</code></pre>
                </div>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>printRawPdf({ documentBase64, referenceId, branchCode, options, printer, poolId, documentId, webhookUrl, agentId, branchId, priority, scheduledAt, recurrence, recurrenceEndAt, recurrenceCount }) → Promise<object></code></div>
                <p>Print a raw base64-encoded PDF without using a template. Supports the same scheduling, pool, and priority options as <code>printWithTemplate</code>.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>printBatch(jobs) → Promise<object></code></div>
                <p>Submit up to 50 print jobs in a single request.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>preview(template, data, options, parameters) → Promise<Buffer></code></div>
                <p>Generate a PDF preview. Returns a Buffer with the PDF content. Accepts optional runtime parameters.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>jobStatus(jobId) → Promise<object></code></div>
                <p>Check the current status of a print job.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>cancelJob(jobId) → Promise<object></code></div>
                <p>Cancel a pending print job.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>waitForJob(jobId, timeoutSeconds=30, pollIntervalMs=500) → Promise<object></code></div>
                <p>Poll until a job reaches a terminal status.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>schemaVersionDiff(schemaName, fromVersion, toVersion) → Promise<object></code></div>
                <p>Get the diff between two versions of a data schema.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>validateTemplateData(templateName, data) → Promise<object></code></div>
                <p>Validate data against a template's schema on the server side.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>uploadDocument(filename, fileData, mimeType?) → Promise<object></code></div>
                <p>Upload a document (base64-encoded) to Print Hub for later use in print jobs.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>listDocuments() → Promise<object></code></div>
                <p>List all uploaded documents.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getDocument(docId) → Promise<object></code></div>
                <p>Get details of a specific document.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>previewDocument(docId) → Promise<Buffer></code></div>
                <p>Preview a document (rendered as PDF). Returns a Buffer.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>downloadDocument(docId) → Promise<Buffer></code></div>
                <p>Download a document's raw file content. Returns a Buffer.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>deleteDocument(docId) → Promise<object></code></div>
                <p>Delete a document.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>registerConnector(name, type, config, icon?) → Promise<object></code></div>
                <p>Register a new data-source connector.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>listConnectors() → Promise<array></code></div>
                <p>List all connectors registered for this client app.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>updateConnector(id, data) → Promise<object></code></div>
                <p>Update an existing connector.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>testConnector(id) → Promise<object></code></div>
                <p>Test a connector by sending a HEAD request to its configured URL.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>deleteConnector(id) → Promise<object></code></div>
                <p>Delete a connector.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getPendingApprovals() → Promise<object></code></div>
                <p>List all print jobs pending approval.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>approveJob(jobId) → Promise<object></code></div>
                <p>Approve a pending print job.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>rejectJob(jobId) → Promise<object></code></div>
                <p>Reject a pending print job.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getAgentVersion() → Promise<object></code></div>
                <p>Get the latest available TrayPrint agent version.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getFonts() → Promise<object></code></div>
                <p>List all available fonts.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getFormulaFunctions() → Promise<object></code></div>
                <p>List all available formula functions.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>validateFormula(expression) → Promise<object></code></div>
                <p>Validate a formula expression.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>evaluateFormula(expression, context) → Promise<object></code></div>
                <p>Evaluate a formula expression with given context variables.</p>
            </div>

            <h3>Error Handling</h3>
            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>import {
    PrintHubClient,
    PrintHubError,
    PrintHubValidationError,
    PrintHubConnectionError,
} from './PrintHubClient.mjs';

const client = new PrintHubClient({
    baseUrl: 'https://print-hub.example.com',
    apiKey:  'your-api-key-here',
});

try {
    const result = await client.printWithTemplate({
        template: 'invoice_sewa',
        data: { no_invoice: 'INV-001', customer: 'PT ABC', total: 150000 },
    });
    console.log(`Job: ${result.job_id}`);
} catch (err) {
    if (err instanceof PrintHubValidationError) {
        console.error('Validation errors:', err.errors);
    } else if (err instanceof PrintHubConnectionError) {
        console.error('Network error — Print Hub unreachable');
    } else {
        console.error('Error:', err.message);
    }
}</code></pre>
            </div>
        </section>

        {{-- ================================================================= --}}
        {{-- 12. OPENAPI / SWAGGER SPECIFICATION --}}
        {{-- ================================================================= --}}
        <section class="card doc-section" id="openapi">
            <div class="card-header"><h2>12. OpenAPI / Swagger Specification</h2></div>

            <p>Print Hub exposes a comprehensive <strong>OpenAPI 3.0.3 specification</strong> that describes the entire Client API surface. You can use this spec to:</p>

            <ul>
                <li>Generate client SDKs in any language using <a href="https://openapi-generator.tech" target="_blank">OpenAPI Generator</a></li>
                <li>Import into API tools like <a href="https://insomnia.rest" target="_blank">Insomnia</a>, <a href="https://www.postman.com" target="_blank">Postman</a>, or <a href="https://swagger.io/tools/swagger-ui/" target="_blank">Swagger UI</a></li>
                <li>Validate API requests and responses automatically</li>
                <li>Generate interactive API documentation</li>
            </ul>

            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code># Download the OpenAPI spec:
# {{ asset('sdk/openapi.yaml') }}

# Validate with swagger-cli:
npx swagger-cli validate openapi.yaml

# Generate a Python client:
npx @openapitools/openapi-generator-cli generate \
    -i openapi.yaml \
    -g python \
    -o ./printhub-client-python

# Generate a TypeScript client:
npx @openapitools/openapi-generator-cli generate \
    -i openapi.yaml \
    -g typescript-fetch \
    -o ./printhub-client-ts</code></pre>
            </div>

            <h3>Specification Overview</h3>
            <table>
                <thead><tr><th>Section</th><th>Endpoints</th></tr></thead>
                <tbody>
                    <tr><td>Connection & Health</td><td><code>GET /test</code>, <code>GET /health</code></td></tr>
                    <tr><td>Discovery</td><td><code>GET /branches</code>, <code>GET /agents/online</code>, <code>GET /queues</code></td></tr>
                    <tr><td>Templates</td><td><code>GET /templates</code>, <code>GET /templates/{name}</code>, <code>GET /templates/{name}/schema</code>, <code>POST /templates/{name}/validate</code></td></tr>
                    <tr><td>Data Schemas</td><td><code>POST /schema</code>, <code>GET /schemas</code>, <code>GET /schema/{name}/versions</code>, <code>GET /schemas/{name}/diff</code></td></tr>
                    <tr><td>Printing</td><td><code>POST /print</code>, <code>POST /print/batch</code>, <code>POST /preview</code></td></tr>
                    <tr><td>Job Management</td><td><code>GET /jobs/{job_id}</code>, <code>DELETE /jobs/{job_id}</code></td></tr>
                    <tr><td>Document Management</td><td><code>POST /documents/upload</code>, <code>GET /documents</code>, <code>GET /documents/{id}</code>, <code>GET /documents/{id}/preview</code>, <code>GET /documents/{id}/download</code>, <code>DELETE /documents/{id}</code></td></tr>
                    <tr><td>Approvals</td><td><code>GET /approvals/pending</code>, <code>POST /approvals/{id}/approve</code>, <code>POST /approvals/{id}/reject</code></td></tr>
                    <tr><td>Connectors</td><td><code>GET /connectors</code>, <code>POST /connectors</code>, <code>PUT /connectors/{id}</code>, <code>POST /connectors/{id}/test</code>, <code>DELETE /connectors/{id}</code></td></tr>
                    <tr><td>Agent & System</td><td><code>GET /agents/version</code>, <code>GET /fonts</code></td></tr>
                    <tr><td>Formula Editor</td><td><code>GET /formula/functions</code>, <code>POST /formula/validate</code>, <code>POST /formula/evaluate</code></td></tr>
                </tbody>
            </table>

            <div class="tip-box">
                <strong>💡 Tip:</strong> The OpenAPI spec includes full JSON Schema definitions for all request/response bodies, making it ideal for code generation and API testing.
            </div>
        </section>

        {{-- ================================================================= --}}
        {{-- 13. POSTMAN COLLECTION --}}
        {{-- ================================================================= --}}
        <section class="card doc-section" id="postman">
            <div class="card-header"><h2>13. Postman Collection</h2></div>

            <p>Download the Postman Collection JSON and import it into <a href="https://www.postman.com" target="_blank">Postman</a> to get a pre-configured collection with all endpoints. Replace <code>@{{base_url}}</code> and <code>@{{api_key}}</code> variables in Postman with your server URL and API key.</p>

            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code># Download the Postman Collection:
# {{ asset('sdk/PrintHub-Postman.json') }}

# Import via CLI (requires postmanctl or Newman):
# newman run PrintHub-Postman.json --env-var "base_url=https://print-hub.example.com/api/v1" --env-var "api_key=your-key"</code></pre>
            </div>

            <div class="tip-box">
                <strong>💡 Tip:</strong> After importing, set the <code>base_url</code> and <code>api_key</code> variables in Postman's "Variables" tab. The auth is pre-configured to use <code>X-API-Key</code> header with your variable.
            </div>
        </section>

        {{-- ================================================================= --}}
        {{-- 14. RATE LIMITING --}}
        {{-- ================================================================= --}}
        <section class="card doc-section" id="rate-limiting">
            <div class="card-header"><h2>14. Rate Limiting</h2></div>

            <p>Print Hub uses Laravel's built-in rate limiter to protect the API from abuse. Rate limits are applied per API key.</p>

            <table>
                <thead><tr><th>API</th><th>Limit</th><th>Window</th><th>Middleware</th></tr></thead>
                <tbody>
                    <tr>
                        <td><span class="badge badge-info">Client API</span></td>
                        <td><strong>60 requests</strong></td>
                        <td>1 minute</td>
                        <td><code>throttle:60,1</code></td>
                    </tr>
                    <tr>
                        <td><span class="badge badge-warning">Agent API</span></td>
                        <td><strong>120 requests</strong></td>
                        <td>1 minute</td>
                        <td><code>throttle:120,1</code></td>
                    </tr>
                </tbody>
            </table>

            <h3>Rate Limit Response</h3>
            <p>When the rate limit is exceeded, the server returns:</p>
            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>// HTTP 429 Too Many Requests
{
    "success": false,
    "error": {
        "code": "VALIDATION_FAILED",
        "message": "Too Many Attempts."
    }
}</code></pre>
            </div>

            <h3>Headers</h3>
            <p>Rate-limited responses include the following headers:</p>
            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
Retry-After: 42</code></pre>
            </div>

            <div class="tip-box">
                <strong>💡 Tip:</strong> The PHP SDK automatically retries on <code>429</code> responses with exponential backoff, up to <code>$maxRetries</code> attempts.
            </div>
        </section>

        {{-- ================================================================= --}}
        {{-- 15. TRAYPRINT AGENT --}}
        {{-- ================================================================= --}}
        <section class="card doc-section" id="trayprint">
            <div class="card-header"><h2>15. TrayPrint Agent</h2></div>

            <p><strong>TrayPrint</strong> is a cross-platform print agent service that runs on Windows, Linux, and macOS workstations. It connects to Print Hub via WebSocket (Laravel Reverb) and HTTP polling, receives print jobs, and sends them to local printers. TrayPrint runs as a system tray application with a settings dialog, or can operate silently as a background service.</p>

            <h3>Architecture</h3>
            <div class="arch-diagram">
                <div class="arch-node">
                    <div class="arch-box hub">📡 Print Hub<br><small>Laravel server</small></div>
                    <div class="arch-arrow">→ WebSocket / HTTP →</div>
                </div>
                <div class="arch-node">
                    <div class="arch-box agent">🖨️ TrayPrint Agent<br><small>Python/PySide6</small></div>
                    <div class="arch-arrow">→ USB / Network →</div>
                </div>
                <div class="arch-node">
                    <div class="arch-box printer">📄 Printer</div>
                </div>
            </div>

            <p>TrayPrint communicates with Print Hub using two channels:</p>
            <ul>
                <li><strong>WebSocket</strong> (Laravel Reverb) — Real-time job notifications, status updates, and live queue changes</li>
                <li><strong>HTTP REST API</strong> — Job status reporting, printer capability discovery, heartbeat/ping, and file downloads</li>
            </ul>

            <h3>Installation</h3>

            <h4>Windows (Recommended — MSI Installer)</h4>
            <p>Download the latest MSI installer from the Print Hub admin panel (<strong>Agents → Download Installer</strong>). The MSI installs TrayPrint and optionally registers a Windows Scheduled Task for automatic startup at boot.</p>

            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code># Silent install with auto-start task
msiexec /i TrayPrint-1.0.0.msi /qn INSTALL_TASK=1

# Install with custom config path
msiexec /i TrayPrint-1.0.0.msi /qn CONFIG_PATH="C:\PrintHub\config.json"</code></pre>
            </div>

            <h4>Linux / macOS</h4>
            <p>Clone the repository and run TrayPrint directly:</p>
            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>git clone https://github.com/your-org/trayprint.git
cd trayprint
pip install -r requirements.txt
python app.py --config /path/to/config.json</code></pre>
            </div>

            <h3>Configuration</h3>
            <p>TrayPrint reads a <code>config.json</code> file on startup. The default location is alongside the executable, or you can specify a custom path with <code>--config</code>.</p>

            <div class="code-block-wrapper">
                <button class="copy-btn" onclick="copyCode(this)" title="Copy to clipboard">📋</button>
                <pre class="code-block"><code>{
    "hub_url": "https://print-hub.example.com",
    "agent_key": "ag_xxxxxxxxxxxx",
    "branch_code": "SDP-SBY",
    "poll_interval": 5,
    "reconnect_delay": 10,
    "log_level": "INFO",
    "temp_dir": "C:\\PrintHub\\temp",
    "watchdog_enabled": true,
    "watchdog_interval": 30,
    "auto_update": true,
    "update_check_interval": 3600,
    "printer_discovery": true,
    "capabilities_cache_ttl": 300
}</code></pre>
            </div>

            <table>
                <thead><tr><th>Key</th><th>Type</th><th>Default</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>hub_url</code></td><td>string</td><td>—</td><td>Print Hub server URL (required)</td></tr>
                    <tr><td><code>agent_key</code></td><td>string</td><td>—</td><td>Agent authentication key from Print Hub (required)</td></tr>
                    <tr><td><code>branch_code</code></td><td>string</td><td><code>""</code></td><td>Branch code this agent belongs to</td></tr>
                    <tr><td><code>poll_interval</code></td><td>int</td><td><code>5</code></td><td>Seconds between job queue polls</td></tr>
                    <tr><td><code>reconnect_delay</code></td><td>int</td><td><code>10</code></td><td>Seconds to wait before reconnecting on disconnect</td></tr>
                    <tr><td><code>log_level</code></td><td>string</td><td><code>"INFO"</code></td><td>Logging level: DEBUG, INFO, WARNING, ERROR</td></tr>
                    <tr><td><code>watchdog_enabled</code></td><td>bool</td><td><code>true</code></td><td>Enable self-healing spooler monitor</td></tr>
                    <tr><td><code>auto_update</code></td><td>bool</td><td><code>true</code></td><td>Enable automatic version checking and updates</td></tr>
                    <tr><td><code>printer_discovery</code></td><td>bool</td><td><code>true</code></td><td>Auto-discover local printers on startup</td></tr>
                </tbody>
            </table>

            <h3>CLI Commands</h3>
            <table>
                <thead><tr><th>Flag</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>--install-task</code></td><td>Register a Windows Scheduled Task for auto-start at user logon</td></tr>
                    <tr><td><code>--uninstall-task</code></td><td>Remove the Windows Scheduled Task</td></tr>
                    <tr><td><code>--install-service</code></td><td>Install as a Windows service using nssm (runs without tray UI)</td></tr>
                    <tr><td><code>--uninstall-service</code></td><td>Remove the Windows service</td></tr>
                    <tr><td><code>--config <path></code></td><td>Specify a custom config file path</td></tr>
                    <tr><td><code>--silent</code></td><td>Run without system tray UI (background mode)</td></tr>
                    <tr><td><code>--version</code></td><td>Display version information and exit</td></tr>
                </tbody>
            </table>

            <h3>Local Agent API Endpoints</h3>
            <p>TrayPrint runs a local Flask HTTP server (default port <code>51999</code>) for local management and monitoring. These endpoints are only accessible from <code>localhost</code>.</p>

            <table>
                <thead><tr><th>Method</th><th>Endpoint</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><span class="method method-get">GET</span></td><td><code>/status</code></td><td>Agent status (connected, polling, printers)</td></tr>
                    <tr><td><span class="method method-get">GET</span></td><td><code>/printers</code></td><td>List locally detected printers</td></tr>
                    <tr><td><span class="method method-get">GET</span></td><td><code>/profiles</code></td><td>List print profiles synced from hub</td></tr>
                    <tr><td><span class="method method-post">POST</span></td><td><code>/print</code></td><td>Submit a local print job directly</td></tr>
                    <tr><td><span class="method method-get">GET</span></td><td><code>/jobs</code></td><td>List recent print jobs</td></tr>
                    <tr><td><span class="method method-get">GET</span></td><td><code>/jobs/{job_id}</code></td><td>Get job details</td></tr>
                    <tr><td><span class="method method-post">POST</span></td><td><code>/jobs/{job_id}/retry</code></td><td>Retry a failed print job</td></tr>
                    <tr><td><span class="method method-post">POST</span></td><td><code>/jobs/{job_id}/cancel</code></td><td>Cancel a queued print job</td></tr>
                    <tr><td><span class="method method-get">GET</span></td><td><code>/queue-status</code></td><td>Current queue depth and status</td></tr>
                    <tr><td><span class="method method-get">GET</span></td><td><code>/api/capabilities</code></td><td>Get capabilities for the default printer</td></tr>
                    <tr><td><span class="method method-get">GET</span></td><td><code>/api/capabilities/all</code></td><td>Get capabilities for all detected printers</td></tr>
                    <tr><td><span class="method method-get">GET</span></td><td><code>/api/diagnostics</code></td><td>Full system diagnostics report</td></tr>
                    <tr><td><span class="method method-get">GET</span></td><td><code>/api/watchdog/log</code></td><td>Watchdog activity log</td></tr>
                </tbody>
            </table>

            <h3>Hub Communication</h3>
            <p>TrayPrint maintains a persistent connection to Print Hub using two complementary mechanisms:</p>

            <h4>WebSocket (Laravel Reverb)</h4>
            <p>When connected via WebSocket, TrayPrint receives real-time notifications for:</p>
            <ul>
                <li><strong>New print jobs</strong> — Immediately notified when a job is assigned to this agent</li>
                <li><strong>Job cancellations</strong> — Cancelled jobs are removed from the local queue</li>
                <li><strong>Profile updates</strong> — Print profiles are synced when changed in the admin panel</li>
                <li><strong>Configuration changes</strong> — Remote config updates take effect immediately</li>
            </ul>

            <h4>HTTP Polling (Fallback)</h4>
            <p>If WebSocket is unavailable, TrayPrint falls back to HTTP polling at the configured <code>poll_interval</code>. The agent calls <code>GET /api/v1/agents/{key}/jobs/pending</code> to fetch pending jobs.</p>

            <h3>Watchdog</h3>
            <p>The watchdog is a self-healing spooler monitor that runs on a configurable interval (default 30 seconds). It performs the following checks:</p>
            <ul>
                <li><strong>Spooler health</strong> — Verifies the Windows Print Spooler service is running (Windows only)</li>
                <li><strong>Stale job cleanup</strong> — Detects and cleans up print jobs stuck in "printing" state for more than 5 minutes</li>
                <li><strong>Connection health</strong> — Verifies the WebSocket and HTTP connections to Print Hub are active</li>
                <li><strong>Disk space</strong> — Checks available temp directory space (alerts if below 100MB)</li>
            </ul>

            <h3>Auto-Update</h3>
            <p>TrayPrint can automatically check for updates by querying <code>GET /api/v1/agents/version</code> on Print Hub. When a new version is available:</p>
            <ol>
                <li>The agent downloads the new installer package</li>
                <li>Validates the package checksum</li>
                <li>Prompts the user (or auto-installs in silent mode)</li>
                <li>Restarts the agent service</li>
            </ol>

            <h3>Diagnostics</h3>
            <p>TrayPrint includes a built-in diagnostics dialog accessible from the system tray menu. It provides:</p>
            <ul>
                <li><strong>Connection status</strong> — WebSocket and HTTP connection state</li>
                <li><strong>Printer list</strong> — All detected printers with capabilities</li>
                <li><strong>Recent jobs</strong> — Last 50 print jobs with status and error details</li>
                <li><strong>Log viewer</strong> — Real-time log tail with filter and export</li>
                <li><strong>Watchdog status</strong> — Last check results and spooler health</li>
                <li><strong>System info</strong> — OS version, Python version, uptime, memory usage</li>
            </ul>

            <h3>Troubleshooting</h3>

            <table>
                <thead><tr><th>Issue</th><th>Likely Cause</th><th>Solution</th></tr></thead>
                <tbody>
                    <tr>
                        <td>Agent shows "Disconnected"</td>
                        <td>Network issue or invalid agent key</td>
                        <td>Verify <code>hub_url</code> and <code>agent_key</code> in config.json. Check firewall rules for WebSocket port (8080/443).</td>
                    </tr>
                    <tr>
                        <td>Jobs stuck in "pending"</td>
                        <td>Agent not polling or WebSocket disconnected</td>
                        <td>Check agent status in the system tray. Restart the agent service. Verify <code>poll_interval</code> is set correctly.</td>
                    </tr>
                    <tr>
                        <td>Printer not found</td>
                        <td>Printer name mismatch or driver issue</td>
                        <td>Run <code>GET /printers</code> on the local API to see detected printers. Verify the printer name matches exactly.</td>
                    </tr>
                    <tr>
                        <td>PDF fails to print</td>
                        <td>Missing PDF renderer or permissions</td>
                        <td>Ensure SumatraPDF (Windows) or <code>lp</code> (Linux) is installed. Check temp directory permissions.</td>
                    </tr>
                    <tr>
                        <td>Watchdog alerts frequently</td>
                        <td>Spooler service unstable or disk space low</td>
                        <td>Check Windows Print Spooler service. Free up disk space in the temp directory.</td>
                    </tr>
                </tbody>
            </table>

            <div class="tip-box">
                <strong>💡 Tip:</strong> Use the <code>GET /api/diagnostics</code> endpoint on the local agent API to get a comprehensive system report. This is the first step in troubleshooting most issues.
            </div>
        </section>

    </div>
</div>

<style>
/* ================================================================
   Documentation Layout
   ================================================================ */
.docs-layout {
    display: flex;
    gap: 1.5rem;
    align-items: flex-start;
}

.docs-sidebar {
    min-width: 240px;
    max-width: 240px;
    position: sticky;
    top: 2rem;
    align-self: flex-start;
}

.docs-content {
    flex: 1;
    min-width: 0;
}

.toc-card {
    padding: 0.75rem;
}

.toc-header {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.5rem;
    padding: 0.25rem 0.5rem;
}

.toc-nav {
    display: flex;
    flex-direction: column;
    gap: 1px;
    margin-bottom: 1rem;
}

.toc-link {
    display: block;
    padding: 0.3rem 0.5rem;
    font-size: 0.78rem;
    color: var(--text-muted);
    text-decoration: none;
    border-radius: 4px;
    transition: all 0.15s;
    border-left: 2px solid transparent;
}

.toc-link:hover {
    color: var(--text);
    background: var(--surface-hover);
}

.toc-link.active {
    color: var(--primary-hover);
    background: rgba(99, 102, 241, 0.08);
    border-left-color: var(--primary);
}

.toc-link.sub {
    padding-left: 1.2rem;
    font-size: 0.72rem;
}

.toc-download {
    padding: 0.5rem;
}

/* ================================================================
   Architecture Diagram
   ================================================================ */
.arch-diagram {
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    gap: 0;
    padding: 1.5rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.arch-node {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 0;
}

.arch-box {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    text-align: center;
    min-width: 160px;
}

.arch-box small {
    display: block;
    font-weight: 400;
    font-size: 0.72rem;
    margin-top: 0.2rem;
    opacity: 0.8;
}

.arch-box.client {
    background: rgba(59, 130, 246, 0.15);
    border: 2px solid var(--info);
    color: var(--info);
}

.arch-box.hub {
    background: rgba(99, 102, 241, 0.15);
    border: 2px solid var(--primary);
    color: var(--primary);
}

.arch-box.agent {
    background: rgba(245, 158, 11, 0.15);
    border: 2px solid var(--warning);
    color: var(--warning);
}

.arch-box.printer {
    background: rgba(34, 197, 94, 0.15);
    border: 2px solid var(--success);
    color: var(--success);
}

.arch-arrow {
    font-size: 0.78rem;
    color: var(--text-muted);
    padding: 0 0.5rem;
    font-family: 'Fira Code', monospace;
    white-space: nowrap;
}

/* ================================================================
   Endpoint Blocks
   ================================================================ */
.endpoint-group {
    margin-bottom: 1rem;
}

.endpoint-group > h3 {
    font-size: 0.95rem;
    font-weight: 600;
    padding: 0.6rem 0.8rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    user-select: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: background 0.15s;
    margin-bottom: 0.5rem;
}

.endpoint-group > h3:hover {
    background: var(--surface-hover);
}

.expandable-arrow {
    font-size: 0.75rem;
    color: var(--text-muted);
    transition: transform 0.15s;
}

.expandable-content {
    display: none;
    padding-left: 0.5rem;
    border-left: 2px solid var(--border);
    margin-left: 0.5rem;
}

.expandable-content.open {
    display: block;
}

.endpoint-block {
    padding: 1rem;
    margin-bottom: 0.75rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    transition: border-color 0.15s;
}

.endpoint-block:hover {
    border-color: var(--primary);
}

.endpoint-block.legacy {
    opacity: 0.75;
}

.endpoint-header {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    margin-bottom: 0.75rem;
    flex-wrap: wrap;
}

.method {
    display: inline-block;
    padding: 0.2rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 700;
    font-family: 'Fira Code', monospace;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    min-width: 44px;
    text-align: center;
}

.method-get {
    background: rgba(34, 197, 94, 0.15);
    color: var(--success);
}

.method-post {
    background: rgba(59, 130, 246, 0.15);
    color: var(--info);
}

.method-delete {
    background: rgba(239, 68, 68, 0.15);
    color: var(--danger);
}

.endpoint-url {
    font-family: 'Fira Code', 'Cascadia Code', monospace;
    font-size: 0.82rem;
    color: var(--text);
    font-weight: 600;
}

.endpoint-tag {
    font-size: 0.7rem;
    color: var(--text-muted);
    padding: 0.15rem 0.5rem;
    background: var(--surface);
    border-radius: 4px;
    border: 1px solid var(--border);
}

.legacy-tag {
    color: var(--warning);
    border-color: rgba(245, 158, 11, 0.3);
    background: rgba(245, 158, 11, 0.1);
}

.endpoint-block h4 {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-muted);
    margin: 0.75rem 0 0.4rem;
}

.endpoint-block p {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-bottom: 0.5rem;
}

/* ================================================================
   Code Blocks
   ================================================================ */
.code-block-wrapper {
    position: relative;
    margin-bottom: 0.75rem;
}

.copy-btn {
    position: absolute;
    top: 0.4rem;
    right: 0.4rem;
    background: rgba(255,255,255,0.08);
    border: 1px solid var(--border);
    color: var(--text-muted);
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.78rem;
    transition: all 0.15s;
    z-index: 5;
    line-height: 1;
}

.copy-btn:hover {
    background: rgba(255,255,255,0.15);
    color: var(--text);
}

.code-block {
    background: #1a1d27;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1.25rem 1rem;
    overflow: auto;
    max-height: 500px;
    font-family: 'Fira Code', 'Cascadia Code', 'JetBrains Mono', monospace;
    font-size: 0.76rem;
    line-height: 1.65;
    color: #e4e6ed;
    white-space: pre-wrap;
    word-break: break-word;
    tab-size: 2;
}

.code-block code {
    background: none;
    padding: 0;
    color: inherit;
    white-space: inherit;
    word-break: inherit;
}

[data-theme="light"] .code-block {
    background: #0f1117;
}

/* ================================================================
   Flow Steps
   ================================================================ */
.flow-steps {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.flow-step {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    transition: border-color 0.15s;
}

.flow-step:hover {
    border-color: var(--primary);
}

.flow-step-num {
    width: 32px;
    height: 32px;
    min-width: 32px;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
}

.flow-step-content {
    flex: 1;
}

.flow-step-content h4 {
    font-size: 0.9rem;
    font-weight: 600;
    margin-bottom: 0.35rem;
}

.flow-step-content p,
.flow-step-content li {
    font-size: 0.82rem;
    color: var(--text-muted);
    line-height: 1.5;
}

.flow-step-content ul {
    padding-left: 1.2rem;
    margin-top: 0.3rem;
}

/* ================================================================
   Method Blocks (SDK client section)
   ================================================================ */
.method-block {
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--border);
}

.method-block:last-child {
    border-bottom: none;
}

.method-sig {
    font-size: 0.82rem;
    margin-bottom: 0.4rem;
    padding: 0.35rem 0.7rem;
    background: rgba(99, 102, 241, 0.08);
    border-radius: 6px;
    display: inline-block;
}

.method-sig code {
    font-family: 'Fira Code', 'Cascadia Code', monospace;
    color: var(--primary-hover);
    font-size: 0.8rem;
}

.method-block p {
    color: var(--text-muted);
    font-size: 0.82rem;
    margin: 0.3rem 0;
}

/* ================================================================
   Tip / Note Boxes
   ================================================================ */
.tip-box {
    padding: 0.75rem 1rem;
    background: rgba(99, 102, 241, 0.08);
    border: 1px solid rgba(99, 102, 241, 0.2);
    border-radius: 8px;
    margin-bottom: 1rem;
    font-size: 0.82rem;
    color: var(--text-muted);
}

.tip-box.warning {
    background: rgba(245, 158, 11, 0.08);
    border-color: rgba(245, 158, 11, 0.2);
}

.tip-box strong {
    color: var(--text);
}

.note {
    font-size: 0.78rem;
    color: var(--text-muted);
    font-style: italic;
}

/* ================================================================
   Error List
   ================================================================ */
.error-list {
    padding-left: 1.2rem;
    font-size: 0.82rem;
    color: var(--text-muted);
}

.error-list li {
    margin-bottom: 0.2rem;
}

.error-list code {
    font-size: 0.78rem;
}

/* ================================================================
   Doc sections spacing
   ================================================================ */
.doc-section {
    scroll-margin-top: 2rem;
}

.doc-section h3 {
    font-size: 1rem;
    font-weight: 600;
    margin-top: 1.5rem;
    margin-bottom: 0.5rem;
}

.doc-section h3:first-child {
    margin-top: 0;
}

.doc-section h4 {
    font-size: 0.88rem;
    font-weight: 600;
    margin-top: 1rem;
    margin-bottom: 0.4rem;
    color: var(--text);
}

.doc-section p {
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-bottom: 0.75rem;
    line-height: 1.6;
}

.doc-section ol,
.doc-section ul {
    font-size: 0.85rem;
    color: var(--text-muted);
    padding-left: 1.2rem;
    margin-bottom: 0.75rem;
    line-height: 1.6;
}

.doc-section ol li,
.doc-section ul li {
    margin-bottom: 0.25rem;
}

/* ================================================================
   Responsive
   ================================================================ */

/* Make all tables responsive */
.doc-section table,
.endpoint-block table {
    width: 100%;
    min-width: 0;
    table-layout: fixed;
    border-collapse: collapse;
}

.doc-section table th,
.doc-section table td,
.endpoint-block table th,
.endpoint-block table td {
    word-wrap: break-word;
    overflow-wrap: break-word;
    hyphens: auto;
}

/* Ensure sidebar doesn't squeeze content too much */
.docs-sidebar {
    min-width: 200px;
    max-width: 220px;
}

@media (max-width: 1100px) {
    .docs-sidebar {
        min-width: 180px;
        max-width: 180px;
    }
    .toc-link {
        font-size: 0.72rem;
    }
}

@media (max-width: 900px) {
    .docs-layout {
        flex-direction: column;
    }

    .docs-sidebar {
        position: static;
        min-width: 100%;
        max-width: 100%;
    }

    .toc-nav {
        display: flex;
        flex-direction: row;
        flex-wrap: wrap;
        gap: 2px;
    }

    .toc-link {
        font-size: 0.72rem;
        padding: 0.2rem 0.4rem;
    }

    .toc-link.sub {
        padding-left: 0.4rem;
    }

    .arch-diagram {
        padding: 1rem;
        flex-direction: column;
    }

    .arch-node {
        flex-direction: column;
    }

    .arch-arrow {
        transform: rotate(90deg);
        padding: 0.25rem 0;
    }

    .arch-box {
        min-width: 120px;
        padding: 0.5rem 1rem;
        font-size: 0.78rem;
    }

    /* Stack code blocks sensibly on mobile */
    .code-block {
        font-size: 0.7rem;
    }
}
</style>

<script>
/**
 * Toggle collapsible endpoint groups
 */
function toggleSection(header) {
    const content = header.nextElementSibling;
    if (content && content.classList.contains('expandable-content')) {
        content.classList.toggle('open');
        const arrow = header.querySelector('.expandable-arrow');
        if (arrow) {
            arrow.textContent = content.classList.contains('open') ? '▾' : '▸';
        }
    }
}

/**
 * Copy code block content to clipboard
 */
function copyCode(btn) {
    const wrapper = btn.closest('.code-block-wrapper');
    const code = wrapper.querySelector('.code-block');
    if (!code) return;

    // Get text, stripping leading/trailing whitespace
    const text = code.textContent.trim();

    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.textContent;
        btn.textContent = '✅ Copied!';
        btn.style.color = 'var(--success)';
        setTimeout(() => {
            btn.textContent = orig;
            btn.style.color = '';
        }, 2000);
    }).catch(() => {
        // Fallback: select text
        const range = document.createRange();
        range.selectNodeContents(code);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
    });
}

/**
 * Scroll spy for table of contents
 */
document.addEventListener('DOMContentLoaded', function() {
    const tocLinks = document.querySelectorAll('.toc-link');
    const sections = [];

    tocLinks.forEach(link => {
        const sectionId = link.getAttribute('data-section');
        if (sectionId) {
            const el = document.getElementById(sectionId);
            if (el) {
                sections.push({ id: sectionId, element: el, link: link });
            }
        }
    });

    function updateActiveSection() {
        const scrollPos = window.scrollY + 100;
        let active = sections[0]?.id;

        sections.forEach(({ id, element }) => {
            if (element.offsetTop <= scrollPos) {
                active = id;
            }
        });

        tocLinks.forEach(link => {
            link.classList.toggle('active', link.getAttribute('data-section') === active);
        });
    }

    // Throttle scroll events
    let ticking = false;
    window.addEventListener('scroll', () => {
        if (!ticking) {
            window.requestAnimationFrame(() => {
                updateActiveSection();
                ticking = false;
            });
            ticking = true;
        }
    });

    updateActiveSection();
});
</script>
@endsection
