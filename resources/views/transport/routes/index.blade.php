@extends('layouts.app')
@section('title', 'Routes & Students')
@section('content')
@php($R = \App\Models\Transport\TransportRoute::class)
<div class="d-flex mb-3 gap-2">
    <div class="small text-muted align-self-center">A bus can run more than one route (trip 1, trip 2…). Students on a route get the monthly transport fee automatically.</div>
    @if(auth()->user()->canManageTransport())<a href="{{ route('transport.routes.create') }}" class="btn btn-sm btn-primary ms-auto text-nowrap"><i class="bi bi-plus-lg"></i> New route</a>@endif
</div>
<div class="card">
    <div class="table-responsive"><table class="table table-hover mb-0 align-middle">
        <thead><tr><th>Route</th><th>Shift / trip</th><th>Timing</th><th>Km</th><th>Vehicle</th><th>Driver / conductor</th><th>Students / seats</th><th>Fee</th><th></th></tr></thead>
        <tbody>
        @forelse($routes as $r)
            @php($limit = $r->seatLimit())
            <tr class="{{ $r->is_active ? '' : 'text-muted' }}">
                <td><a href="{{ route('transport.routes.show', $r) }}" class="fw-semibold">{{ $r->label() }}</a><div class="small text-muted">{{ $r->stops_count }} stops</div></td>
                <td class="small">{{ $R::SHIFTS[$r->shift] }}<br>Trip {{ $r->trip_no }}@if($r->vehicle_id && ($trips[$r->vehicle_id] ?? collect())->count() > 1) <span class="badge text-bg-light border">{{ $trips[$r->vehicle_id]->count() }} trips on this bus</span>@endif</td>
                <td class="small">{{ $r->start_time ? substr($r->start_time, 0, 5) : '' }}{{ $r->end_time ? ' – '.substr($r->end_time, 0, 5) : '' }}</td>
                <td>{{ (float) $r->distance_km ?: '' }}</td>
                <td>{{ $r->vehicle?->bus_no ?? '—' }}</td>
                <td class="small">{{ $r->driver?->name ?? '—' }}<br>{{ $r->conductor?->name }}</td>
                <td><span class="{{ $limit && $r->students_count > $limit ? 'text-danger fw-bold' : '' }}">{{ $r->students_count }}</span> / {{ $limit ?: '—' }}
                    @if($limit)<div class="progress" style="height:4px;width:80px"><div class="progress-bar {{ $r->students_count > $limit ? 'bg-danger' : '' }}" style="width:{{ min(100, round($r->students_count / $limit * 100)) }}%;{{ $r->students_count <= $limit ? 'background:#FF4B32' : '' }}"></div></div>@endif</td>
                <td>{{ (float) $r->monthly_fee ? inr($r->monthly_fee) : 'by stop' }}</td>
                <td class="text-end"><a href="{{ route('transport.routes.show', $r) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="9" class="text-center text-muted py-4">No routes yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
