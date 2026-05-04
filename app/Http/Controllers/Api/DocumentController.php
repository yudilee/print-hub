<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\PrintDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * DocumentController handles upload, preview, download, and management
 * of documents associated with print jobs.
 */
class DocumentController extends Controller
{
    /**
     * Maximum allowed file size in bytes (50 MB).
     */
    const MAX_FILE_SIZE = 50 * 1024 * 1024;

    /**
     * Allowed MIME types.
     */
    const ALLOWED_MIMES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/jpg',
    ];

    // -------------------------------------------------------------------------
    // POST /api/v1/documents/upload
    // -------------------------------------------------------------------------

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:51200', // 50MB in KB
            'page_count' => 'nullable|integer|min:1',
        ]);

        $file = $request->file('file');
        $mimeType = $file->getMimeType();

        if (!in_array($mimeType, self::ALLOWED_MIMES, true)) {
            return ApiResponse::validationError(
                'Invalid file type. Allowed types: PDF, PNG, JPG.'
            );
        }

        $originalName = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $storedFilename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $storagePath = "documents/{$storedFilename}";

        // Store the file
        Storage::disk('local')->put($storagePath, file_get_contents($file->getRealPath()));

        $document = PrintDocument::create([
            'user_id'         => Auth::id(),
            'original_name'   => $originalName,
            'stored_filename' => $storedFilename,
            'mime_type'       => $mimeType,
            'file_size'       => $fileSize,
            'page_count'      => $request->input('page_count'),
            'disk'            => 'local',
            'storage_path'    => $storagePath,
            'metadata'        => $request->input('metadata'),
        ]);

        return ApiResponse::success([
            'document' => $this->formatDocument($document),
        ], 201);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/documents
    // -------------------------------------------------------------------------

    public function list(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 25), 100);

        $documents = PrintDocument::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $documents->through(fn($d) => $this->formatDocument($d));

        return ApiResponse::success([
            'documents' => $documents->items(),
            'meta'      => [
                'current_page' => $documents->currentPage(),
                'per_page'     => $documents->perPage(),
                'total'        => $documents->total(),
                'last_page'    => $documents->lastPage(),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/documents/{id}
    // -------------------------------------------------------------------------

    public function show($id)
    {
        $document = PrintDocument::where('user_id', Auth::id())->findOrFail($id);

        return ApiResponse::success([
            'document' => $this->formatDocument($document),
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/documents/{id}/preview
    // -------------------------------------------------------------------------

    public function preview($id)
    {
        $document = PrintDocument::where('user_id', Auth::id())->findOrFail($id);

        if (!Storage::disk($document->disk)->exists($document->storage_path)) {
            return ApiResponse::notFound('DOCUMENT_NOT_FOUND', 'Document file not found on disk.');
        }

        $fileContent = Storage::disk($document->disk)->get($document->storage_path);

        return response($fileContent, 200, [
            'Content-Type'        => $document->mime_type,
            'Content-Disposition' => 'inline; filename="' . $document->original_name . '"',
            'Content-Length'      => $document->file_size,
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/documents/{id}/download
    // -------------------------------------------------------------------------

    public function download($id)
    {
        $document = PrintDocument::where('user_id', Auth::id())->findOrFail($id);

        if (!Storage::disk($document->disk)->exists($document->storage_path)) {
            return ApiResponse::notFound('DOCUMENT_NOT_FOUND', 'Document file not found on disk.');
        }

        $fileContent = Storage::disk($document->disk)->get($document->storage_path);

        return response($fileContent, 200, [
            'Content-Type'        => $document->mime_type,
            'Content-Disposition' => 'attachment; filename="' . $document->original_name . '"',
            'Content-Length'      => $document->file_size,
        ]);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/v1/documents/{id}
    // -------------------------------------------------------------------------

    public function destroy($id)
    {
        $document = PrintDocument::where('user_id', Auth::id())->findOrFail($id);

        // Soft delete (file remains on disk for existing print jobs)
        $document->delete();

        return ApiResponse::success([
            'message' => 'Document deleted successfully.',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function formatDocument(PrintDocument $document): array
    {
        return [
            'id'              => $document->id,
            'original_name'   => $document->original_name,
            'mime_type'       => $document->mime_type,
            'file_size'       => $document->file_size,
            'formatted_size'  => $document->formatted_size,
            'page_count'      => $document->page_count,
            'preview_url'     => $document->preview_url,
            'metadata'        => $document->metadata,
            'created_at'      => $document->created_at?->toISOString(),
        ];
    }
}
