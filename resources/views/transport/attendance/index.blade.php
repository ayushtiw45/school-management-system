@extends('layouts.app')
@section('title', 'Driver & Conductor Attendance')
@section('content')
@php($A = \App\Models\Transport\StaffAttendance::class)
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <form><input type="date" name="date" value="{{ $date->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" class="form-control form-control-sm" onchange="this.form.submit()"></form>
    <a href="{{ route('transport.attendance.monthly') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-calendar3"></i> Month sheet</a>
    <a href="{{ route('transport.leaves.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-calendar-x"></i> Leave & substitutes</a>
</div>
<form method="POST" action="{{ route('transport.attendance.store') }}" class="card">
    @csrf<input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
    <div class="card-header d-flex align-items-center">{{ $date->format('l, d M Y') }}
        <button type="button" class="btn btn-sm btn-outline-success ms-auto" onclick="document.querySelectorAll('.att-present').forEach(r => r.checked = true)">All present</button></div>
    <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
        <thead><tr><th>Name</th><th>Role / vehicle</th><th>Status</th><th style="width:110px">In</th><th style="width:110px">Out</th><th>Note</th></tr></thead>
        <tbody>
        @forelse($staff as $s)
            @php($m = $marks[$s->id] ?? null)
            @php($status = $m?->status ?? (in_array($s->id, $onLeave) ? 'leave' : 'present'))
            <tr>
                <td><a href="{{ route('transport.staff.show', $s) }}">{{ $s->name }}</a>@if(! $m)<span class="badge text-bg-light border ms-1">not marked</span>@endif</td>
                <td class="small">{{ \App\Models\Transport\TransportStaff::TYPES[$s->type] }} · {{ $s->vehicle?->bus_no ?? '—' }}</td>
                <td class="text-nowrap">
                    @foreach($A::STATUSES as $k => $l)
                        <input type="radio" class="btn-check {{ $k === 'present' ? 'att-present' : '' }}" name="att[{{ $s->id }}][status]" value="{{ $k }}" id="a{{ $s->id }}{{ $k }}" @checked($status === $k)>
                        <label class="btn btn-sm btn-outline-{{ ['present' => 'success', 'absent' => 'danger', 'half_day' => 'warning', 'leave' => 'info'][$k] }}" for="a{{ $s->id }}{{ $k }}">{{ $l }}</label>
                    @endforeach
                </td>
                <td><input type="time" name="att[{{ $s->id }}][in_time]" value="{{ $m?->in_time ? substr($m->in_time, 0, 5) : ($s->duty_start ? substr($s->duty_start, 0, 5) : '') }}" class="form-control form-control-sm"></td>
                <td><input type="time" name="att[{{ $s->id }}][out_time]" value="{{ $m?->out_time ? substr($m->out_time, 0, 5) : '' }}" class="form-control form-control-sm"></td>
                <td><input name="att[{{ $s->id }}][note]" value="{{ $m?->note }}" class="form-control form-control-sm"></td>
            </tr>
        @empty<tr><td colspan="6" class="text-muted text-center py-3">Add drivers and conductors first.</td></tr>@endforelse
        </tbody>
    </table></div>
    @if($staff->isNotEmpty() && auth()->user()->canManageTransport())<div class="card-footer text-end"><button class="btn btn-primary">Save attendance</button></div>@endif
</form>
@endsection
