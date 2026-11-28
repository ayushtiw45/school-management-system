@extends('layouts.app')
@section('title', $e->name)
@section('content')
@php($E = \App\Models\Hr\Employee::class)
@php($u = auth()->user())
@php($days = \App\Models\Hr\TimetableEntry::DAYS)
<div class="d-flex flex-wrap gap-3 align-items-start mb-3">
    @if($e->photo)<img src="{{ route('staff.employees.photo', $e) }}" class="rounded-3" style="width:84px;height:84px;object-fit:cover" alt="">@else<div class="rounded-3 bg-light d-flex align-items-center justify-content-center fw-bold fs-2" style="width:84px;height:84px">{{ strtoupper(substr($e->name, 0, 1)) }}</div>@endif
    <div>
        <h2 class="h4 fw-bold mb-0">{{ $e->name }} @unless($e->isActive())<span class="badge text-bg-secondary fs-6">{{ $E::STATUSES[$e->status] }} {{ $e->leaving_date?->format('d M Y') }}</span>@endunless</h2>
        <div class="text-muted small">{{ $e->employee_id }} · {{ $e->designation ?: $E::TYPES[$e->type] }}@if($e->department) · {{ $e->department }}@endif · {{ $E::EMPLOYMENT[$e->employment_type] }}</div>
        <div class="small">@if($e->phone)<a href="tel:{{ $e->phone }}"><i class="bi bi-telephone"></i> {{ $e->phone }}</a>@endif @if($e->email) · {{ $e->email }}@endif</div>
        @if($e->classTeacherOf->isNotEmpty())<div class="small mt-1"><span class="badge text-bg-light border">Class teacher: {{ $e->classTeacherOf->map(fn ($s) => $s->schoolClass->name.' '.$s->name)->implode(', ') }}</span></div>@endif
    </div>
    <div class="ms-auto d-flex flex-wrap gap-2">
        @if($u->canManageStaff())<a href="{{ route('staff.employees.edit', $e) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Edit</a>@endif
        <a href="{{ route('staff.idcards', ['employee' => $e->id]) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-person-badge"></i> ID card</a>
        <div class="dropdown"><button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Letters</button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" target="_blank" href="{{ route('staff.letter', [$e, 'joining']) }}">Appointment / joining letter</a></li>
                <li><a class="dropdown-item" target="_blank" href="{{ route('staff.letter', [$e, 'experience']) }}">Experience certificate</a></li>
                <li><a class="dropdown-item" target="_blank" href="{{ route('staff.letter', [$e, 'relieving']) }}">Relieving letter</a></li>
            </ul></div>
        @if($u->canManageStaff())<button class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#exitBox">{{ $e->isActive() ? 'Mark as left' : 'Change status' }}</button>@endif
    </div>
</div>
@if($u->canManageStaff())
<form method="POST" action="{{ route('staff.employees.exit', $e) }}" class="card card-body mb-3 collapse" id="exitBox">@csrf
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small">Status</label><select name="status" class="form-select form-select-sm">@foreach($E::STATUSES as $k => $l)<option value="{{ $k }}" @selected($e->status === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label small">Last working day</label><input type="date" name="leaving_date" value="{{ $e->leaving_date?->format('Y-m-d') ?? now()->format('Y-m-d') }}" class="form-control form-control-sm"></div>
        <div class="col-md-4"><label class="form-label small">Reason</label><input name="leaving_reason" value="{{ $e->leaving_reason }}" class="form-control form-control-sm"></div>
        <div class="col-md-2"><button class="btn btn-sm btn-danger w-100">Save</button></div>
    </div>
</form>
@endif
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card mb-3"><div class="card-header">Details</div>
            <table class="table table-sm mb-0 small">
                <tr><th style="width:40%">Joined</th><td>{{ $e->joining_date?->format('d M Y') ?? '—' }} · total experience {{ $e->totalExperience() }} yrs</td></tr>
                @if($e->contract_end)<tr><th>Contract / probation ends</th><td>{{ $e->contract_end->format('d M Y') }}</td></tr>@endif
                <tr><th>Qualification</th><td>{{ $e->qualification ?: '—' }}</td></tr>
                <tr><th>Specialization</th><td>{{ $e->specialization ?: '—' }}</td></tr>
                <tr><th>Date of birth</th><td>{{ $e->dob?->format('d M Y') }} {{ $e->blood_group ? '· '.$e->blood_group : '' }}</td></tr>
                <tr><th>Address</th><td>{{ $e->address }}</td></tr>
                <tr><th>Emergency</th><td>{{ $e->emergency_name }} {{ $e->emergency_phone }}</td></tr>
                <tr><th>Aadhaar / PAN</th><td>{{ $e->aadhaar_no ?: '—' }} / {{ $e->pan_no ?: '—' }}</td></tr>
                @if($u->canSeeAccounts())<tr><th>Bank</th><td>{{ $e->bank_name }} {{ $e->bank_account }} {{ $e->bank_ifsc }}</td></tr><tr><th>PF / ESI</th><td>{{ $e->uan_no ?: '—' }} / {{ $e->esi_no ?: '—' }}</td></tr>@endif
                @if(! $e->isActive())<tr><th>Left</th><td>{{ $e->leaving_date?->format('d M Y') }} — {{ $e->leaving_reason }}</td></tr>@endif
            </table>
        </div>

        @if($u->canSeeAccounts())
        <form method="POST" action="{{ route('staff.employees.salary', $e) }}" class="card mb-3">@csrf @method('PUT')
            <div class="card-header d-flex">Salary structure (monthly) <span class="ms-auto small">Net {{ inr($e->gross() - $e->deductions()) }}</span></div>
            <div class="card-body row g-2">
                @foreach($E::EARNINGS as $k => $l)<div class="col-6 col-md-4"><label class="form-label small mb-0">{{ $l }}</label><input type="number" step="0.01" min="0" name="{{ $k }}" value="{{ (float) $e->$k ?: '' }}" class="form-control form-control-sm"></div>@endforeach
                @foreach($E::DEDUCTIONS as $k => $l)<div class="col-6 col-md-4"><label class="form-label small mb-0 text-danger">− {{ $l }}</label><input type="number" step="0.01" min="0" name="{{ $k }}" value="{{ (float) $e->$k ?: '' }}" class="form-control form-control-sm"></div>@endforeach
            </div>
            @if($u->canManagePayroll())<div class="card-footer text-end"><button class="btn btn-sm btn-primary">Save salary</button></div>@endif
        </form>
        @endif

        <div class="card mb-3"><div class="card-header">Documents <span class="small text-muted">(degree, B.Ed., ID proof, police verification…)</span></div>
            <ul class="list-group list-group-flush small">
                @forelse($e->documents as $d)
                    <li class="list-group-item d-flex align-items-center gap-2"><i class="bi {{ $d->isImage() ? 'bi-image' : 'bi-file-earmark-pdf' }}"></i>
                        <a href="{{ route('staff.documents.show', $d) }}" target="_blank">{{ $d->title }}</a>
                        @if($d->valid_till)<span class="{{ $d->valid_till->isPast() ? 'text-danger' : 'text-muted' }}">valid till {{ $d->valid_till->format('d M Y') }}</span>@endif
                        @if($u->canManageStaff())<form method="POST" action="{{ route('staff.documents.destroy', $d) }}" class="ms-auto" data-confirm="Delete this document?">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-x-lg"></i></button></form>@endif</li>
                @empty<li class="list-group-item text-muted">No documents yet.</li>@endforelse
            </ul>
            @if($u->canManageStaff())
            <form method="POST" action="{{ route('staff.documents.store', $e) }}" enctype="multipart/form-data" class="card-footer row g-1">@csrf
                <div class="col-md-4"><input name="title" class="form-control form-control-sm" placeholder="Name" required></div>
                <div class="col-md-3"><input type="date" name="valid_till" class="form-control form-control-sm" title="Valid till (optional)"></div>
                <div class="col-md-5 d-flex gap-1"><input type="file" name="file" class="form-control form-control-sm" accept=".pdf,image/*" required><button class="btn btn-sm btn-primary">Upload</button></div>
            </form>
            @endif
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card mb-3"><div class="card-header d-flex">Leave balance <a href="{{ route('staff.leaves.index', ['status' => 'all']) }}" class="ms-auto small">Leave</a></div>
            <div class="card-body d-flex flex-wrap gap-2">
                @foreach($balances as $b)
                    <div class="border rounded-3 px-3 py-2"><div class="small text-muted">{{ $b['type']->name }}</div>
                        <div class="fw-bold">{{ $b['balance'] !== null ? ($b['balance'] + 0).' left' : ($b['used'] + 0).' taken' }}</div>
                        <div class="small text-muted">{{ $b['used'] + 0 }} used{{ $b['pending'] ? ' · '.($b['pending'] + 0).' pending' : '' }}{{ $b['balance'] !== null ? ' of '.($b['allowed'] + 0) : '' }}</div></div>
                @endforeach
            </div>
            <ul class="list-group list-group-flush small">
                @foreach($leaves as $l)<li class="list-group-item">{{ $l->type?->code }} · {{ $l->from_date->format('d M') }}{{ $l->to_date->ne($l->from_date) ? ' – '.$l->to_date->format('d M') : '' }} ({{ $l->days + 0 }} d) · <span class="badge {{ ['approved' => 'text-bg-success', 'pending' => 'text-bg-warning'][$l->status] ?? 'text-bg-secondary' }}">{{ \App\Models\Hr\EmployeeLeave::STATUSES[$l->status] }}</span> <span class="text-muted">{{ $l->reason }}</span></li>@endforeach
            </ul>
        </div>
        <div class="card mb-3"><div class="card-header">Attendance this month</div>
            <div class="card-body d-flex flex-wrap gap-1">
                @forelse($attendance as $a)<span class="badge {{ ['present' => 'text-bg-success', 'late' => 'text-bg-primary', 'half_day' => 'text-bg-warning', 'absent' => 'text-bg-danger', 'leave' => 'text-bg-info'][$a->status] }}" title="{{ \App\Models\Hr\EmployeeAttendance::STATUSES[$a->status] }}">{{ $a->date->format('d') }} {{ \App\Models\Hr\EmployeeAttendance::SHORT[$a->status] }}</span>
                @empty<span class="small text-muted">Not marked yet.</span>@endforelse
            </div>
        </div>
        @if($e->isTeacher())
        <div class="card mb-3"><div class="card-header d-flex">Timetable <span class="small text-muted ms-2">{{ $timetable->flatten()->count() }} periods/week</span><a href="{{ route('staff.timetable.teacher', ['employee_id' => $e->id]) }}" class="ms-auto small">Full view</a></div>
            <div class="table-responsive"><table class="table table-sm table-bordered mb-0 text-center small">
                <thead><tr><th></th>@foreach($periods->where('is_break', false) as $p)<th>{{ $p->name }}</th>@endforeach</tr></thead>
                <tbody>@foreach($days as $d => $dn)<tr><th class="text-start">{{ substr($dn, 0, 3) }}</th>
                    @foreach($periods->where('is_break', false) as $p)@php($t = $timetable[$d.'-'.$p->id][0] ?? null)<td class="{{ $t ? 'table-light' : '' }}">@if($t)<div class="fw-semibold">{{ $t->section->schoolClass->name }} {{ $t->section->name }}</div><div class="text-muted" style="font-size:.7rem">{{ $t->subject?->name }}</div>@endif</td>@endforeach</tr>@endforeach</tbody>
            </table></div>
            @if($e->subjects->isNotEmpty())<div class="card-footer small">Teaches: {{ $e->subjects->map(fn ($s) => $s->subject->name.' ('.$s->schoolClass->name.($s->section ? ' '.$s->section->name : '').')')->implode(', ') }}</div>@endif
        </div>
        @endif
        @if($payrolls->isNotEmpty() || $advances->isNotEmpty())
        <div class="card"><div class="card-header">Salary history</div>
            <ul class="list-group list-group-flush small">
                @foreach($payrolls as $p)<li class="list-group-item d-flex gap-2">{{ $p->monthLabel() }} <span class="text-muted">{{ $p->paid_days + 0 }}/{{ $p->working_days + 0 }} days</span><span class="ms-auto">{{ inr($p->net) }}</span>
                    <span class="badge {{ $p->status === 'paid' ? 'text-bg-success' : 'text-bg-warning' }}">{{ $p->status === 'paid' ? 'Paid' : 'Draft' }}</span><a href="{{ route('staff.payroll.slip', $p) }}" target="_blank">Slip</a></li>@endforeach
                @foreach($advances as $a)<li class="list-group-item">Advance {{ inr($a->amount) }} on {{ $a->given_on->format('d M Y') }} · balance {{ inr($a->balance()) }}</li>@endforeach
            </ul>
        </div>
        @endif
    </div>
</div>
@endsection
