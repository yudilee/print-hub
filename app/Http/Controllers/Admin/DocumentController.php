<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrintDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    /**
     * Allowed MIME types for document uploads.
     */
    const ALLOWED_MIMES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/jpg',
    ];

    public function index()
    {
        $documents = PrintDocument::with('user:id,name')
            ->withCount('subsequentVersions')
            ->latest()
            ->paginate(25);

        return view('admin.documents.index', compact('documents'));
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file'           => 'required|file|max:51200', // 50MB in KB
            'page_count'     => 'nullable|integer|min:1',
            'retain_until'   => 'nullable|date|after:today',
            'auto_delete'    => 'nullable|boolean',
        ]);

        $file = $request->file('file');
        $mimeType = $file->getMimeType();

        if (!in_array($mimeType, self::ALLOWED_MIMES, true)) {
            return redirect()->route('admin.documents')
                ->with('toast_error', 'Invalid file type. Allowed: PDF, PNG, JPG.');
        }

        $originalName = $file->getClientOriginalName();
        $fileSize = $file->getSize();

        // Check if a document with the same original name exists for versioning
        $existingDocument = PrintDocument::where('original_name', $originalName)
            ->where('user_id', Auth::id())
            ->latest()
            ->first();

        $version = 1;
        $previousVersionId = null;

        if ($existingDocument) {
            $version = ($existingDocument->version ?? 1) + 1;
            $previousVersionId = $existingDocument->id;
        }

        $storedFilename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $storagePath = "documents/{$storedFilename}";

        Storage::disk('local')->put($storagePath, file_get_contents($file->getRealPath()));

        PrintDocument::create([
            'user_id'            => Auth::id(),
            'original_name'      => $originalName,
            'stored_filename'    => $storedFilename,
            'mime_type'          => $mimeType,
            'file_size'          => $fileSize,
            'page_count'         => $request->input('page_count'),
            'disk'               => 'local',
            'storage_path'       => $storagePath,
            'retain_until'       => $request->input('retain_until'),
            'auto_delete'        => $request->boolean('auto_delete'),
            'version'            => $version,
            'previous_version_id' => $previousVersionId,
        ]);

        return redirect()->route('admin.documents')->with('success', 'Document uploaded successfully.');
    }

    /**
     * Purge all documents past their retain_until date.
     */
    public function purgeExpired()
    {
        $query = PrintDocument::whereNotNull('retain_until')
            ->where('retain_until', '<', now())
            ->where('auto_delete', true);

        $count = $query->count();
        $deleted = 0;

        $query->chunk(100, function ($documents) use (&$deleted) {
            foreach ($documents as $document) {
                try {
                    if ($document->storage_path && Storage::disk($document->disk ?? 'local')->exists($document->storage_path)) {
                        Storage::disk($document->disk ?? 'local')->delete($document->storage_path);
                    }
                    $document->forceDelete();
                    $deleted++;
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to purge document ID {$document->id}: {$e->getMessage()}");
                }
            }
        });

        if ($deleted === 0) {
            return redirect()->route('admin.documents')->with('info', 'No expired documents to purge.');
        }

        return redirect()->route('admin.documents')->with('success', "Purged {$deleted} expired document(s).");
    }

    /**
     * Show version history for a document.
     */
    public function versions($id)
    {
        $document = PrintDocument::with('user:id,name')->findOrFail($id);

        // Collect all versions by traversing the linked list
        $versions = collect();
        $current = $document;

        // Walk backwards to find the first version
        while ($current->previousVersion) {
            $current = $current->previousVersion;
        }

        // Walk forward collecting all versions
        $versionNumber = 1;
        do {
            $current->version_label = $versionNumber;
            $versions->push($current);
            $current = $current->subsequentVersions()->with('user:id,name')->first();
            $versionNumber++;
        } while ($current);

        return view('admin.documents.versions', compact('document', 'versions'));
    }

    public function destroy($id)
    {
        $document = PrintDocument::findOrFail($id);
        $document->delete(); // soft delete

        return redirect()->route('admin.documents')->with('success', 'Document deleted.');
    }
}
