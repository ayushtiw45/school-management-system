@extends('layouts.admin')
@section('title', $school->school_name)
@section('content')
<div class="row g-3">
    <div class="col-lg-4"><div class="card p-3">
        <h1 class="h5 fw-bold">{{ $school->school_name }}</h1>
        <dl class="row small mb-0">
            <dt class="col-4 fw-normal text-muted">Contact</dt><dd class="col-8">{{ $school->name }}</dd>
            <dt class="col-4 fw-normal text-muted">Phone</dt><dd class="col-8">{{ $school->phone }}</dd>
            <dt class="col-4 fw-normal text-muted">Email</dt><dd class="col-8">{{ $school->email }}</dd>
            <dt class="col-4 fw-normal text-muted">City</dt><dd class="col-8">{{ $school->city }}{{ $school->state ? ', '.$school->state : '' }}</dd>
            <dt class="col-4 fw-normal text-muted">Joined</dt><dd class="col-8">{{ $school->created_at->format('d M Y') }}</dd>
            <dt class="col-4 fw-normal text-muted">Last login</dt><dd class="col-8">{{ $school->last_login_at?->format('d M Y H:i') ?? '—' }}</dd>
            <dt class="col-4 fw-normal text-muted">Downloads</dt><dd class="col-8">{{ $school->downloads->count() }} @if($school->downloads->first())(last {{ $school->downloads->first()->created_at->format('d M Y') }})@endif</dd>
        </dl>
        <a href="{{ route('admin.licenses', ['user_id' => $school->id]) }}#issue" class="btn btn-sm btn-navy mt-3">Issue license manually</a>
    </div></div>
    <div class="col-lg-8">
        <div class="card mb-3"><div class="card-header bg-white">Licenses</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>Installation ID</th><th>Plan</th><th>Valid till</th><th>Source</th><th>Issued</th></tr></thead>
                <tbody>@forelse($school->licenses as $l)<tr><td class="font-monospace">{{ $l->installation_id }}</td><td>{{ \App\Services\LicenseIssuer::PLANS[$l->plan]['label'] ?? 'Custom' }}</td><td>{{ $l->expires_on?->format('d M Y') ?? 'Lifetime' }}</td><td>{{ ucfirst($l->source) }}</td><td>{{ $l->created_at->format('d M Y') }}</td></tr>@empty<tr><td colspan="5" class="text-muted text-center py-3">No licenses.</td></tr>@endforelse</tbody>
            </table></div>
        </div>
        <div class="card"><div class="card-header bg-white">Payments</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>Date</th><th>Installation ID</th><th>Plan</th><th>Status</th><th class="text-end">Amount</th></tr></thead>
                <tbody>@forelse($school->payments as $p)<tr><td>{{ $p->created_at->format('d M Y') }}</td><td class="font-monospace">{{ $p->installation_id }}</td><td>{{ $p->plan }}</td><td>{{ $p->status }}</td><td class="text-end">₹{{ number_format($p->rupees(), 2) }}</td></tr>@empty<tr><td colspan="5" class="text-muted text-center py-3">No payments.</td></tr>@endforelse</tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection
