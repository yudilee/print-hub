<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * Standardised API response envelope.
 *
 * All API responses from Print Hub follow the shape:
 *
 *   Success:  { "success": true,  "data": { ... } }
 *   Error:    { "success": false, "error": { "code": "...", "message": "..." } }
 *
 * Error codes (machine-readable):
 *   MISSING_API_KEY      — X-API-Key header absent
 *   INVALID_API_KEY      — Key not found or inactive
 *   TEMPLATE_NOT_FOUND   — Template name does not exist
 *   SCHEMA_NOT_FOUND     — Schema name does not exist
 *   BRANCH_NOT_FOUND     — Branch code / ID does not exist
 *   JOB_NOT_FOUND        — Job ID does not exist
 *   NO_AGENT_AVAILABLE   — No online agent to handle job
 *   AGENT_OFFLINE        — Pinned agent is offline
 *   VALIDATION_FAILED    — Request payload did not pass validation
 *   INVALID_DOCUMENT     — Base64 document could not be decoded
 *   JOB_NOT_CANCELLABLE  — Job is not in a cancellable state
 */
class ApiResponse
{
    /**
     * Return a successful response.
     */
    public static function success(array|object $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $data,
        ], $status);
    }

    /**
     * Return an error response.
     */
    public static function error(string $code, string $message, int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error'   => [
                'code'    => $code,
                'message' => $message,
            ],
        ], $status);
    }

    /**
     * Return a 401 Unauthorized error.
     */
    public static function unauthorized(string $code = 'INVALID_API_KEY', string $message = 'Unauthorized.'): JsonResponse
    {
        return static::error($code, $message, 401);
    }

    /**
     * Return a 404 Not Found error.
     */
    public static function notFound(string $code, string $message): JsonResponse
    {
        return static::error($code, $message, 404);
    }

    /**
     * Return a 422 Unprocessable Entity error.
     */
    public static function validationError(string $message, array $details = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error'   => [
                'code'    => 'VALIDATION_FAILED',
                'message' => $message,
                'details' => $details,
            ],
        ], 422);
    }

    /**
     * Return a 503 Service Unavailable error.
     */
    public static function serviceUnavailable(string $code, string $message): JsonResponse
    {
        return static::error($code, $message, 503);
    }
}
