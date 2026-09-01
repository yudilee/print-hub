@extends('admin.layout')
@section('title', 'Templates')

@section('content')
<x-breadcrumb :items="[['label' => 'Print Templates']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Print Templates & Visual Designer</h2>
        <p class="text-xs text-slate-400">Design millimeter-perfect layouts for receipts, forms, invoices, and thermal labels</p>
    </div>
    <a href="{{ route('admin.templates.create') }}" class="btn-primary btn-sm">
        <x-icon name="plus" size="13" />
        <span>Create Template</span>
    </a>
</div>

<div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs mb-6">
    <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
            Available Templates: <span class="text-white font-mono font-bold">{{ $templates->count() }}</span>
        </h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800 font-semibold tracking-wider">
                <tr>
                    <th class="px-5 py-3.5">Template Name</th>
                    <th class="px-5 py-3.5">Dimensions (W × H)</th>
                    <th class="px-5 py-3.5">Elements</th>
                    <th class="px-5 py-3.5">Last Updated</th>
                    <th class="px-5 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse($templates as $template)
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="px-5 py-3.5">
                        <span class="font-bold text-white">{{ $template->name }}</span>
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="badge badge-info font-mono">{{ $template->paper_width_mm }} × {{ $template->paper_height_mm }} mm</span>
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-400">
                        @php
                            $els = $template->elements ?? [];
                            if (is_array($els) && isset($els['sections']) && isset($els['elements'])) {
                                $count = count($els['elements']);
                            } elseif (is_array($els)) {
                                $count = count($els);
                            } else {
                                $count = 0;
                            }
                        @endphp
                        {{ $count }} element(s)
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-400 font-mono">
                        {{ $template->updated_at->diffForHumans() }}
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="inline-flex items-center gap-1.5">
                            <a href="{{ route('admin.templates.edit', $template) }}" class="btn-secondary btn-sm">Visual Editor</a>
                            <form action="{{ route('admin.templates.clone', $template) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="btn-secondary btn-sm">Clone</button>
                            </form>
                            <form action="{{ route('admin.templates.destroy', $template) }}" method="POST" onsubmit="return confirm('Delete this template?')" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5">
                        <x-empty-state icon="📐" title="No templates designed yet" description="Build dynamic form layouts and millimeter-calibrated label templates." actionText="Create Template" :actionUrl="route('admin.templates.create')" />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xs">
    <h3 class="text-sm font-bold text-white mb-2">⚡ API Template Integration</h3>
    <p class="text-xs text-slate-400 mb-3">Submit JSON payloads to <code class="text-blue-400 font-mono">POST /api/v1/jobs</code> referencing your template slug:</p>
    <pre class="p-4 rounded-xl bg-slate-950 border border-slate-800 font-mono text-xs text-slate-300 overflow-x-auto">{
  "template": "invoice_v1",
  "printer": "Brother-HL-L2360D",
  "template_data": {
    "customer_name": "Acme Corp",
    "invoice_no": "INV-2026-001"
  }
}</pre>
</div>
@endsection
