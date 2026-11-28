@extends('layouts.app')
@section('title', 'Teacher Timetable')
@section('content')
@php($days = \App\Models\Hr\TimetableEntry::DAYS)
<form class="d-flex gap-2 mb-3 no-print"><select name="employee_id" class="form-select form-select-sm" style="max-width:300px" onchange="this.form.submit()">
    @foreach($teachers as $t)<option value="{{ $t->id }}" @selected($teacher?->id === $t->id)>{{ $t->name }} ({{ $load[$t->id] ?? 0 }} periods)</option>@endforeach</select>
    <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Print</button>
    <a href="{{ route('staff.reports.show', 'workload') }}" class="btn btn-sm btn-outline-secondary">Workload of all teachers</a></form>
@if($teacher)
<h2 class="h5 fw-bold">{{ $teacher->name }} <span class="text-muted fs-6 fw-normal">{{ $grid->count() }} periods per week</span></h2>
<div class="card"><div class="table-responsive"><table class="table table-bordered mb-0 text-center align-middle small">
    <thead class="table-light"><tr><th style="width:90px">Day</th>@foreach($periods as $p)<th>{{ $p->name }}<div class="fw-normal text-muted" style="font-size:.7rem">{{ $p->timeLabel() }}</div></th>@endforeach<th>Total</th></tr></thead>
    <tbody>
    @foreach($days as $d => $dn)
        <tr><th>{{ $dn }}</th>
            @foreach($periods as $p)
                @php($e = $grid[$d.'-'.$p->id] ?? null)
                <td class="{{ $p->is_break ? 'table-light' : ($e ? '' : 'text-muted') }}">@if($p->is_break)—@elseif($e)<div class="fw-semibold">{{ $e->section->schoolClass->name }} {{ $e->section->name }}</div><div style="font-size:.72rem">{{ $e->subject?->name }}</div>@else free @endif</td>
            @endforeach
            <td class="fw-semibold">{{ $grid->filter(fn ($x, $k) => str_starts_with($k, $d.'-'))->count() }}</td></tr>
    @endforeach
    </tbody>
</table></div></div>
@else<div class="card card-body text-muted">Add teachers first.</div>@endif
@endsection
