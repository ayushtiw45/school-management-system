<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Models\Setting;
use App\Services\LicenseIssuer;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        return view('home', ['plans' => LicenseIssuer::pricedPlans(), 'trialDays' => Setting::get('trial_days')]);
    }

    public function locale(Request $request, string $locale)
    {
        abort_unless(isset(SetLocale::LOCALES[$locale]), 404);
        $request->session()->put('locale', $locale);
        $request->user()?->update(['locale' => $locale]);

        return back()->withCookie(cookie()->forever('locale', $locale));
    }
}
