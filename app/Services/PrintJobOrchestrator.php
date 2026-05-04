<?php

namespace App\Services;

use App\Models\PrintAgent;
use App\Models\PrintJob;
use App\Models\PrintProfile;
use App\Models\PrintTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrintJobOrchestrator
{
    /**
     * Generate a PDF from template + data and store it.
     *
     * @return array{filePath: string, type: string, templateName: string|null, validationWarnings: array}
     */
    public function generateFromTemplate(string $templateName, array $printData, array $options = [], bool $skipValidation = false): array
    {
        $template = PrintTemplate::with('dataSchema')->where('name', $templateName)->first();

        if (!$template) {
            throw new \RuntimeException("Template '{$templateName}' not found.");
        }

        $validationWarnings = [];

        if ($template->dataSchema && !$skipValidation) {
            $errors = $template->dataSchema->validateData($printData);
            if (!empty($errors)) {
                $validationWarnings = $errors;
            }
        }

        $engine = new ContinuousFormEngine();
        $pdfBinary = $engine->generate($template, $printData, $options);

        $jobId = (string) Str::uuid();
        $filePath = "print_jobs/{$jobId}.pdf";

        Storage::put($filePath, $pdfBinary);

        return [
            'filePath'           => $filePath,
            'type'               => 'pdf',
            'templateName'       => $template->name,
            'validationWarnings' => $validationWarnings,
        ];
    }

    /**
     * Decode and store a base64-encoded document.
     *
     * @return array{filePath: string, type: string}
     */
    public function generateFromBase64(string $base64Data, ?string $type = null): array
    {
        $base64Data = preg_replace('/\s+/', '', $base64Data);

        if (strlen($base64Data) % 4 === 1) {
            throw new \RuntimeException('Invalid base64 string length.');
        }

        $decoded = base64_decode($base64Data, true);
        if ($decoded === false) {
            throw new \RuntimeException('Invalid base64-encoded content.');
        }

        $resolvedType = $type ?? 'pdf';
        $extension = ($resolvedType === 'pdf') ? 'pdf' : 'raw';
        $jobId = (string) Str::uuid();
        $filePath = "print_jobs/{$jobId}.{$extension}";

        Storage::put($filePath, $decoded);

        return [
            'filePath' => $filePath,
            'type'     => $resolvedType,
        ];
    }

    /**
     * Create a PrintJob record in the database.
     */
    public function createJob(
        string $filePath,
        PrintAgent $agent,
        ?int $branchId,
        string $printer,
        string $type,
        array $options = [],
        ?string $webhookUrl = null,
        ?string $referenceId = null,
        ?string $templateName = null,
        ?array $templateData = null,
    ): PrintJob {
        $jobId = pathinfo($filePath, PATHINFO_FILENAME);

        return PrintJob::create([
            'job_id'         => $jobId,
            'print_agent_id' => $agent->id,
            'branch_id'      => $branchId,
            'printer_name'   => $printer,
            'type'           => $type,
            'status'         => 'pending',
            'file_path'      => $filePath,
            'webhook_url'    => $webhookUrl,
            'reference_id'   => $referenceId,
            'options'        => $options,
            'template_data'  => $templateData,
            'template_name'  => $templateName,
        ]);
    }

    /**
     * Resolve printer name from request, profile, or fallback.
     */
    public static function resolvePrinter(?string $requestedPrinter, ?PrintProfile $profile): string
    {
        if ($requestedPrinter) {
            return $requestedPrinter;
        }

        if ($profile && $profile->default_printer) {
            return $profile->default_printer;
        }

        $p = PrintProfile::first();
        return $p?->default_printer ?? 'Default';
    }

    /**
     * Build print options by merging profile defaults with request options.
     */
    public static function mergeProfileOptions(?PrintProfile $profile, array $requestOptions = []): array
    {
        $options = [];

        if ($profile) {
            $options = [
                'orientation'    => $profile->orientation,
                'copies'         => $profile->copies,
                'duplex'         => $profile->duplex,
                'margin_top'     => $profile->margin_top,
                'margin_bottom'  => $profile->margin_bottom,
                'margin_left'    => $profile->margin_left,
                'margin_right'   => $profile->margin_right,
                'fit_to_page'    => $profile->extra_options['fit_to_page'] ?? false,
            ];

            $dimensions = PaperSizeService::resolveFromProfile($profile);
            $options['paper_width_mm']  = $dimensions['width_mm'];
            $options['paper_height_mm'] = $dimensions['height_mm'];
        }

        return array_merge($options, $requestOptions);
    }
}
