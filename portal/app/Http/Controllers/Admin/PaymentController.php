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
}
