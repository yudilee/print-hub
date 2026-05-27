<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MfaToken stores TOTP-based multi-factor authentication configuration
 * for admin users.
 *
 * Each user can have one MFA token record containing:
 * - The Base32-encoded TOTP secret
 * - Whether MFA is enabled
 * - Last verified timestamp
 * - Recovery codes for account recovery
 *
 * @property int $id
 * @property int $user_id
 * @property string $secret  Base32-encoded TOTP secret
 * @property bool $is_enabled
 * @property \Carbon\Carbon|null $last_verified_at
 * @property array|null $recovery_codes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class MfaToken extends Model
{
    protected $fillable = [
        'user_id',
        'secret',
        'is_enabled',
        'last_verified_at',
        'recovery_codes',
    ];

    protected $casts = [
        'is_enabled'       => 'boolean',
        'last_verified_at' => 'datetime',
        'recovery_codes'   => 'array',
    ];

    /**
     * The user this MFA token belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a random Base32-encoded TOTP secret.
     */
    public static function generateSecret(): string
    {
        $bytes = random_bytes(20); // 160 bits as recommended by RFC 4226
        return self::base32Encode($bytes);
    }

    /**
     * Generate a set of one-time recovery codes.
     *
     * @param int $count Number of codes to generate
     * @return array<string>
     */
    public static function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(
                substr(bin2hex(random_bytes(4)), 0, 4) . '-' .
                substr(bin2hex(random_bytes(4)), 0, 4)
            );
        }
        return $codes;
    }

    /**
     * Base32 encode binary data (RFC 4648).
     */
    public static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $result = '';
        $bits = 0;
        $buffer = 0;

        for ($i = 0; $i < strlen($data); $i++) {
            $buffer = ($buffer << 8) | ord($data[$i]);
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $result .= $alphabet[($buffer >> $bits) & 0x1F];
            }
        }

        if ($bits > 0) {
            $result .= $alphabet[($buffer << (5 - $bits)) & 0x1F];
        }

        // Add padding
        $padLength = (8 - (strlen($result) % 8)) % 8;
        $result .= str_repeat('=', $padLength);

        return $result;
    }

    /**
     * Base32 decode a string back to binary.
     */
    public static function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = strtoupper(str_replace('=', '', $data));
        $result = '';
        $bits = 0;
        $buffer = 0;

        for ($i = 0; $i < strlen($data); $i++) {
            $pos = strpos($alphabet, $data[$i]);
            if ($pos === false) continue;

            $buffer = ($buffer << 5) | $pos;
            $bits += 5;

            if ($bits >= 8) {
                $bits -= 8;
                $result .= chr(($buffer >> $bits) & 0xFF);
            }
        }

        return $result;
    }

    /**
     * Generate a TOTP code for the given secret and timestamp.
     *
     * Implements TOTP (RFC 6238) with 30-second time step, 6 digits,
     * and SHA-1 HMAC as per the standard.
     *
     * @param string $secret      Base32-encoded secret
     * @param int|null $timestamp Unix timestamp (default: current time)
     * @return string  6-digit TOTP code
     */
    public static function generateTotp(string $secret, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $timeStep = 30;

        // Counter
        $counter = pack('N*', 0) . pack('N*', intdiv($timestamp, $timeStep));

        // HMAC-SHA1
        $decodedSecret = self::base32Decode($secret);
        $hash = hash_hmac('sha1', $counter, $decodedSecret, true);

        // Dynamic truncation
        $offset = ord($hash[19]) & 0x0F;
        $binary = (
            (ord($hash[$offset]) & 0x7F) << 24 |
            (ord($hash[$offset + 1]) & 0xFF) << 16 |
            (ord($hash[$offset + 2]) & 0xFF) << 8 |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        // Generate 6-digit code
        $otp = $binary % 1000000;

        return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a TOTP code against the stored secret.
     *
     * Accepts codes within a window of ±1 time step (90 seconds total)
     * to account for clock drift.
     *
     * @param string $code The 6-digit code to verify
     * @return bool
     */
    public function verifyTotp(string $code): bool
    {
        $timestamp = time();

        // Check current time step and adjacent steps (±1)
        for ($i = -1; $i <= 1; $i++) {
            $expected = self::generateTotp($this->secret, $timestamp + ($i * 30));
            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verify a recovery code and consume it if valid.
     *
     * @param string $code Recovery code to verify
     * @return bool
     */
    public function verifyRecoveryCode(string $code): bool
    {
        $codes = $this->recovery_codes ?? [];

        $index = array_search(strtoupper($code), $codes, true);

        if ($index === false) {
            return false;
        }

        // Consume the code by removing it
        unset($codes[$index]);
        $this->update(['recovery_codes' => array_values($codes)]);

        return true;
    }

    /**
     * Get the TOTP URI for QR code generation (otpauth:// URI).
     *
     * @param string $issuer  The issuer name (e.g., "Print Hub")
     * @param string $account The account identifier (e.g., user email)
     * @return string
     */
    public function getTotpUri(string $issuer, string $account): string
    {
        $encodedIssuer = rawurlencode($issuer);
        $encodedAccount = rawurlencode($account);

        return "otpauth://totp/{$encodedIssuer}:{$encodedAccount}"
            . "?secret={$this->secret}"
            . "&issuer={$encodedIssuer}"
            . "&algorithm=SHA1"
            . "&digits=6"
            . "&period=30";
    }
}
