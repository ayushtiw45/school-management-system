@extends('layouts.app')
@section('title', 'Drivers & Conductors')
@section('content')
@php($S = \App\Models\Transport\TransportStaff::class)
<div class="d-flex flex-wrap gap-2 mb-3">
    <div class="btn-group btn-group-sm">
        @foreach(['' => 'All', 'driver' => 'Drivers', 'conductor' => 'Conductors'] as $k => $l)
            <a href="{{ route('transport.staff.index', array_filter(['type' => $k, 'status' => request('status')])) }}" class="btn btn-outline-primary {{ request('type', '') === $k ? 'active' : '' }}">{{ $l }}</a>
        @endforeach
    </div>
    <div class="btn-group btn-group-sm">
        @foreach(['' => 'Active', 'inactive' => 'Inactive'] as $k => $l)
            <a href="{{ route('transport.staff.index', array_filter(['type' => request('type'), 'status' => $k])) }}" class="btn btn-outline-secondary {{ request('status', '') === $k ? 'active' : '' }}">{{ $l }}</a>
        @endforeach
    </div>
    <a href="{{ route('transport.attendance.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-calendar-check"></i> Attendance</a>
    <a href="{{ route('transport.leaves.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-calendar-x"></i> Leave</a>
    @if(auth()->user()->canManageTransport())
        <a href="{{ route('transport.staff.create', ['type' => 'driver']) }}" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-plus-lg"></i> Add driver</a>
        <a href="{{ route('transport.staff.create', ['type' => 'conductor']) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg"></i> Add conductor</a>
    @endif
</div>
<div class="card">
    <div class="table-responsive"><table class="table table-hover mb-0 align-middle">
        <thead><tr><th>Name</th><th>Role</th><th>Mobile</th><th>Vehicle</th><th>Duty</th><th>Licence</th><th>Verification</th><th></th></tr></thead>
        <tbody>
        @forelse($staff as $s)
            <tr>
                <td><a href="{{ route('transport.staff.show', $s) }}" class="fw-semibold">{{ $s->name }}</a><div class="small text-muted">{{ $s->employee_id }}</div></td>
                <td>{{ $S::TYPES[$s->type] }}</td>
                <td>@if($s->phone)<a href="tel:{{ $s->phone }}">{{ $s->phone }}</a>@endif</td>
                <td>{{ $s->vehicle?->bus_no ?? '—' }}</td>
                <td class="small">{{ $s->duty_start ? substr($s->duty_start, 0, 5).' – '.substr((string) $s->duty_end, 0, 5) : '—' }}</td>
                <td>@if($s->type === 'driver')@include('transport.partials.expiry', ['date' => $s->licence_expiry])@else — @endif</td>
                <td><span class="badge {{ ['verified' => 'text-bg-success', 'pending' => 'text-bg-warning', 'failed' => 'text-bg-danger'][$s->verification_status] }}">{{ $S::VERIFICATION[$s->verification_status] }}</span></td>
                <td class="text-end"><a href="{{ route('transport.staff.show', $s) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-4">No drivers or conductors yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
