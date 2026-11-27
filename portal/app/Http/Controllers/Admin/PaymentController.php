<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = Payment::with(['user', 'license'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest('id')->paginate(40)->withQueryString();

        return view('admin.payments', [
            'payments' => $payments,
            'total' => Payment::where('status', 'paid')->sum('amount') / 100,
        ]);
    }

    public function subscriptions(Request $request)
    {
        $subs = \App\Models\Subscription::with('user')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', '!=', 'created'))
            ->latest('id')->paginate(40)->withQueryString();

        return view('admin.subscriptions', [
            'subs' => $subs,
            'mrr' => \App\Models\Subscription::where('status', 'active')->sum('monthly_amount') / 100,
            'counts' => \App\Models\Subscription::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }
}
