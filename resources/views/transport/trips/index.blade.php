@extends('layouts.app')
@section('title', 'Daily Trips')
@section('content')
@php($can = auth()->user()->canManageTransport())
<form class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" max="{{ now()->format('Y-m-d') }}" class="form-control form-control-sm" style="max-width:170px" onchange="this.form.submit()">
    <div class="btn-group btn-group-sm">
        @foreach(['morning' => 'Morning', 'afternoon' => 'Afternoon'] as $k => $l)
            <a href="{{ route('transport.trips.index', ['date' => $date->format('Y-m-d'), 'shift' => $k]) }}" class="btn btn-outline-primary {{ $shift === $k ? 'active' : '' }}">{{ $l }}</a>
        @endforeach
    </div>
    <span class="small text-muted ms-2">Write the km reading at start and end of each run. This gives cost per km and each driver's trip history.</span>
</form>

@if($can && $pending->isNotEmpty())
<form method="POST" action="{{ route('transport.trips.store') }}" class="card mb-3">
    @csrf<input type="hidden" name="trip_date" value="{{ $date->format('Y-m-d') }}"><input type="hidden" name="shift" value="{{ $shift }}">
    <div class="card-header">{{ ucfirst($shift) }} trips to log — {{ $date->format('d M Y') }}</div>
    <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
        <thead><tr><th style="width:30px"></th><th>Route</th><th>Vehicle</th><th>Driver</th><th>Conductor</th><th style="width:110px">Start km</th><th style="width:110px">End km</th><th style="width:90px">Students</th><th>Note</th></tr></thead>
        <tbody>
        @foreach($pending as $i => $p)
            <tr>
                <td><input type="checkbox" class="form-check-input" name="trips[{{ $i }}][save]" value="1" checked></td>
                <td><input type="hidden" name="trips[{{ $i }}][transport_route_id]" value="{{ $p['route']->id }}">{{ $p['route']->label() }}<div class="small text-muted">trip {{ $p['route']->trip_no }}</div>@if($p['change'])<span class="badge text-bg-warning">changed today</span>@endif</td>
                <td><select name="trips[{{ $i }}][vehicle_id]" class="form-select form-select-sm"><option value="">—</option>@foreach($vehicles as $v)<option value="{{ $v->id }}" @selected($p['vehicle_id'] == $v->id)>{{ $v->bus_no }}</option>@endforeach</select></td>
                <td><select name="trips[{{ $i }}][driver_id]" class="form-select form-select-sm"><option value="">—</option>@foreach($drivers as $d)<option value="{{ $d->id }}" @selected($p['driver_id'] == $d->id)>{{ $d->name }}</option>@endforeach</select></td>
                <td><select name="trips[{{ $i }}][conductor_id]" class="form-select form-select-sm"><option value="">—</option>@foreach($conductors as $d)<option value="{{ $d->id }}" @selected($p['conductor_id'] == $d->id)>{{ $d->name }}</option>@endforeach</select></td>
                <td><input type="number" min="0" name="trips[{{ $i }}][start_km]" value="{{ old("trips.$i.start_km", $p['start_km']) }}" class="form-control form-control-sm"></td>
                <td><input type="number" min="0" name="trips[{{ $i }}][end_km]" value="{{ old("trips.$i.end_km") }}" class="form-control form-control-sm"></td>
                <td><input type="number" min="0" name="trips[{{ $i }}][students_count]" value="{{ $p['students'] }}" class="form-control form-control-sm"></td>
                <td><input name="trips[{{ $i }}][notes]" class="form-control form-control-sm"></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    <div class="card-footer text-end"><button class="btn btn-primary">Save ticked trips</button></div>
</form>
@endif

<div class="card">
    <div class="card-header">Logged on {{ $date->format('d M Y') }} <span class="badge text-bg-light border">{{ $trips->count() }}</span> <span class="small text-muted ms-2">{{ number_format($trips->sum(fn ($t) => $t->km())) }} km</span></div>
    <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
        <thead><tr><th>Shift</th><th>Route</th><th>Vehicle</th><th>Driver</th><th>Conductor</th><th>Start</th><th>End</th><th>Km</th><th>Students</th><th></th></tr></thead>
        <tbody>
        @forelse($trips as $t)
            <tr><td>{{ ucfirst($t->shift) }}</td><td>{{ $t->route?->label() ?? '—' }}</td><td>{{ $t->vehicle?->bus_no }}</td><td>{{ $t->driver?->name }}</td><td>{{ $t->conductor?->name }}</td>
                <td>{{ $t->start_km }}</td><td>{{ $t->end_km }}</td><td class="fw-semibold">{{ $t->km() ?: '' }}</td><td>{{ $t->students_count }}</td>
                <td class="text-end text-nowrap">@if($can)
                    <button class="btn btn-sm btn-link p-0" data-bs-toggle="collapse" data-bs-target="#tr{{ $t->id }}"><i class="bi bi-pencil"></i></button>
                    <form method="POST" action="{{ route('transport.trips.destroy', $t) }}" class="d-inline" data-confirm="Remove this trip?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger p-0 ms-1"><i class="bi bi-x-lg"></i></button></form>
                @endif</td></tr>
            @if($can)<tr class="collapse" id="tr{{ $t->id }}"><td colspan="10">
                <form method="POST" action="{{ route('transport.trips.update', $t) }}" class="d-flex gap-1">@csrf @method('PUT')
                    <input type="number" name="start_km" value="{{ $t->start_km }}" class="form-control form-control-sm" placeholder="Start km">
                    <input type="number" name="end_km" value="{{ $t->end_km }}" class="form-control form-control-sm" placeholder="End km">
                    <input type="number" name="students_count" value="{{ $t->students_count }}" class="form-control form-control-sm" placeholder="Students">
                    <input name="notes" value="{{ $t->notes }}" class="form-control form-control-sm" placeholder="Note"><button class="btn btn-sm btn-primary">Save</button>
                </form></td></tr>@endif
        @empty<tr><td colspan="10" class="text-muted text-center py-3">No trips logged for this day.</td></tr>@endforelse
        </tbody>
    </table></div>
</div>
@endsection
