@extends('layouts.app')
@section('title', 'Plan & Billing')
@section('content')
@php($isAdmin = auth()->user()->isAdmin())

@if($state === 'locked')
    <div class="card mb-3 border-2" style="border-color:#FF4B32">
        <div class="card-body p-4 p-md-5 text-center">
            <div class="display-6" style="color:#FF4B32"><i class="bi bi-lock-fill"></i></div>
            <h2 class="h3 fw-bold mt-2">{{ $trial ? "Start your {$trialDays}-day trial for ₹".($trialPrice + 0) : 'Activate your school for ₹'.number_format($monthlyPrice).' per month' }}</h2>
            <p class="text-muted mb-4" style="max-width:620px;margin:0 auto">
                @if($trial)
                    Pay ₹{{ $trialPrice + 0 }} now and every feature unlocks immediately for {{ $trialDays }} days. After that ₹{{ number_format($monthlyPrice) }} per month is paid automatically by UPI Autopay or card. Cancel any time — no charge if you cancel during the trial.
                @else
                    Pay monthly by Autopay. Cancel any time.
                @endif
            </p>
            @include('billing-button')
        </div>
    </div>
    <div class="card">
        <div class="card-header">What unlocks</div>
        <div class="card-body">
            <div class="row g-2">
                @foreach([
                    ['bi-person-plus', 'Enquiries, admissions & online admission form'], ['bi-people', 'Student records, ID cards, promotion, TC'],
                    ['bi-diagram-3', 'Classes, sections & sessions'], ['bi-grid-3x3', 'Fee structure & automatic dues'],
                    ['bi-cash-coin', 'Fee collection & printable receipts'], ['bi-hourglass-split', 'Pending fees & WhatsApp reminders'],
                    ['bi-bar-chart-line', 'Reports in Excel & PDF'], ['bi-wallet', 'Expenses & salary records'],
                    ['bi-bus-front', 'Transport: buses, routes, fuel, maintenance & profit'], ['bi-shield-lock', 'Staff logins (principal, reception, accounts, transport)'], ['bi-chat-dots', 'SMS / WhatsApp messages to parents'],
                ] as [$icon, $label])
                    <div class="col-md-6"><div class="d-flex align-items-center gap-2 p-2 border rounded-3 text-muted">
                        <i class="bi {{ $icon }}"></i><span>{{ $label }}</span><i class="bi bi-lock-fill ms-auto" style="color:#FF4B32"></i>
                    </div></div>
                @endforeach
            </div>
        </div>
    </div>
@else
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Your plan</div>
                <div class="card-body">
                    @if($state === 'active')
                        <span class="badge text-bg-success fs-6">Active</span>
                        <p class="mt-3 mb-1">Unlocked till <strong>{{ $school->paid_until->format('d M Y') }}</strong> ({{ $school->daysLeft() }} day(s) left).</p>
                        @if($live)
                            <p class="text-muted small">{{ $live->label() }} · ₹{{ number_format($live->monthly_amount / 100) }} per month is charged automatically, and access extends by itself.</p>
                        @else
                            <p class="text-muted small">Autopay is not active. Start it before the date above to keep using every feature.</p>
                        @endif
                    @else
                        <span class="badge text-bg-danger fs-6">Plan ended</span>
                        <p class="mt-3 mb-1">Ended on <strong>{{ $school->paid_until->format('d M Y') }}</strong>.</p>
                        <p class="text-muted small">Read-only mode: you can still view, print and download reports. Renew to add or change records.</p>
                    @endif
                    @if(! $live)
                        <div class="mt-3">@include('billing-button')</div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">Help</div>
                <div class="card-body small">
                    <p>Payments are processed securely by Razorpay. Your bank may ask once to approve Autopay, and informs you before each monthly debit.</p>
                    @if($whatsapp)<a href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode('Hello, I need help with my plan. School: '.$school->name) }}" target="_blank" rel="noopener" class="btn btn-success btn-sm"><i class="bi bi-whatsapp"></i> WhatsApp support</a>@endif
                    <a href="{{ route('refund') }}" target="_blank" class="ms-2">Refund & cancellation policy</a>
                </div>
            </div>
        </div>
    </div>
@endif

@if($subscriptions->count())
    <div class="card mt-3">
        <div class="card-header">Autopay history</div>
        <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Started</th><th>Status</th><th>Monthly</th><th>Trial ends</th><th>Paid till</th><th>Payments</th><th></th></tr></thead>
            <tbody>
            @foreach($subscriptions as $sub)
                <tr>
                    <td>{{ $sub->created_at->format('d M Y') }}</td><td>{{ $sub->label() }}</td><td>₹{{ number_format($sub->monthly_amount / 100) }}</td>
                    <td>{{ $sub->trial_ends_at?->format('d M Y') }}</td><td>{{ $sub->current_end?->format('d M Y') }}</td><td>{{ $sub->paid_count }}</td>
                    <td class="text-end">@if($sub->isLive() && $isAdmin)
                        <form method="POST" action="{{ route('billing.cancel', $sub) }}" data-confirm="Cancel Autopay? The software keeps working until the paid period ends.">@csrf<button class="btn btn-sm btn-outline-danger">Cancel Autopay</button></form>
                    @endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
@endif
@endsection
