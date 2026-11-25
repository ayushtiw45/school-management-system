@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
@php($u = auth()->user())
<div class="d-flex flex-wrap align-items-center mb-3 gap-2">
    <div>
        <h2 class="h5 fw-bold mb-0">Welcome, {{ $u->name }}</h2>
        <div class="text-muted small">{{ now()->format('l, d M Y') }} @if($session)· Session {{ $session->name }}@endif</div>
    </div>
    <div class="ms-auto d-flex gap-2">
        @if($u->hasRole('admin', 'admission_staff'))<a href="{{ route('admissions.create') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-person-plus"></i> New Admission</a>@endif
        @if($u->hasRole('admin', 'accountant'))<a href="{{ route('payments.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-cash-coin"></i> Collect Fee</a>@endif
    </div>
</div>

@if(! $session && $u->isAdmin())
    <div class="alert alert-warning">Setup is not complete. Create an <a href="{{ route('sessions.index') }}">academic session</a>, add <a href="{{ route('classes.index') }}">classes & sections</a>, then set the <a href="{{ route('fee-structure.index') }}">fee structure</a>.</div>
@endif

<div class="row g-3 mb-3">
    @php($cards = [
        ['Total Students', $stats['students'], 'bi-people', 'primary', 'Active students'],
        ['Admissions', $stats['admissions'], 'bi-person-check', 'success', $stats['pending_admissions'].' pending approval'],
        ['Total Fees Collected', inr($stats['total_collection']), 'bi-wallet2', 'info', 'This session'],
        ["Today's Collection", inr($stats['today_collection']), 'bi-cash-stack', 'warning', $stats['today_receipts'].' receipt(s) today'],
        ['Pending Fees', inr($stats['pending_fees']), 'bi-hourglass-split', 'danger', 'Due till today'],
    ])
    @foreach($cards as [$label, $value, $icon, $color, $sub])
        <div class="col-6 col-md-4 col-xl">
            <div class="card stat h-100"><div class="card-body d-flex gap-3 align-items-center">
                <div class="icon bg-{{ $color }}-subtle text-{{ $color }}"><i class="bi {{ $icon }}"></i></div>
                <div class="min-w-0">
                    <div class="text-muted small">{{ $label }}</div>
                    <div class="value text-truncate">{{ $value }}</div>
                    <div class="text-muted" style="font-size:.72rem">{{ $sub }}</div>
                </div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Class-wise Students</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Class</th><th class="text-end">Boys</th><th class="text-end">Girls</th><th class="text-end">Total</th><th style="width:35%"></th></tr></thead>
                    <tbody>
                    @php($max = max(1, $classStats->max('active_count')))
                    @forelse($classStats as $c)
                        <tr>
                            <td><a href="{{ route('students.index', ['class_id' => $c->id]) }}" class="text-decoration-none">{{ $c->name }}</a></td>
                            <td class="text-end">{{ $c->boys }}</td>
                            <td class="text-end">{{ $c->girls }}</td>
                            <td class="text-end fw-semibold">{{ $c->active_count }}</td>
                            <td><div class="progress" style="height:6px"><div class="progress-bar" style="width:{{ round($c->active_count / $max * 100) }}%;background:#14213d"></div></div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted text-center py-3">No classes yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header d-flex">Recent Admissions <a href="{{ route('students.index', ['status' => 'all']) }}" class="ms-auto small">View all</a></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Adm. No</th><th>Student</th><th>Class</th><th>Date</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse($recentAdmissions as $s)
                        <tr>
                            <td>{{ $s->admission_no }}</td>
                            <td><a href="{{ route('students.show', $s) }}">{{ $s->full_name }}</a></td>
                            <td>{{ $s->class_label }}</td>
                            <td>{{ $s->admission_date->format('d M Y') }}</td>
                            <td>@include('students.status-badge', ['status' => $s->status])</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted text-center py-3">No admissions yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex">Recent Payments @if($u->hasRole('admin','accountant'))<a href="{{ route('payments.index') }}" class="ms-auto small">View all</a>@endif</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Receipt</th><th>Student</th><th>Mode</th><th>Date</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                    @forelse($recentPayments as $p)
                        <tr>
                            <td>@if($u->hasRole('admin','accountant'))<a href="{{ route('payments.show', $p) }}">{{ $p->receipt_no }}</a>@else{{ $p->receipt_no }}@endif</td>
                            <td>{{ $p->student->full_name }} <span class="text-muted small">({{ $p->student->schoolClass->name }})</span></td>
                            <td>{{ $p->methodLabel() }}</td>
                            <td>{{ $p->payment_date->format('d M Y') }}</td>
                            <td class="text-end fw-semibold">{{ inr($p->amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted text-center py-3">No payments yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
