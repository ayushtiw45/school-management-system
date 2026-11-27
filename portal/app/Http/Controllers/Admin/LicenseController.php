<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\User;
use App\Services\LicenseIssuer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LicenseController extends Controller
{
    public function index(Request $request)
    {
        $licenses = License::with('user')
            ->when($request->q, fn ($q, $t) => $q->where(fn ($q) => $q->where('school_name', 'like', "%$t%")->orWhere('installation_id', 'like', "%$t%")))
            ->when($request->filter === 'expiring', fn ($q) => $q->whereNotNull('expires_on')->whereBetween('expires_on', [now()->toDateString(), now()->addDays(30)->toDateString()]))
            ->when($request->filter === 'expired', fn ($q) => $q->whereNotNull('expires_on')->whereDate('expires_on', '<', now()))
            ->latest('id')->paginate(40)->withQueryString();

        return view('admin.licenses', [
            'licenses' => $licenses,
            'prefillUser' => $request->user_id ? User::find($request->user_id) : null,
        ]);
    }

    /** Manual key, e.g. after a UPI / cash payment. */
    public function store(Request $request, LicenseIssuer $issuer)
    {
        $request->merge(['installation_id' => LicenseIssuer::normalizeId((string) $request->installation_id)]);
        $data = $request->validate([
            'installation_id' => ['required', 'regex:'.LicenseIssuer::ID_PATTERN],
            'school_name' => ['required', 'string', 'max:150'],
            'user_id' => ['nullable', 'exists:users,id'],
            'plan' => ['required', Rule::in(['1y', '2y', 'life', 'custom'])],
            'custom_date' => ['required_if:plan,custom', 'nullable', 'date', 'after:today'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], ['installation_id.regex' => 'Installation ID looks wrong (example: TDS-7K2P-QX9M-4HDA).']);

        $license = $issuer->issue($data['installation_id'], $data['school_name'], $data['plan'], 'manual',
            $data['user_id'] ?? null, $request->user()->id, $data['notes'] ?? null, $data['custom_date'] ?? null);

        return redirect()->route('admin.licenses')->with('success', 'License created for '.$license->school_name.'.')->with('new_license_id', $license->id);
    }

    public function key(LicenseIssuer $issuer)
    {
        return view('admin.key', ['publicKey' => $issuer->publicKey()]);
    }

    public function backup(LicenseIssuer $issuer)
    {
        $body = json_encode(['signing_key' => $issuer->keys(), 'exported_at' => now()->toIso8601String()], JSON_PRETTY_PRINT);

        return response($body, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="tds-signing-key-backup-'.now()->format('Y-m-d').'.json"',
        ]);
    }
}
