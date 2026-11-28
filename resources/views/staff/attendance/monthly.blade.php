@extends('layouts.app')
@section('title', 'Staff Attendance · '.$start->format('F Y'))
@section('content')
@php($S = \App\Models\Hr\EmployeeAttendance::SHORT)
<form class="d-flex gap-2 mb-3 no-print"><input type="month" name="month" value="{{ $month }}" class="form-control form-control-sm" style="max-width:180px" onchange="this.form.submit()">
    <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Print</button>
    <a href="{{ route('staff.reports.show', ['attendance', 'month' => $month]) }}" class="btn btn-sm btn-outline-secondary">Summary / Excel</a></form>
<div class="card"><div class="table-responsive"><table class="table table-sm table-bordered mb-0 text-center" style="font-size:.72rem">
    <thead><tr><th class="text-start">Name</th>@foreach($days as $d)<th class="{{ $d->isSunday() ? 'table-light' : '' }}">{{ $d->format('j') }}<div class="text-muted" style="font-size:.6rem">{{ substr($d->format('D'), 0, 2) }}</div></th>@endforeach<th>P</th><th>A</th><th>L</th></tr></thead>
    <tbody>
    @foreach($employees as $e)
        @php($row = $marks[$e->id] ?? collect())
        <tr><td class="text-start text-nowrap">{{ $e->name }}</td>
            @foreach($days as $d)
                @php($a = $row[$d->format('Y-m-d')] ?? null)
                <td class="{{ $d->isSunday() ? 'table-light' : '' }} {{ ['present' => 'text-success', 'late' => 'text-primary', 'absent' => 'text-danger fw-bold', 'half_day' => 'text-warning', 'leave' => 'text-info'][$a?->status] ?? '' }}">{{ $S[$a?->status] ?? '' }}</td>
            @endforeach
            <td>{{ $row->whereIn('status', ['present', 'late'])->count() + 0.5 * $row->where('status', 'half_day')->count() }}</td><td>{{ $row->where('status', 'absent')->count() }}</td><td>{{ $row->where('status', 'leave')->count() }}</td></tr>
    @endforeach
    </tbody>
</table></div></div>
<p class="small text-muted mt-2">P present · LT late · H half day · A absent · L leave</p>
@endsection
