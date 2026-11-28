@extends('layouts.app')
@section('title', 'Attendance · '.$start->format('F Y'))
@section('content')
<form class="d-flex gap-2 mb-3 no-print"><input type="month" name="month" value="{{ $month }}" class="form-control form-control-sm" style="max-width:180px" onchange="this.form.submit()">
    <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Print</button></form>
<div class="card"><div class="table-responsive"><table class="table table-sm table-bordered mb-0 text-center" style="font-size:.75rem">
    <thead><tr><th class="text-start">Name</th>@foreach($days as $d)<th class="{{ $d->isSunday() ? 'table-light' : '' }}">{{ $d->format('j') }}</th>@endforeach<th>P</th><th>A</th><th>L</th></tr></thead>
    <tbody>
    @foreach($staff as $s)
        @php($row = $marks[$s->id] ?? collect())
        <tr><td class="text-start text-nowrap">{{ $s->name }}</td>
            @foreach($days as $d)
                @php($a = $row[$d->format('Y-m-d')] ?? null)
                <td class="{{ $d->isSunday() ? 'table-light' : '' }} {{ ['present' => 'text-success', 'absent' => 'text-danger fw-bold', 'half_day' => 'text-warning', 'leave' => 'text-info'][$a?->status] ?? '' }}">{{ ['present' => 'P', 'absent' => 'A', 'half_day' => 'H', 'leave' => 'L'][$a?->status] ?? '' }}</td>
            @endforeach
            <td>{{ $row->where('status', 'present')->count() + 0.5 * $row->where('status', 'half_day')->count() }}</td><td>{{ $row->where('status', 'absent')->count() }}</td><td>{{ $row->where('status', 'leave')->count() }}</td></tr>
    @endforeach
    </tbody>
</table></div></div>
<p class="small text-muted mt-2">P present · A absent · H half day · L leave</p>
@endsection
