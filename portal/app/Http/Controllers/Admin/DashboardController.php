<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Download;
use App\Models\License;
use App\Models\Payment;
use App\Models\User;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $schools = User::where('is_admin', false);
        $stats = [
            'schools' => (clone $schools)->count(),
            'schools_month' => (clone $schools)->where('created_at', '>=', now()->startOfMonth())->count(),
            'downloads' => Download::count(),
            'downloaded_schools' => Download::distinct('user_id')->count('user_id'),
            'licensed' => License::where(fn ($q) => $q->whereNull('expires_on')->orWhereDate('expires_on', '>=', now()))->distinct('installation_id')->count('installation_id'),
            'revenue' => Payment::where('status', 'paid')->sum('amount') / 100,
            'revenue_month' => Payment::where('status', 'paid')->where('paid_at', '>=', now()->startOfMonth())->sum('amount') / 100,
            'subs_active' => \App\Models\Subscription::where('status', 'active')->count(),
            'subs_trial' => \App\Models\Subscription::where('status', 'authenticated')->count(),
            'mrr' => \App\Models\Subscription::where('status', 'active')->sum('monthly_amount') / 100,
            'expiring' => License::whereNotNull('expires_on')->whereBetween('expires_on', [now()->toDateString(), now()->addDays(30)->toDateString()])->count(),
        ];

        return view('admin.dashboard', [
            'stats' => $stats,
            'recentSchools' => User::where('is_admin', false)->withCount('downloads')->latest()->take(8)->get(),
            'recentPayments' => Payment::with('user')->where('status', 'paid')->latest('paid_at')->take(8)->get(),
        ]);
    }
}
