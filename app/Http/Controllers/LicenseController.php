<?php

namespace App\Http\Controllers;

use App\Services\LicenseService;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    public function show(LicenseService $license)
    {
        return view('license', [
            'status' => $license->status(),
            'installationId' => $license->installationId(),
            'vendor' => config('license.vendor'),
        ]);
    }

    public function activate(Request $request, LicenseService $license)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Only the admin can activate a license.');
        $data = $request->validate(['key' => ['required', 'string', 'max:2000']]);

        if ($error = $license->activate($data['key'])) {
            return back()->withErrors(['key' => $error])->withInput();
        }

        return redirect()->route('license')->with('success', 'License activated. Thank you!');
    }
}
