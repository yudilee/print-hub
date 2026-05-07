<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientApp;
use App\Models\WebhookDelivery;
use App\Services\WebhookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    /**
     * List all client apps with their webhook configuration.
     */
    public function index()
    {
        $clientApps = ClientApp::withCount(['webhookDeliveries as last_delivery_status' => function ($q) {
            $q->select(\DB::raw('COALESCE(
                (SELECT status FROM webhook_deliveries
                 WHERE client_app_id = client_apps.id
                 ORDER BY id DESC LIMIT 1),
                \'none\'
            )'));
        }])->latest()->get();

        return view('admin.webhooks.index', compact('clientApps'));
    }

    /**
     * Update webhook configuration for a client app.
     */
    public function update(Request $request, ClientApp $clientApp)
    {
        $data = $request->validate([
            'webhook_url'       => 'nullable|url|max:500',
            'webhook_secret'    => 'nullable|string|max:255',
            'webhook_events'    => 'nullable|array',
            'webhook_events.*'  => 'string|in:job.completed,job.failed,job.queued,agent.online,agent.offline',
            'webhook_retry_count' => 'nullable|integer|min:0|max:10',
            'webhook_timeout'   => 'nullable|integer|min:1|max:30',
            'is_active'         => 'nullable|boolean',
        ]);

        $clientApp->update([
            'webhook_url'        => $data['webhook_url'] ?? null,
            'webhook_secret'     => $data['webhook_secret'] ?? null,
            'webhook_events'     => $data['webhook_events'] ?? [],
            'webhook_retry_count' => $data['webhook_retry_count'] ?? 3,
            'webhook_timeout'    => $data['webhook_timeout'] ?? 10,
            'is_active'          => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.webhooks.index')
            ->with('success', 'Webhook configuration updated for <strong>' . e($clientApp->name) . '</strong>.');
    }

    /**
     * Show delivery history for a specific client app.
     */
    public function deliveries(Request $request, ClientApp $clientApp)
    {
        $query = WebhookDelivery::where('client_app_id', $clientApp->id);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Add delivery duration (in seconds) as a computed attribute
        $deliveries = $query->select('*')
            ->selectRaw('EXTRACT(EPOCH FROM COALESCE(updated_at, created_at) - created_at)::integer as delivery_duration')
            ->latest()
            ->paginate(25);

        // Count stats for the summary cards
        $stats = [
            'total'     => $clientApp->webhookDeliveries()->count(),
            'success'   => $clientApp->webhookDeliveries()->where('status', 'success')->count(),
            'failed'    => $clientApp->webhookDeliveries()->where('status', 'failed')->count(),
            'retrying'  => $clientApp->webhookDeliveries()->where('status', 'retrying')->count(),
            'pending'   => $clientApp->webhookDeliveries()->where('status', 'pending')->count(),
        ];

        return view('admin.webhooks.deliveries', compact('clientApp', 'deliveries', 'stats'));
    }

    /**
     * Retry a specific webhook delivery.
     */
    public function retryDelivery(WebhookDelivery $delivery)
    {
        if (!in_array($delivery->status, ['failed', 'retrying'])) {
            return redirect()->back()
                ->with('toast_error', 'Only failed or retrying deliveries can be retried.');
        }

        $service = app(WebhookService::class);
        $service->deliver($delivery);

        $status = $delivery->fresh()->status;

        return redirect()->back()
            ->with('toast_success', "Delivery #{$delivery->id} retried. Status: {$status}.");
    }

    /**
     * Bulk retry all failed deliveries for a specific client app.
     */
    public function bulkRetry(ClientApp $clientApp)
    {
        $deliveries = WebhookDelivery::where('client_app_id', $clientApp->id)
            ->whereIn('status', ['failed', 'retrying'])
            ->get();

        if ($deliveries->isEmpty()) {
            return redirect()->back()
                ->with('toast_error', 'No failed or retrying deliveries found for <strong>' . e($clientApp->name) . '</strong>.');
        }

        $service = app(WebhookService::class);
        $retried = 0;

        foreach ($deliveries as $delivery) {
            $service->deliver($delivery);
            $retried++;
        }

        return redirect()->back()
            ->with('toast_success', "Bulk retry initiated for {$retried} delivery(s) of <strong>" . e($clientApp->name) . '</strong>.');
    }

    /**
     * Export delivery logs as CSV for a specific client app.
     */
    public function exportDeliveriesCsv(Request $request, ClientApp $clientApp)
    {
        $query = WebhookDelivery::where('client_app_id', $clientApp->id);

        // Apply same filters as the deliveries view
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $deliveries = $query->latest()->get();

        $filename = 'webhook-deliveries-' . Str::slug($clientApp->name) . '-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($deliveries) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fputs($handle, "\xEF\xBB\xBF");

            // Header row
            fputcsv($handle, [
                'ID',
                'Event Type',
                'Status',
                'HTTP Code',
                'Attempts',
                'Max Attempts',
                'Response Body',
                'Error Message',
                'Last Attempt At',
                'Created At',
                'Updated At',
                'Duration (s)',
            ]);

            foreach ($deliveries as $delivery) {
                $duration = $delivery->created_at && $delivery->updated_at
                    ? $delivery->created_at->diffInSeconds($delivery->updated_at)
                    : '';

                fputcsv($handle, [
                    $delivery->id,
                    $delivery->event_type,
                    $delivery->status,
                    $delivery->response_code,
                    $delivery->attempts,
                    $delivery->max_attempts,
                    Str::limit($delivery->response_body, 500),
                    $delivery->error_message,
                    $delivery->last_attempt_at?->format('Y-m-d H:i:s'),
                    $delivery->created_at->format('Y-m-d H:i:s'),
                    $delivery->updated_at->format('Y-m-d H:i:s'),
                    $duration,
                ]);
            }

            fclose($handle);
        };

        return Response::stream($callback, 200, $headers);
    }
}
