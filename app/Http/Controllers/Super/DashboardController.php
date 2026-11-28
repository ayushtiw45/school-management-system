<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Subscription;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $now = now();

        return view('super.dashboard', [
            'stats' => [
                'schools' => School::count(),
                'new_month' => School::where('created_at', '>=', $now->copy()->startOfMonth())->count(),
                'locked' => School::whereNull('paid_until')->count(),
                'active' => School::where('paid_until', '>', $now)->count(),
                'expired' => School::where('paid_until', '<=', $now)->count(),
                'on_trial' => Subscription::where('status', 'authenticated')->count(),
                'autopay' => Subscription::where('status', 'active')->count(),
                'mrr' => Subscription::where('status', 'active')->sum('monthly_amount') / 100,
            ],
            'recent' => School::withCount('users')->latest()->take(10)->get(),
        ]);
    }
}
