<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrintFont;
use App\Services\FontService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FontController extends Controller
{
    /**
     * Display a listing of all fonts with search/filter.
     */
    public function index(Request $request)
    {
        $query = PrintFont::with('uploader');

        // Search by name or font_family
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('font_family', 'like', "%{$search}%");
            });
        }

        // Filter by file type
        if ($type = $request->get('file_type')) {
            $query->where('file_type', $type);
        }

        // Filter by active status
        if ($request->has('is_active') && $request->get('is_active') !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $fonts = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('admin.fonts.index', compact('fonts'));
    }

    /**
     * Store a newly uploaded font.
     */
    public function store(Request $request, FontService $fontService)
    {
        $request->validate([
            'name'      => 'required|string|max:255|unique:print_fonts,name',
            'font_file' => 'required|file|mimes:ttf,otf|max:5120', // 5MB max
        ]);

        $file = $request->file('font_file');
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());

        // Store file in the fonts disk
        $fileName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '-' . time() . '.' . $extension;
        $filePath = $file->storeAs('', $fileName, 'fonts');

        if (!$filePath) {
            return back()->withErrors(['font_file' => 'Failed to store font file.']);
        }

        // Generate FPDF definition files for PDF rendering
        try {
            $disk = Storage::disk('fonts');
            $fullFontPath = $disk->path($fileName);
            $fontService->convertToFpdf($fullFontPath);
        } catch (\Throwable $e) {
            // Log the error but don't block the upload; the definition will be
            // generated on-demand in ContinuousFormEngine if needed.
            \Illuminate\Support\Facades\Log::warning('FPDF font definition generation failed: ' . $e->getMessage(), [
                'font_name' => $request->name,
                'file'      => $fileName,
            ]);
        }

        // Auto-detect font family from file name if not explicitly provided
        $fontFamily = $request->get('font_family', pathinfo($originalName, PATHINFO_FILENAME));

        // Determine style from name keywords
        $nameLower = Str::lower($request->get('name', $fontFamily));
        $fontStyle = 'regular';
        if (str_contains($nameLower, 'bold') && str_contains($nameLower, 'italic')) {
            $fontStyle = 'bold_italic';
        } elseif (str_contains($nameLower, 'bold')) {
            $fontStyle = 'bold';
        } elseif (str_contains($nameLower, 'italic')) {
            $fontStyle = 'italic';
        }

        PrintFont::create([
            'name'        => $request->name,
            'font_family' => $fontFamily,
            'font_style'  => $fontStyle,
            'file_path'   => $fileName,
            'file_type'   => $extension,
            'file_size'   => $file->getSize(),
            'is_active'   => true,
            'uploaded_by' => auth()->id(),
        ]);

        return redirect()->route('admin.fonts')->with('success', "Font '{$request->name}' uploaded successfully.");
    }

    /**
     * Update font metadata (name, preview_text, is_active).
     */
    public function update(Request $request, PrintFont $font)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255|unique:print_fonts,name,' . $font->id,
            'preview_text' => 'nullable|string|max:500',
            'is_active'    => 'boolean',
        ]);

        $font->update($data);

        return redirect()->route('admin.fonts')->with('success', "Font '{$font->name}' updated successfully.");
    }

    /**
     * Soft-delete a font (keep the file on disk).
     */
    public function destroy(PrintFont $font)
    {
        $fontName = $font->name;
        $font->delete(); // soft delete

        return redirect()->route('admin.fonts')->with('success', "Font '{$fontName}' deleted (file retained on disk).");
    }

    /**
     * Download the font file.
     */
    public function download(PrintFont $font)
    {
        $disk = Storage::disk('fonts');
        $filePath = $font->file_path;

        if (!$disk->exists($filePath)) {
            return back()->withErrors(['error' => 'Font file not found on disk.']);
        }

        return $disk->download($filePath, $font->name . '.' . $font->file_type);
    }

    /**
     * Serve the font file for browser preview (e.g. for canvas FontFace API).
     */
    public function preview(PrintFont $font)
    {
        $disk = Storage::disk('fonts');
        $filePath = $font->file_path;

        if (!$disk->exists($filePath)) {
            abort(404, 'Font file not found.');
        }

        $mimeType = $font->file_type === 'otf' ? 'font/otf' : 'font/ttf';

        return response($disk->get($filePath), 200, [
            'Content-Type'              => $mimeType,
            'Content-Disposition'       => 'inline; filename="' . $font->file_path . '"',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control'             => 'public, max-age=31536000',
        ]);
    }
}
