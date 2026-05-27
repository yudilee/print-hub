<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates the size of incoming POST/PUT/PATCH request bodies against a
 * configurable maximum (default: 50 MB).
 *
 * Returns a structured JSON error response (instead of a vague "413 Request
 * Entity Too Large" from the web server) so that client applications can
 * handle the error gracefully.
 */
class ValidatePostSize
{
    /**
     * Maximum allowed request body size in bytes.
     * Defaults to 50 MB — configure via the VALIDATE_POST_SIZE_MAX env  or
     * by overriding the constant in a subclass.
     */
    public const MAX_BYTES = 52_428_800; // 50 MB

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isBodyMethod($request->method())) {
            return $next($request);
        }

        $maxBytes = $this->getMaxBytes();

        // Check Content-Length header if available (fast path)
        $contentLength = $request->header('Content-Length');
        if ($contentLength !== null && (int) $contentLength > $maxBytes) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'PAYLOAD_TOO_LARGE',
                    'message' => "Request body exceeds the maximum allowed size of "
                        . $this->formatBytes($maxBytes)
                        . ". Please reduce the payload size or use the document upload endpoint for large files.",
                ],
            ], 413);
        }

        // Fallback: check actual content size after PHP has read it
        // (catches chunked transfer encoding where Content-Length is absent)
        $content = $request->getContent();
        if (mb_strlen($content, '8bit') > $maxBytes) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'PAYLOAD_TOO_LARGE',
                    'message' => "Request body exceeds the maximum allowed size of "
                        . $this->formatBytes($maxBytes)
                        . ". Please reduce the payload size or use the document upload endpoint for large files.",
                ],
            ], 413);
        }

        return $next($request);
    }

    /**
     * Returns whether the HTTP method typically carries a request body.
     */
    protected function isBodyMethod(string $method): bool
    {
        return in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    /**
     * Get the maximum allowed bytes — pulled from env or the class constant.
     */
    protected function getMaxBytes(): int
    {
        return (int) (env('VALIDATE_POST_SIZE_MAX') ?: static::MAX_BYTES);
    }

    /**
     * Format bytes into a human-readable string.
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i     = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
