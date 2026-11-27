<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

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

        if ((int) config('license.trial_days') <= 0) {
            // Paid activation required (₹1 trial on the website)
            return ['state' => 'expired', 'days_left' => 0, 'expires' => null, 'plan' => 'none', 'school' => null, 'enforced' => true];
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

    /** Website page where this computer is activated / paid for. */
    public function activateUrl(): ?string
    {
        $server = rtrim((string) config('license.server'), '/');

        return $server ? $server.'/activate?installation_id='.urlencode($this->installationId()) : null;
    }

    /**
     * Ask the website for this computer's newest key and activate it if it lasts longer than the current one.
     * Throttled (every $minutes unless forced); silent when offline.
     *
     * @return bool true if a newer license was activated
     */
    public function syncOnline(bool $force = false, int $minutes = 60): bool
    {
        $server = rtrim((string) config('license.server'), '/');
        if (! $this->enabled() || ! $server) {
            return false;
        }
        $last = (int) Setting::get('license_last_sync');
        if (! $force && $last && now()->timestamp - $last < $minutes * 60) {
            return false;
        }
        Setting::put('license_last_sync', (string) now()->timestamp);

        try {
            $res = Http::timeout(4)->acceptJson()->get($server.'/api/license/'.urlencode($this->installationId()));
        } catch (\Throwable) {
            return false;
        }
        $key = $res->successful() ? $res->json('key') : null;
        if (! $key || $key === Setting::get('license_key')) {
            return false;
        }

        $new = $this->verify($key);
        if (! $new) {
            return false;
        }
        $current = Setting::get('license_key') ? $this->verify(Setting::get('license_key')) : null;
        $newer = ! $current
            || ($new['exp'] === null && $current['exp'] !== null)
            || ($new['exp'] !== null && $current['exp'] !== null && $new['exp'] > $current['exp']);

        return $newer && $this->activate($key) === null;
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
