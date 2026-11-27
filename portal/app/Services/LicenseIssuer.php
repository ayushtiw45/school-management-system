<?php

namespace App\Services;

use App\Models\License;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Creates license keys the desktop app accepts (Ed25519, checked offline by the app).
 * Key format: TDS1.<base64url(json)>.<base64url(signature)>
 * The secret signing key lives in storage/app/private/signing-key.json (outside public_html).
 */
class LicenseIssuer
{
    public const PLANS = [
        '1y' => ['months' => 12, 'label' => '1 Year'],
        '2y' => ['months' => 24, 'label' => '2 Years'],
        'life' => ['months' => null, 'label' => 'Lifetime'],
    ];

    /** Labels for every plan code stored on a license (incl. subscription ones). */
    public const LABELS = ['1y' => '1 Year', '2y' => '2 Years', 'life' => 'Lifetime', 'custom' => 'Custom', 'trial' => '7-day trial', 'monthly' => 'Monthly'];

    public const ID_PATTERN = '/^TDS(-[2-9A-HJ-NP-Z]{4}){3}$/';

    private const KEY_FILE = 'signing-key.json';

    /** Plans with a price set in admin settings: key => price in rupees */
    public static function pricedPlans(): array
    {
        $out = [];
        foreach (array_keys(self::PLANS) as $p) {
            $price = (float) Setting::get('price_'.$p);
            if ($price > 0) {
                $out[$p] = $price;
            }
        }

        return $out;
    }

    public static function normalizeId(string $id): string
    {
        return strtoupper(trim($id));
    }

    public function keys(): array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::KEY_FILE)) {
            $pair = sodium_crypto_sign_keypair();
            $disk->put(self::KEY_FILE, json_encode([
                'public' => base64_encode(sodium_crypto_sign_publickey($pair)),
                'secret' => base64_encode(sodium_crypto_sign_secretkey($pair)),
                'created' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT));
            @chmod($disk->path(self::KEY_FILE), 0600);
        }

        return json_decode($disk->get(self::KEY_FILE), true);
    }

    public function publicKey(): string
    {
        return $this->keys()['public'];
    }

    /** Latest expiry for an installation (null if none, 'life' if lifetime). */
    public function currentExpiry(string $installationId): Carbon|string|null
    {
        $licenses = License::where('installation_id', $installationId)->get();
        if ($licenses->contains(fn ($l) => $l->expires_on === null)) {
            return 'life';
        }

        return $licenses->max('expires_on');
    }

    /**
     * Issue a license. Renewals extend from the current expiry if it is still in the future.
     *
     * @param  string|null  $customExpiry  Y-m-d, only for plan "custom"
     */
    public function issue(string $installationId, string $school, string $plan, string $source, ?int $userId = null, ?int $issuedBy = null, ?string $notes = null, ?string $customExpiry = null): License
    {
        $installationId = self::normalizeId($installationId);
        $today = now()->startOfDay();
        $current = $this->currentExpiry($installationId);
        $start = ($current instanceof Carbon && $current->gte($today)) ? $current->copy()->addDay() : $today->copy();

        if ($customExpiry !== null || in_array($plan, ['custom', 'trial', 'monthly'], true)) {
            $expires = Carbon::parse($customExpiry)->startOfDay();
        } elseif (self::PLANS[$plan]['months'] === null) {
            $expires = null;
        } else {
            $expires = $start->copy()->addMonthsNoOverflow(self::PLANS[$plan]['months'])->subDay();
        }

        $payload = [
            'id' => $installationId,
            'school' => $school,
            'plan' => $plan === 'life' ? 'lifetime' : (self::LABELS[$plan] ?? 'custom'),
            'exp' => $expires?->toDateString(),
            'iat' => $today->toDateString(),
        ];
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $sig = sodium_crypto_sign_detached($json, base64_decode($this->keys()['secret']));

        return License::create([
            'user_id' => $userId,
            'installation_id' => $installationId,
            'school_name' => $school,
            'plan' => $plan,
            'starts_on' => $start,
            'expires_on' => $expires,
            'license_key' => 'TDS1.'.self::b64e($json).'.'.self::b64e($sig),
            'source' => $source,
            'issued_by' => $issuedBy,
            'notes' => $notes,
        ]);
    }

    public static function b64e(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }
}
