<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Support\Tenant;
use Illuminate\Support\Facades\Storage;

/** School logo (public: admission form, WhatsApp preview) and accountant signature (staff only). */
class SchoolAssetController extends Controller
{
    public function logo(School $school)
    {
        $path = $school->setting('logo_path');
        abort_unless($path && Storage::exists($path), 404);

        return Storage::response($path, null, ['Cache-Control' => 'public, max-age=86400']);
    }

    public function signature()
    {
        $path = Tenant::school()?->setting('signature_path');
        abort_unless($path && Storage::exists($path), 404);

        return Storage::response($path);
    }
}
