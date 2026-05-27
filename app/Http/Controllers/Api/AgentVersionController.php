<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgentRelease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentVersionController extends Controller
{
    /**
     * Return the latest agent release version for the requested platform.
     *
     * Accepts optional ?platform=linux|windows|macos query parameter.
     * If omitted, returns latest for each platform.
     *
     * GET /api/v1/agents/version
     */
    public function index(Request $request): JsonResponse
    {
        $platform = $request->query('platform');

        if ($platform) {
            // Return latest for a specific platform
            $release = AgentRelease::forPlatform($platform)
                ->latestPerPlatform()
                ->latest('created_at')
                ->first();

            if (! $release) {
                return response()->json([
                    'latest_version' => null,
                    'download_url'   => null,
                    'sha256'         => null,
                    'release_notes'  => null,
                    'mandatory'      => false,
                    'message'        => 'No release found for platform: ' . $platform,
                ], 404);
            }

            return response()->json([
                'latest_version' => $release->version,
                'download_url'   => $release->getDownloadUrl(),
                'sha256'         => $release->sha256_hash,
                'release_notes'  => $release->release_notes,
                'mandatory'      => $release->is_mandatory,
            ]);
        }

        // Return latest version for each platform
        $platforms = ['linux', 'windows', 'macos'];
        $result = [];

        foreach ($platforms as $p) {
            $release = AgentRelease::forPlatform($p)
                ->latestPerPlatform()
                ->latest('created_at')
                ->first();

            if ($release) {
                $result[$p] = [
                    'latest_version' => $release->version,
                    'download_url'   => $release->getDownloadUrl(),
                    'sha256'         => $release->sha256_hash,
                    'release_notes'  => $release->release_notes,
                    'mandatory'      => $release->is_mandatory,
                ];
            } else {
                $result[$p] = null;
            }
        }

        return response()->json($result);
    }
}
