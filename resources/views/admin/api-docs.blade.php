@extends('admin.layout')
@section('title', 'API Documentation')

@section('head')
<style>
    .swagger-ui .topbar { display: none; }
    #swagger-ui { max-width: 100%; overflow-x: auto; }
    .swagger-ui .info .title { color: var(--text) !important; }
    .swagger-ui .info { margin: 20px 0; }
    .swagger-ui .scheme-container { background: var(--surface) !important; border: 1px solid var(--border) !important; }
    .swagger-ui .opblock-tag { color: var(--text) !important; }
    .swagger-ui .opblock .opblock-section-header { background: var(--surface) !important; }
    .swagger-ui .opblock .opblock-summary-description { color: var(--text-muted) !important; }
    .swagger-ui table thead tr td, .swagger-ui table thead tr th { color: var(--text-muted) !important; }
    .swagger-ui .model-box { background: var(--bg) !important; }
    .swagger-ui .model { color: var(--text) !important; }
    .swagger-ui .parameter__name { color: var(--text) !important; }
    .swagger-ui .parameter__type { color: var(--text-muted) !important; }
    .swagger-ui .opblock-body pre { background: var(--bg) !important; color: var(--text) !important; }
    .swagger-ui .response-col_status { color: var(--text) !important; }
    .swagger-ui .response-col_links { color: var(--text-muted) !important; }
    .swagger-ui .responses-inner h4, .swagger-ui .responses-inner h5 { color: var(--text) !important; }
    .swagger-ui .btn { font-size: 0.8rem !important; }
    .swagger-ui input[type=text] { background: var(--bg) !important; color: var(--text) !important; border-color: var(--border) !important; }
    .swagger-ui select { background: var(--bg) !important; color: var(--text) !important; border-color: var(--border) !important; }
</style>
@endsection

@section('content')
<x-breadcrumb :items="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'API Documentation']]" />

<div class="page-header">
    <h1>API Documentation</h1>
    <p>Interactive documentation for the Print Hub REST API. Based on the OpenAPI 3.0 specification.</p>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <div id="swagger-ui"></div>
</div>

<!-- Swagger UI CDN -->
<link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
<script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js" crossorigin></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    SwaggerUIBundle({
        url: '{{ asset('sdk/openapi.yaml') }}',
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [
            SwaggerUIBundle.presets.apis,
            SwaggerUIBundle.SwaggerUIStandalonePreset
        ],
        layout: 'BaseLayout',
        defaultModelsExpandDepth: 1,
        docExpansion: 'list',
        filter: true,
        showExtensions: true,
        showCommonExtensions: true,
        tryItOutEnabled: false,
    });
});
</script>
@endsection
