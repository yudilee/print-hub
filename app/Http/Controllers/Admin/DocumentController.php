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
            ->latest()
            ->paginate(25);

        return view('admin.documents.index', compact('documents'));
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file'       => 'required|file|max:51200', // 50MB in KB
            'page_count' => 'nullable|integer|min:1',
        ]);

        $file = $request->file('file');
        $mimeType = $file->getMimeType();

        if (!in_array($mimeType, self::ALLOWED_MIMES, true)) {
            return redirect()->route('admin.documents')
                ->with('toast_error', 'Invalid file type. Allowed: PDF, PNG, JPG.');
        }

        $originalName = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $storedFilename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $storagePath = "documents/{$storedFilename}";

        Storage::disk('local')->put($storagePath, file_get_contents($file->getRealPath()));

        PrintDocument::create([
            'user_id'         => Auth::id(),
            'original_name'   => $originalName,
            'stored_filename' => $storedFilename,
            'mime_type'       => $mimeType,
            'file_size'       => $fileSize,
            'page_count'      => $request->input('page_count'),
            'disk'            => 'local',
            'storage_path'    => $storagePath,
        ]);

        return redirect()->route('admin.documents')->with('success', 'Document uploaded successfully.');
    }

    public function destroy($id)
    {
        $document = PrintDocument::findOrFail($id);
        $document->delete(); // soft delete

        return redirect()->route('admin.documents')->with('success', 'Document deleted.');
    }
}
