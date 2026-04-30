@extends('admin.layout')
@section('title', 'SDK Documentation')

@section('content')
<div class="page-header">
    <h1>📖 Print Hub SDK Documentation</h1>
    <p>Complete integration guide for client application developers.</p>
</div>

<div style="display: flex; gap: 1.5rem;">
    {{-- Sidebar Navigation --}}
    <div style="min-width: 220px; position: sticky; top: 2rem; align-self: flex-start;">
        <div class="card" style="padding: 1rem;">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.75rem;">Contents</div>
            <div style="display: flex; flex-direction: column; gap: 2px;">
                <a href="#getting-started" class="sdk-nav">Getting Started</a>
                <a href="#installation" class="sdk-nav sub">Installation</a>
                <a href="#initialization" class="sdk-nav sub">Initialization</a>
                <a href="#quick-start" class="sdk-nav sub">Quick Start</a>
                <a href="#concepts" class="sdk-nav">Core Concepts</a>
                <a href="#branches" class="sdk-nav sub">Companies & Branches</a>
                <a href="#templates" class="sdk-nav sub">Templates & Schemas</a>
                <a href="#queues" class="sdk-nav sub">Queues (Profiles)</a>
                <a href="#routing" class="sdk-nav sub">Auto-Routing</a>
                <a href="#api-reference" class="sdk-nav">API Reference</a>
                <a href="#discovery-api" class="sdk-nav sub">Discovery</a>
                <a href="#printing-api" class="sdk-nav sub">Printing</a>
                <a href="#schema-api" class="sdk-nav sub">Schemas</a>
                <a href="#jobs-api" class="sdk-nav sub">Job Management</a>
                <a href="#examples" class="sdk-nav">Integration Examples</a>
                <a href="#laravel-example" class="sdk-nav sub">Laravel</a>
                <a href="#multi-branch" class="sdk-nav sub">Multi-Branch Setup</a>
                <a href="#error-handling" class="sdk-nav sub">Error Handling</a>
                <a href="#troubleshooting" class="sdk-nav">Troubleshooting</a>
            </div>
        </div>
        <div style="margin-top: 1rem;">
            <a href="{{ route('admin.clients.sdk') }}" class="btn btn-primary" style="width: 100%; text-decoration: none; justify-content: center;">
                ⬇️ Download SDK
            </a>
        </div>
    </div>

    {{-- Content --}}
    <div style="flex: 1; min-width: 0;">

        {{-- Getting Started --}}
        <div class="card" id="getting-started">
            <div class="card-header"><h2>🚀 Getting Started</h2></div>

            <h3 id="installation" style="color: var(--primary); margin-top: 1.5rem;">Installation</h3>
            <p style="color: var(--text-muted); margin-bottom: 1rem;">The SDK is a single PHP file that requires Guzzle HTTP client.</p>
            <pre class="code-block"><code># Install Guzzle via Composer
composer require guzzlehttp/guzzle

# Download the SDK from Print Hub
# Go to Client Apps → Download SDK
# Place PrintHubClient.php in your project</code></pre>

            <h3 id="initialization" style="color: var(--primary); margin-top: 2rem;">Initialization</h3>
            <pre class="code-block"><code>&lt;?php
require_once 'PrintHubClient.php';

$printHub = new PrintHubClient(
    baseUrl: 'https://print-hub.example.com',  // Your Print Hub URL
    apiKey:  'your-api-key-here',               // From Client Apps page
    timeout: 15,                                // Request timeout (seconds)
    cacheDir: '/tmp'                            // Schema cache directory
);

// Set default branch for all subsequent calls
$printHub->setBranch('SDP-MAIN');</code></pre>

            <h3 id="quick-start" style="color: var(--primary); margin-top: 2rem;">Quick Start</h3>
            <pre class="code-block"><code>// Print an invoice using a template
$result = $printHub->printWithTemplate(
    template:    'invoice_sewa',
    data:        ['no_invoice' => 'INV-001', 'customer' => 'PT ABC', 'total' => 150000],
    referenceId: 'INV-001'
);

echo "Job ID: " . $result['job_id'];
echo "Status: " . $result['status']; // "queued"

// Wait for completion
$final = $printHub->waitForJob($result['job_id'], timeoutSeconds: 30);
echo "Final: " . $final['status']; // "success" or "failed"</code></pre>
        </div>

        {{-- Core Concepts --}}
        <div class="card" id="concepts">
            <div class="card-header"><h2>📐 Core Concepts</h2></div>

            <h3 id="branches" style="color: var(--primary); margin-top: 1rem;">Companies & Branches</h3>
            <p style="color: var(--text-muted); margin-bottom: 1rem;">Print Hub serves multiple companies under the Hartono Raya Motor Group. Each company has one or more branches, each with its own agents and printers.</p>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div style="padding: 1rem; background: var(--bg); border-radius: 8px; border: 1px solid var(--border);">
                    <strong style="color: var(--primary);">Company</strong>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">A legal entity (e.g. "SDP / Harent"). Has a unique code like <code>SDP</code>.</p>
                </div>
                <div style="padding: 1rem; background: var(--bg); border-radius: 8px; border: 1px solid var(--border);">
                    <strong style="color: var(--success);">Branch</strong>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;">A physical location within a company. Has a unique code like <code>SDP-SBY</code>.</p>
                </div>
            </div>

            <h3 id="templates" style="color: var(--primary); margin-top: 2rem;">Templates & Schemas</h3>
            <p style="color: var(--text-muted); margin-bottom: 1rem;">Templates are global HTML/PDF designs that accept data variables. Each template has a <strong>schema</strong> defining required fields and table structures.</p>
            <pre class="code-block"><code>// Discover available templates
$templates = $printHub->getTemplates();

// Get schema for a specific template
$schema = $printHub->getTemplateSchema('invoice_sewa');
// Returns: { required_fields: {...}, required_tables: {...} }</code></pre>

            <h3 id="queues" style="color: var(--primary); margin-top: 2rem;">Queues (Profiles)</h3>
            <p style="color: var(--text-muted); margin-bottom: 1rem;">A queue defines printing configuration: paper size, orientation, margins, target agent, and target printer. Each queue belongs to a specific branch.</p>

            <h3 id="routing" style="color: var(--primary); margin-top: 2rem;">Auto-Routing (Branch Template Defaults)</h3>
            <p style="color: var(--text-muted); margin-bottom: 1rem;">Each branch can configure a <strong>default queue</strong> per template. When you print from that branch, the system automatically routes to the correct agent/printer.</p>
            <div style="padding: 1rem; background: rgba(99, 102, 241, 0.08); border-radius: 8px; border: 1px solid rgba(99, 102, 241, 0.2); margin-bottom: 1rem;">
                <strong style="color: var(--primary);">How it works:</strong>
                <ol style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.5rem; padding-left: 1.2rem;">
                    <li>You send: <code>template: "invoice_sewa"</code> + <code>branch_code: "SDP-SBY"</code></li>
                    <li>Print Hub looks up the configured default queue for this branch + template combo</li>
                    <li>That queue knows which agent and printer to use → job is routed automatically</li>
                    <li>If no default is configured → falls back to any online agent in the branch</li>
                </ol>
            </div>
        </div>

        {{-- API Reference --}}
        <div class="card" id="api-reference">
            <div class="card-header"><h2>📚 API Reference</h2></div>

            {{-- Discovery --}}
            <h3 id="discovery-api" style="color: var(--primary); margin-top: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border);">Discovery Methods</h3>

            <div class="method-block">
                <div class="method-sig"><code>getBranches(): array</code></div>
                <p>List all available branches with their company info.</p>
                <pre class="code-block"><code>$branches = $printHub->getBranches();
// [['id' => 1, 'code' => 'SDP-MAIN', 'name' => 'SDP - Main', 'company' => ['code' => 'SDP', ...]]]</code></pre>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getOnlineAgents(?string $branchCode = null): array</code></div>
                <p>List online agents, optionally filtered by branch.</p>
                <pre class="code-block"><code>$agents = $printHub->getOnlineAgents('SDP-SBY');
// [['id' => 3, 'name' => 'PC-SBY-01', 'printers' => [...], 'last_seen_at' => '...']]</code></pre>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getTemplates(): array</code></div>
                <p>List all available print templates.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getTemplate(string $name): array</code></div>
                <p>Get detailed info for a specific template.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>getTemplateSchema(string $name, bool $useCache = true): array</code></div>
                <p>Get the data schema for a template. Results are cached locally for 10 minutes.</p>
            </div>

            {{-- Printing --}}
            <h3 id="printing-api" style="color: var(--success); margin-top: 2rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border);">Printing Methods</h3>

            <div class="method-block">
                <div class="method-sig"><code>printWithTemplate(string $template, array $data, string $referenceId = '', string $queue = '', ?string $branchCode = null, array $options = []): array</code></div>
                <p>Print using a named template. The primary method for template-based printing.</p>
                <table style="font-size: 0.8rem; margin: 0.75rem 0;">
                    <thead><tr><th>Parameter</th><th>Type</th><th>Description</th></tr></thead>
                    <tbody>
                        <tr><td><code>$template</code></td><td>string</td><td>Template name (e.g. "invoice_sewa")</td></tr>
                        <tr><td><code>$data</code></td><td>array</td><td>Key-value data to fill into the template</td></tr>
                        <tr><td><code>$referenceId</code></td><td>string</td><td>Your app's reference ID for tracking</td></tr>
                        <tr><td><code>$queue</code></td><td>string</td><td>Explicit queue/profile name override</td></tr>
                        <tr><td><code>$branchCode</code></td><td>?string</td><td>Branch code override (falls back to <code>setBranch()</code>)</td></tr>
                        <tr><td><code>$options</code></td><td>array</td><td><code>copies</code>, <code>skip_validation</code>, <code>priority</code></td></tr>
                    </tbody>
                </table>
                <pre class="code-block"><code>$result = $printHub->printWithTemplate(
    template:    'invoice_sewa',
    data:        ['no_invoice' => 'INV-001', 'total' => 150000],
    referenceId: 'INV-001',
    branchCode:  'SDP-SBY',
    options:     ['copies' => 2]
);
// { "status": "queued", "job_id": "abc-123", "agent": "PC-SBY-01", "printer": "HP LaserJet" }</code></pre>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>printRawPdf(string $base64Pdf, string $referenceId = '', string $queue = '', ?string $branchCode = null, array $options = []): array</code></div>
                <p>Print a raw PDF document (base64-encoded) without using a template.</p>
                <pre class="code-block"><code>$pdfBase64 = base64_encode(file_get_contents('report.pdf'));
$result = $printHub->printRawPdf($pdfBase64, referenceId: 'RPT-001');</code></pre>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>printAsync(string $template, array $data, ...): PromiseInterface</code></div>
                <p>Same as <code>printWithTemplate</code> but returns a Guzzle Promise for non-blocking execution.</p>
                <pre class="code-block"><code>$promise = $printHub->printAsync('invoice_sewa', $data, referenceId: 'INV-001');
$promise->then(function ($result) {
    echo "Queued: " . $result['job_id'];
});</code></pre>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>printBatch(array $jobs): array</code></div>
                <p>Print multiple jobs in a single request. Each job is an array with template/data/branch_code.</p>
                <pre class="code-block"><code>$result = $printHub->printBatch([
    ['template' => 'invoice_sewa', 'data' => $invoice1, 'reference_id' => 'INV-001'],
    ['template' => 'invoice_sewa', 'data' => $invoice2, 'reference_id' => 'INV-002'],
    ['template' => 'receipt',      'data' => $receipt,  'branch_code'  => 'SDP-JKT'],
]);</code></pre>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>preview(string $template, array $data, array $options = []): string</code></div>
                <p>Generate a PDF preview without sending to the printer. Returns raw PDF bytes.</p>
                <pre class="code-block"><code>$pdfBytes = $printHub->preview('invoice_sewa', $data);
file_put_contents('preview.pdf', $pdfBytes);
// or return as response:
return response($pdfBytes, 200, ['Content-Type' => 'application/pdf']);</code></pre>
            </div>

            {{-- Schema --}}
            <h3 id="schema-api" style="color: var(--warning); margin-top: 2rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border);">Schema Methods</h3>

            <div class="method-block">
                <div class="method-sig"><code>registerSchema(string $schemaName, array $schemaData): array</code></div>
                <p>Register or update a data schema for template data binding.</p>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>validateData(string $templateName, array $data): array</code></div>
                <p>Validate data against a template schema. Returns an array of error messages (empty = valid).</p>
                <pre class="code-block"><code>$errors = $printHub->validateData('invoice_sewa', $data);
if (!empty($errors)) {
    foreach ($errors as $err) echo "⚠️ $err\n";
}</code></pre>
            </div>

            {{-- Jobs --}}
            <h3 id="jobs-api" style="color: var(--info); margin-top: 2rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border);">Job Management</h3>

            <div class="method-block">
                <div class="method-sig"><code>jobStatus(string $jobId): array</code></div>
                <p>Check the current status of a print job.</p>
                <pre class="code-block"><code>$status = $printHub->jobStatus('abc-123');
// { "job_id": "abc-123", "status": "success", "printer": "HP LaserJet", "completed_at": "..." }</code></pre>
            </div>

            <div class="method-block">
                <div class="method-sig"><code>waitForJob(string $jobId, int $timeoutSeconds = 30, int $pollIntervalMs = 500): array</code></div>
                <p>Poll until a job reaches "success" or "failed". Throws exception on timeout.</p>
                <pre class="code-block"><code>try {
    $result = $printHub->waitForJob('abc-123', timeoutSeconds: 60);
    echo "Final status: " . $result['status'];
} catch (PrintHubException $e) {
    echo "Timeout: " . $e->getMessage();
}</code></pre>
            </div>
        </div>

        {{-- Integration Examples --}}
        <div class="card" id="examples">
            <div class="card-header"><h2>🔧 Integration Examples</h2></div>

            <h3 id="laravel-example" style="color: var(--primary); margin-top: 1rem;">Laravel Integration</h3>
            <p style="color: var(--text-muted); margin-bottom: 1rem;">Register the SDK as a singleton in your Laravel service provider:</p>
            <pre class="code-block"><code>// app/Providers/AppServiceProvider.php
public function register()
{
    $this->app->singleton(PrintHubClient::class, function () {
        $client = new PrintHubClient(
            baseUrl: config('services.printhub.url'),
            apiKey:  config('services.printhub.key'),
        );

        // Set branch from config or runtime
        if ($branch = config('services.printhub.branch_code')) {
            $client->setBranch($branch);
        }

        return $client;
    });
}

// config/services.php
'printhub' => [
    'url'         => env('PRINTHUB_URL', 'http://print-hub.local'),
    'key'         => env('PRINTHUB_API_KEY'),
    'branch_code' => env('PRINTHUB_BRANCH', 'SDP-MAIN'),
],

// Usage in a controller:
class InvoiceController extends Controller
{
    public function print(Invoice $invoice, PrintHubClient $printHub)
    {
        $result = $printHub->printWithTemplate(
            template:    'invoice_sewa',
            data:        $invoice->toPrintData(),
            referenceId: $invoice->number,
        );

        return back()->with('success', "Print queued: {$result['job_id']}");
    }
}</code></pre>

            <h3 id="multi-branch" style="color: var(--primary); margin-top: 2.5rem;">Multi-Branch Setup</h3>
            <p style="color: var(--text-muted); margin-bottom: 1rem;">If your app serves multiple branches, resolve the branch dynamically:</p>
            <pre class="code-block"><code>// Option A: Per-request branch
$printHub->printWithTemplate(
    template:   'invoice_sewa',
    data:       $data,
    branchCode: $currentUser->branch_code  // dynamic per request
);

// Option B: Change default branch at runtime
$printHub->setBranch($currentUser->branch_code);
$printHub->printWithTemplate('invoice_sewa', $data);  // uses new branch

// Option C: Discover branches first
$branches = $printHub->getBranches();
foreach ($branches as $branch) {
    echo "{$branch['code']}: {$branch['name']} ({$branch['company']['code']})\n";
}</code></pre>

            <h3 id="error-handling" style="color: var(--danger); margin-top: 2.5rem;">Error Handling</h3>
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
    // API error (4xx/5xx) — e.g. invalid API key, template not found
    Log::error("Print Hub error: " . $e->getMessage());
}</code></pre>
        </div>

        {{-- Troubleshooting --}}
        <div class="card" id="troubleshooting">
            <div class="card-header"><h2>🔍 Troubleshooting</h2></div>

            <div class="trouble-item">
                <strong style="color: var(--danger);">❌ "PrintHubClient error: Unauthorized [401]"</strong>
                <p>Your API key is invalid or expired. Check <strong>Client Apps</strong> page to verify the key.</p>
            </div>

            <div class="trouble-item">
                <strong style="color: var(--danger);">❌ "No online agents available"</strong>
                <p>No print agents are connected in the target branch. Check the <strong>Agents</strong> page to verify agent status. Ensure the TrayPrint app is running on the target PC.</p>
            </div>

            <div class="trouble-item">
                <strong style="color: var(--warning);">⚠️ "Schema validation failed"</strong>
                <p>Your data doesn't match the template's required schema. Use <code>getTemplateSchema()</code> to see required fields, or pass <code>['skip_validation' => true]</code> to bypass.</p>
            </div>

            <div class="trouble-item">
                <strong style="color: var(--warning);">⚠️ Branch routing not working</strong>
                <p>Ensure the branch has <strong>template defaults</strong> configured. Go to <strong>Branches → [branch] → Defaults</strong> and assign a default queue for each template.</p>
            </div>

            <div class="trouble-item">
                <strong style="color: var(--info);">ℹ️ Job stays in "queued" status</strong>
                <p>The agent hasn't picked up the job yet. Check if the target agent is online and actively polling. Jobs time out after the agent's configured interval.</p>
            </div>

            <div class="trouble-item">
                <strong style="color: var(--info);">ℹ️ Using setBranch() vs passing branchCode</strong>
                <p><code>setBranch()</code> sets a default for all subsequent calls. Passing <code>branchCode</code> per-call overrides it. If your app serves only one branch, use <code>setBranch()</code> once at initialization.</p>
            </div>
        </div>

    </div>
</div>

<style>
.sdk-nav {
    display: block;
    padding: 0.35rem 0.75rem;
    font-size: 0.8rem;
    color: var(--text-muted);
    text-decoration: none;
    border-radius: 4px;
    transition: all 0.15s;
}
.sdk-nav:hover {
    color: var(--text);
    background: var(--surface-hover);
}
.sdk-nav.sub {
    padding-left: 1.5rem;
    font-size: 0.75rem;
}

.code-block {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 1rem;
    overflow-x: auto;
    margin-bottom: 1rem;
    font-family: 'Fira Code', 'Cascadia Code', monospace;
    font-size: 0.78rem;
    line-height: 1.6;
    color: var(--text);
}

.method-block {
    padding: 1rem 0;
    border-bottom: 1px solid var(--border);
}
.method-block:last-child {
    border-bottom: none;
}
.method-sig {
    font-size: 0.85rem;
    margin-bottom: 0.5rem;
    padding: 0.4rem 0.8rem;
    background: rgba(99, 102, 241, 0.08);
    border-radius: 6px;
    display: inline-block;
}
.method-sig code {
    font-family: 'Fira Code', 'Cascadia Code', monospace;
    color: var(--primary-hover);
}
.method-block p {
    color: var(--text-muted);
    font-size: 0.85rem;
    margin: 0.5rem 0;
}

.trouble-item {
    padding: 1rem;
    background: var(--bg);
    border-radius: 8px;
    border: 1px solid var(--border);
    margin-bottom: 0.75rem;
}
.trouble-item p {
    color: var(--text-muted);
    font-size: 0.85rem;
    margin-top: 0.5rem;
}

h3 {
    font-size: 1rem;
    font-weight: 600;
}
</style>
@endsection
