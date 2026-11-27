<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;

/**
 * Offline licensing for the desktop build.
 *
 * Key format:  TDS1.<base64url(json payload)>.<base64url(ed25519 signature)>
 * Payload:     {"id": installation id, "school": name, "plan": yearly|lifetime|..., "exp": "Y-m-d"|null, "iat": "Y-m-d"}
 * Keys are signed on the vendor's website with the secret key; the app only holds the public key.
 */
class LicenseService
{
    public function enabled(): bool
    {
        return (bool) config('license.enabled');
    }

    /** Stable ID of this installation, e.g. TDS-7K2P-QX9M-4HDA */
    public function installationId(): string
    {
        $id = Setting::get('installation_id');
        if (! $id) {
            $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // no 0/O/1/I
            $chars = '';
            foreach (str_split(random_bytes(12)) as $b) {
                $chars .= $alphabet[ord($b) % 32];
            }
            $id = 'TDS-'.implode('-', str_split($chars, 4));
            Setting::put('installation_id', $id);
        }

        return $id;
    }

    /** "Today", protected against the PC clock being turned back. */
    public function today(): Carbon
    {
        $today = now()->startOfDay();
        $lastSeen = Setting::get('license_last_seen');

        if ($lastSeen && Carbon::parse($lastSeen)->gt($today)) {
            return Carbon::parse($lastSeen)->startOfDay();
        }
        if ($lastSeen !== $today->toDateString()) {
            Setting::put('license_last_seen', $today->toDateString());
        }

        return $today;
    }

    public function trialStart(): Carbon
    {
        $dates = array_filter([Setting::get('trial_started_at'), config('license.first_run')]);
        $valid = array_filter(array_map(fn ($d) => rescue(fn () => Carbon::parse($d)->startOfDay(), null, false), $dates));

        if (! $valid) {
            Setting::put('trial_started_at', now()->toDateString());

            return now()->startOfDay();
        }

        $start = collect($valid)->min();
        if (Setting::get('trial_started_at') !== $start->toDateString()) {
            Setting::put('trial_started_at', $start->toDateString());
        }

        return $start;
    }

    /**
     * @return array{state: string, days_left: ?int, expires: ?Carbon, plan: ?string, school: ?string, enforced: bool}
     *               state is licensed, trial or expired
     */
    public function status(): array
    {
        if (! $this->enabled()) {
            return ['state' => 'licensed', 'days_left' => null, 'expires' => null, 'plan' => 'hosted', 'school' => null, 'enforced' => false];
        }

        $today = $this->today();

        if ($key = Setting::get('license_key')) {
            $payload = $this->verify($key);
            if ($payload) {
                $exp = $payload['exp'] ? Carbon::parse($payload['exp'])->endOfDay() : null;
                if (! $exp || $exp->gte($today)) {
                    return [
                        'state' => 'licensed',
                        'days_left' => $exp ? (int) $today->diffInDays($exp->copy()->startOfDay()) : null,
                        'expires' => $exp,
                        'plan' => $payload['plan'] ?? null,
                        'school' => $payload['school'] ?? null,
                        'enforced' => true,
                    ];
                }

                return ['state' => 'expired', 'days_left' => 0, 'expires' => $exp, 'plan' => $payload['plan'] ?? null, 'school' => $payload['school'] ?? null, 'enforced' => true];
            }
        }

        $trialEnd = $this->trialStart()->addDays((int) config('license.trial_days'));
        $left = max(0, (int) $today->diffInDays($trialEnd, false));

        return [
            'state' => $left > 0 ? 'trial' : 'expired',
            'days_left' => $left,
            'expires' => $trialEnd,
            'plan' => 'trial',
            'school' => null,
            'enforced' => true,
        ];
    }

    public function canWrite(): bool
    {
        return $this->status()['state'] !== 'expired';
    }

    /** Returns the payload if the key is genuine and belongs to this installation. */
    public function verify(string $key): ?array
    {
        $key = preg_replace('/\s+/', '', $key);
        $parts = explode('.', $key);
        $publicKey = base64_decode((string) config('license.public_key'), true);

        if (count($parts) !== 3 || $parts[0] !== 'TDS1' || ! $publicKey || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return null;
        }

        $json = self::b64d($parts[1]);
        $sig = self::b64d($parts[2]);
        if ($json === null || $sig === null || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return null;
        }

        try {
            if (! sodium_crypto_sign_verify_detached($sig, $json, $publicKey)) {
                return null;
            }
        } catch (\SodiumException) {
            return null;
        }

        $payload = json_decode($json, true);
        if (! is_array($payload) || ($payload['id'] ?? null) !== $this->installationId()) {
            return null;
        }

        return $payload;
    }

    /** @return string|null error message, or null on success */
    public function activate(string $key): ?string
    {
        $payload = $this->verify($key);
        if (! $payload) {
            return 'This license key is not valid for this computer. Check that you sent the correct Installation ID and copied the full key.';
        }
        if ($payload['exp'] && Carbon::parse($payload['exp'])->endOfDay()->lt($this->today())) {
            return 'This license key has already expired ('.Carbon::parse($payload['exp'])->format('d M Y').'). Please contact us to renew.';
        }

        Setting::put('license_key', preg_replace('/\s+/', '', $key));

        return null;
    }

    // ---- helpers shared with the key generator ----

    public static function b64e(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function b64d(string $s): ?string
    {
        $bin = base64_decode(strtr($s, '-_', '+/'), true);

        return $bin === false ? null : $bin;
    }

    /** Build a signed key (used by tests; the website has its own copy of this logic). */
    public static function sign(array $payload, string $secretKeyB64): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return 'TDS1.'.self::b64e($json).'.'.self::b64e(sodium_crypto_sign_detached($json, base64_decode($secretKeyB64)));
    }
}
