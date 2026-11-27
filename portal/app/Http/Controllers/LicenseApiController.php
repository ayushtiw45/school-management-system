<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Services\LicenseIssuer;
use App\Services\SubscriptionManager;

/**
 * Called by the desktop software to fetch its newest license key automatically.
 * A key only works on the computer with that Installation ID, so returning it reveals nothing useful to others.
 */
class LicenseApiController extends Controller
{
    public function show(string $installationId, SubscriptionManager $manager)
    {
        $id = LicenseIssuer::normalizeId($installationId);
        abort_unless(preg_match(LicenseIssuer::ID_PATTERN, $id), 404);

        $licenses = License::where('installation_id', $id)->get();
        $best = $licenses->first(fn ($l) => $l->expires_on === null)
            ?? $licenses->sortByDesc(fn ($l) => $l->expires_on->timestamp)->first();
        $sub = $manager->liveSubscription($id);

        return response()->json([
            'installation_id' => $id,
            'key' => $best?->license_key,
            'expires' => $best?->expires_on?->toDateString(),
            'autopay' => $sub ? $sub->status : null,
            'activate_url' => route('activate', ['installation_id' => $id]),
        ])->header('Cache-Control', 'no-store');
    }
}
