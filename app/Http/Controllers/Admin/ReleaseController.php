<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentRelease;
use App\Traits\LogsActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ReleaseController extends Controller
{
    use LogsActivity;

    /**
     * List all agent releases.
     */
    public function index(): View
    {
        $releases = AgentRelease::with('uploader')
            ->latest()
            ->get();

        return view('admin.releases.index', compact('releases'));
    }

    /**
     * Show the upload form (actually rendered on the same page as index,
     * but we keep this for potential future separate page).
     */
    public function create(): View
    {
        $releases = AgentRelease::with('uploader')
            ->latest()
            ->get();

        return view('admin.releases.index', compact('releases'));
    }

    /**
     * Store a new release with file upload and SHA-256 validation.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'version'        => 'required|string|max:50',
            'platform'       => 'required|in:linux,windows,macos',
            'channel'        => 'required|in:stable,beta,alpha',
            'release_notes'  => 'nullable|string|max:5000',
            'is_mandatory'   => 'nullable|boolean',
            'installer_file' => 'required|file|max:512000', // 500MB max
        ]);

        $file = $request->file('installer_file');

        // Compute SHA-256 hash before storing
        $sha256Hash = hash_file('sha256', $file->getRealPath());

        // Validate SHA-256 if provided separately (optional client-side check field)
        if ($request->filled('sha256_hash') && ! hash_equals($request->input('sha256_hash'), $sha256Hash)) {
            return back()->withErrors(['sha256_hash' => 'SHA-256 hash mismatch. The file may be corrupted.'])->withInput();
        }

        // Store file in agent-releases directory on the local disk
        $storedPath = $file->store('agent-releases', 'local');

        // Create the release record
        $release = AgentRelease::create([
            'version'           => $data['version'],
            'platform'          => $data['platform'],
            'channel'           => $data['channel'],
            'file_original_name'=> $file->getClientOriginalName(),
            'file_stored_path'  => $storedPath,
            'file_mime_type'    => $file->getMimeType(),
            'file_size'         => $file->getSize(),
            'sha256_hash'       => $sha256Hash,
            'release_notes'     => $data['release_notes'] ?? null,
            'is_mandatory'      => $request->boolean('is_mandatory'),
            'is_latest'         => false, // Will be set explicitly via markLatest
            'uploaded_by'       => auth()->id(),
        ]);

        $this->logActivity('release.created', $release, [
            'version'  => $release->version,
            'platform' => $release->platform,
        ]);

        return redirect()->route('admin.releases')
            ->with('success', "Release {$release->version} for {$release->platform} uploaded successfully.");
    }

    /**
     * Delete a release and its associated file.
     */
    public function destroy(AgentRelease $release): RedirectResponse
    {
        // Delete the stored file
        if ($release->file_stored_path && Storage::disk('local')->exists($release->file_stored_path)) {
            Storage::disk('local')->delete($release->file_stored_path);
        }

        $version = $release->version;
        $platform = $release->platform;

        $release->delete();

        $this->logActivity('release.deleted', null, [
            'version'  => $version,
            'platform' => $platform,
        ]);

        return redirect()->route('admin.releases')
            ->with('success', "Release {$version} for {$platform} deleted.");
    }

    /**
     * Mark a release as the latest for its platform (and unmark others).
     */
    public function markLatest(AgentRelease $release): RedirectResponse
    {
        // Unmark all other releases for the same platform and channel
        AgentRelease::where('platform', $release->platform)
            ->where('channel', $release->channel)
            ->where('id', '!=', $release->id)
            ->update(['is_latest' => false]);

        // Mark this release as latest
        $release->update(['is_latest' => true]);

        $this->logActivity('release.marked-latest', $release, [
            'version'  => $release->version,
            'platform' => $release->platform,
        ]);

        return redirect()->route('admin.releases')
            ->with('success', "Release {$release->version} for {$release->platform} marked as latest.");
    }
}
