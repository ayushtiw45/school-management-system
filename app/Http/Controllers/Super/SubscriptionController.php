<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        return view('super.subscriptions', [
            'subs' => Subscription::with('school')
                ->when($request->status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', '!=', 'created'))
                ->latest('id')->paginate(40)->withQueryString(),
            'mrr' => Subscription::where('status', 'active')->sum('monthly_amount') / 100,
            'counts' => Subscription::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }
}
