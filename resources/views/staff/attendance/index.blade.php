@extends('layouts.app')
@section('title', 'Staff Attendance')
@section('content')
@php($A = \App\Models\Hr\EmployeeAttendance::class)
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <form class="d-flex gap-2"><input type="date" name="date" value="{{ $date->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" class="form-control form-control-sm" onchange="this.form.submit()">
        <select name="type" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">All staff</option>@foreach(\App\Models\Hr\Employee::TYPES as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach</select></form>
    <a href="{{ route('staff.attendance.monthly') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-calendar3"></i> Month sheet</a>
    <a href="{{ route('staff.substitutions.index', ['date' => $date->format('Y-m-d')]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left-right"></i> Substitutes for absent teachers</a>
</div>
@if($date->isSunday())<div class="alert alert-light border">{{ $date->format('d M Y') }} is a Sunday.</div>@endif
<form method="POST" action="{{ route('staff.attendance.store') }}" class="card">
    @csrf<input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
    <div class="card-header d-flex align-items-center">{{ $date->format('l, d M Y') }} <span class="small text-muted ms-2">{{ $marks->count() }} of {{ $employees->count() }} marked</span>
        <button type="button" class="btn btn-sm btn-outline-success ms-auto" onclick="document.querySelectorAll('.att-present').forEach(r => { if (!r.closest('tr').dataset.leave) r.checked = true; })">All present</button></div>
    <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
        <thead><tr><th>Name</th><th>Status</th><th style="width:105px">In</th><th style="width:105px">Out</th><th>Note</th></tr></thead>
        <tbody>
        @forelse($employees as $e)
            @php($m = $marks[$e->id] ?? null)
            @php($lv = $onLeave[$e->id] ?? null)
            @php($status = $m?->status ?? ($lv ? ($lv->half_day ? 'half_day' : 'leave') : 'present'))
            <tr @if($lv) data-leave="1" @endif>
                <td><a href="{{ route('staff.employees.show', $e) }}">{{ $e->name }}</a> <span class="small text-muted">{{ $e->designation }}</span>
                    @if($lv)<span class="badge text-bg-info">{{ $lv->type?->code }} leave</span>@endif @if(! $m)<span class="badge text-bg-light border">not marked</span>@endif</td>
                <td class="text-nowrap">
                    @foreach($A::STATUSES as $k => $l)
                        <input type="radio" class="btn-check {{ $k === 'present' ? 'att-present' : '' }}" name="att[{{ $e->id }}][status]" value="{{ $k }}" id="a{{ $e->id }}{{ $k }}" @checked($status === $k)>
                        <label class="btn btn-sm btn-outline-{{ ['present' => 'success', 'late' => 'primary', 'half_day' => 'warning', 'absent' => 'danger', 'leave' => 'info'][$k] }}" for="a{{ $e->id }}{{ $k }}">{{ $l }}</label>
                    @endforeach
                </td>
                <td><input type="time" name="att[{{ $e->id }}][in_time]" value="{{ $m?->in_time ? substr($m->in_time, 0, 5) : '' }}" class="form-control form-control-sm"></td>
                <td><input type="time" name="att[{{ $e->id }}][out_time]" value="{{ $m?->out_time ? substr($m->out_time, 0, 5) : '' }}" class="form-control form-control-sm"></td>
                <td><input name="att[{{ $e->id }}][note]" value="{{ $m?->note }}" class="form-control form-control-sm"></td>
            </tr>
        @empty<tr><td colspan="5" class="text-muted text-center py-3">Add staff first.</td></tr>@endforelse
        </tbody>
    </table></div>
    @if($employees->isNotEmpty())<div class="card-footer text-end"><button class="btn btn-primary">Save attendance</button></div>@endif
</form>
@endsection
