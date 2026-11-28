@extends('layouts.app')
@section('title', $member->name)
@section('content')
@php($S = \App\Models\Transport\TransportStaff::class)
@php($can = auth()->user()->canManageTransport())
<div class="d-flex flex-wrap gap-2 align-items-start mb-3">
    <div>
        <h2 class="h4 fw-bold mb-0">{{ $member->name }} <span class="badge text-bg-light border fs-6 fw-normal">{{ $S::TYPES[$member->type] }}</span> @unless($member->is_active)<span class="badge text-bg-secondary">Inactive</span>@endunless</h2>
        <div class="text-muted small">{{ $member->employee_id }} @if($member->phone)· <a href="tel:{{ $member->phone }}">{{ $member->phone }}</a>@endif · Vehicle: {{ $member->vehicle?->label() ?? '—' }}</div>
    </div>
    @if($can)
    <div class="ms-auto d-flex gap-2">
        <a href="{{ route('transport.staff.edit', $member) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Edit</a>
        <form method="POST" action="{{ route('transport.staff.toggle', $member) }}" data-confirm="Change status?">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $member->is_active ? 'Mark inactive' : 'Mark active' }}</button></form>
    </div>
    @endif
</div>
<div class="row g-3 mb-3">
    @php($present = $attendance->where('status', 'present')->count() + 0.5 * $attendance->where('status', 'half_day')->count())
    @foreach([['Trips (all time)', number_format($stats['trips'])], ['Km driven', number_format($stats['km'])], ['Present this month', $present.' / '.$attendance->count()],
              ['Breakdowns', $stats['breakdowns']], ['Salary', inr($member->monthly_salary).'/month']] as [$l, $val])
        <div class="col-6 col-md"><div class="card p-3 h-100"><div class="small text-muted">{{ $l }}</div><div class="fw-bold">{{ $val }}</div></div></div>
    @endforeach
</div>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">Details</div>
            <table class="table table-sm mb-0">
                @if($member->type === 'driver')<tr><th>Licence</th><td>{{ $member->licence_no }} {{ $member->licence_type ? '('.$member->licence_type.')' : '' }}<br>@include('transport.partials.expiry', ['date' => $member->licence_expiry])</td></tr>@endif
                <tr><th>Verification</th><td><span class="badge {{ ['verified' => 'text-bg-success', 'pending' => 'text-bg-warning', 'failed' => 'text-bg-danger'][$member->verification_status] }}">{{ $S::VERIFICATION[$member->verification_status] }}</span> {{ $member->verified_on?->format('d M Y') }}<div class="small">{{ $member->verification_note }}</div></td></tr>
                <tr><th>Duty</th><td>{{ $member->duty_start ? substr($member->duty_start, 0, 5).' – '.substr((string) $member->duty_end, 0, 5) : '—' }}</td></tr>
                <tr><th>Routes</th><td>@forelse($routes as $r)<a href="{{ route('transport.routes.show', $r) }}">{{ $r->label() }}</a>@if(! $loop->last), @endif @empty — @endforelse</td></tr>
                <tr><th>Joined</th><td>{{ $member->joining_date?->format('d M Y') ?? '—' }}</td></tr>
                <tr><th>Aadhaar</th><td>{{ $member->aadhaar_no ?: '—' }}</td></tr>
                <tr><th>Address</th><td>{{ $member->address ?: '—' }}</td></tr>
            </table>
        </div>
        @include('transport.partials.files', ['files' => $member->files, 'ownerType' => 'staff', 'ownerId' => $member->id])
        <div class="card mb-3">
            <div class="card-header d-flex">Leave <a href="{{ route('transport.leaves.index') }}" class="ms-auto small">Manage</a></div>
            <ul class="list-group list-group-flush small">
                @forelse($leaves as $l)<li class="list-group-item">{{ $l->from_date->format('d M') }} – {{ $l->to_date->format('d M Y') }} ({{ $l->days() }} d) · {{ \App\Models\Transport\StaffLeave::STATUSES[$l->status] }}@if($l->substitute) · substitute {{ $l->substitute->name }}@endif<div class="text-muted">{{ $l->reason }}</div></li>
                @empty<li class="list-group-item text-muted">No leave taken.</li>@endforelse
            </ul>
        </div>
        @if($salaries->isNotEmpty())
        <div class="card">
            <div class="card-header">Salary paid</div>
            <ul class="list-group list-group-flush small">
                @foreach($salaries as $sal)<li class="list-group-item d-flex">{{ \Carbon\Carbon::createFromFormat('Y-m', $sal->month)->format('M Y') }} <span class="text-muted ms-2">paid {{ $sal->paid_on->format('d M') }}</span><span class="ms-auto">{{ inr($sal->net()) }}</span> <a href="{{ route('transport.salaries.slip', $sal) }}" class="ms-2" target="_blank">Slip</a></li>@endforeach
            </ul>
        </div>
        @endif
    </div>
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header">Attendance this month</div>
            <div class="card-body d-flex flex-wrap gap-1">
                @forelse($attendance->sortBy('date') as $a)
                    <span class="badge {{ ['present' => 'text-bg-success', 'absent' => 'text-bg-danger', 'half_day' => 'text-bg-warning', 'leave' => 'text-bg-info'][$a->status] }}" title="{{ \App\Models\Transport\StaffAttendance::STATUSES[$a->status] }}">{{ $a->date->format('d') }}</span>
                @empty<span class="text-muted small">Not marked yet.</span>@endforelse
            </div>
        </div>
        <div class="card">
            <div class="card-header">Trip history</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>Date</th><th>Shift</th><th>Vehicle</th><th>Route</th><th class="text-end">Km</th></tr></thead>
                <tbody>
                @forelse($trips as $t)
                    <tr><td>{{ $t->trip_date->format('d M Y') }}</td><td>{{ ucfirst($t->shift) }}</td><td>{{ $t->vehicle?->bus_no }}</td><td>{{ $t->route?->label() }}</td><td class="text-end">{{ $t->km() ?: '' }}</td></tr>
                @empty<tr><td colspan="5" class="text-muted text-center py-3">No trips yet.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection
