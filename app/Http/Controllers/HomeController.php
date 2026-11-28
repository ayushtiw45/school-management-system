<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        return view('home', [
            'trialDays' => PlatformSetting::get('trial_days'),
            'trialPrice' => (float) PlatformSetting::get('trial_price') + 0,
            'monthlyPrice' => number_format((float) PlatformSetting::get('monthly_price')),
        ]);
    }

    public function locale(Request $request, string $locale)
    {
        abort_unless(isset(SetLocale::LOCALES[$locale]), 404);
        $request->session()->put('locale', $locale);

        return back()->withCookie(cookie()->forever('locale', $locale));
    }
}
