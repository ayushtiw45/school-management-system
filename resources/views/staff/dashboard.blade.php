@extends('layouts.app')
@section('title', 'Staff Dashboard')
@section('content')
@php($u = auth()->user())
@if($counts['teachers'] + $counts['others'] === 0)
    <div class="alert alert-light border"><strong>Start here:</strong> 1) <a href="{{ route('staff.employees.create') }}">add teachers and staff</a> → 2) <a href="{{ route('staff.academics') }}">set class teachers and subjects</a> → 3) <a href="{{ route('staff.timetable.index') }}">make the timetable</a> → 4) mark attendance daily → 5) make salary at month end.</div>
@endif
<div class="row g-3 mb-3">
    @foreach([['Teachers', $counts['teachers'], 'bi-person-video3', route('staff.employees.index', ['type' => 'teaching'])], ['Other staff', $counts['others'], 'bi-people', route('staff.employees.index', ['type' => 'non_teaching'])],
              ['Present today', $counts['present'], 'bi-check2-circle', route('staff.attendance.index')], ['Absent / leave', $counts['away'], 'bi-person-x', route('staff.attendance.index')],
              ['Not marked', $counts['unmarked'], 'bi-question-circle', route('staff.attendance.index')], ['Leave requests', $counts['pending_leaves'], 'bi-inbox', route('staff.leaves.index')]] as [$l, $v, $i, $link])
        <div class="col-6 col-md-4 col-xl-2"><a href="{{ $link }}" class="card stat h-100 text-decoration-none"><div class="card-body"><div class="small text-muted"><i class="bi {{ $i }}"></i> {{ $l }}</div><div class="value text-body">{{ $v }}</div></div></a></div>
    @endforeach
</div>
@if($u->canSeeAccounts())
<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-4 py-2 align-items-center">
    <div class="fw-semibold">{{ now()->format('F Y') }}</div>
    <div><span class="small text-muted">Monthly salary bill</span> <strong>{{ inr($payroll['monthly']) }}</strong></div>
    <div><span class="small text-muted">Paid</span> <strong class="text-success">{{ inr($payroll['paid']) }}</strong></div>
    @if($payroll['drafts'])<div><span class="small text-muted">Drafts to pay</span> <strong>{{ $payroll['drafts'] }}</strong></div>@endif
    <a href="{{ route('staff.payroll.index') }}" class="ms-auto small">Payroll</a>
</div></div>
@endif
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card mb-3"><div class="card-header d-flex">Substitutes needed today <a href="{{ route('staff.substitutions.index') }}" class="ms-auto small">Arrange</a></div>
            <ul class="list-group list-group-flush small">
                @forelse($subNeeds as $n)<li class="list-group-item d-flex">{{ $n['entry']->period->name }} · {{ $n['entry']->section->schoolClass->name }} {{ $n['entry']->section->name }} · {{ $n['entry']->subject?->name }} <span class="text-muted ms-1">({{ $n['entry']->employee?->name }})</span>
                    <span class="ms-auto {{ $n['substitution']?->substitute ? 'text-success' : 'text-danger' }}">{{ $n['substitution']?->substitute?->name ?? 'not arranged' }}</span></li>
                @empty<li class="list-group-item text-muted">No periods need a substitute today.</li>@endforelse
            </ul>
        </div>
        <div class="card mb-3"><div class="card-header">Away today</div>
            <ul class="list-group list-group-flush small">
                @forelse($away as $a)<li class="list-group-item">{{ $a->employee?->name }} — {{ \App\Models\Hr\EmployeeAttendance::STATUSES[$a->status] }} {{ $a->note ? '('.$a->note.')' : '' }}</li>
                @empty<li class="list-group-item text-muted">Nobody marked absent.</li>@endforelse
            </ul>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card mb-3"><div class="card-header"><i class="bi bi-bell"></i> Coming up</div>
            <ul class="list-group list-group-flush small">
                @forelse($reminders as $r)
                    <a href="{{ route('staff.employees.show', $r['employee']) }}" class="list-group-item list-group-item-action d-flex gap-2">
                        <i class="bi {{ ['birthday' => 'bi-cake2 text-danger', 'anniversary' => 'bi-award text-primary', 'contract' => 'bi-file-earmark-text text-warning', 'document' => 'bi-file-earmark-x text-warning'][$r['kind']] }}"></i>
                        <span><strong>{{ $r['what'] }}</strong> · {{ $r['employee']->name }}</span>
                        <span class="ms-auto text-muted">{{ $r['days'] < 0 ? abs($r['days']).' d ago' : ($r['days'] === 0 ? 'today' : 'in '.$r['days'].' d') }} · {{ $r['date']->format('d M') }}</span></a>
                @empty<li class="list-group-item text-muted">No birthdays, anniversaries or contract endings soon.</li>@endforelse
            </ul>
        </div>
        <div class="card"><div class="card-header">Setup</div>
            <ul class="list-group list-group-flush small">
                <li class="list-group-item d-flex">Timetable made <span class="ms-auto">{{ $timetableDone }} of {{ $sections }} sections</span></li>
                <li class="list-group-item">Sections without a class teacher: @forelse($noClassTeacher->take(12) as $s){{ $s->schoolClass->name }} {{ $s->name }}@if(! $loop->last), @endif @empty <span class="text-success">none</span>@endforelse {{ $noClassTeacher->count() > 12 ? '…' : '' }}
                    @if($noClassTeacher->isNotEmpty())<a href="{{ route('staff.academics') }}" class="ms-1">Set</a>@endif</li>
            </ul>
        </div>
    </div>
</div>
@endsection
