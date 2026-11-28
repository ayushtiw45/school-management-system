@extends('layouts.app')
@section('title', 'Vehicles')
@section('content')
<div class="d-flex flex-wrap gap-2 mb-3">
    <div class="btn-group btn-group-sm">
        @foreach(['' => 'Active', 'inactive' => 'Inactive', 'all' => 'All'] as $k => $l)
            <a href="{{ route('transport.vehicles.index', array_filter(['status' => $k])) }}" class="btn btn-outline-primary {{ request('status', '') === $k ? 'active' : '' }}">{{ $l }}</a>
        @endforeach
    </div>
    @if(auth()->user()->canManageTransport())<a href="{{ route('transport.vehicles.create') }}" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-plus-lg"></i> Add vehicle</a>@endif
</div>
<div class="card">
    <div class="table-responsive"><table class="table table-hover mb-0 align-middle">
        <thead><tr><th>Bus</th><th>Model</th><th>Seats</th><th>Fuel</th><th>Type</th><th>Insurance</th><th>PUC</th><th>Permit</th><th>Routes</th><th></th></tr></thead>
        <tbody>
        @forelse($vehicles as $v)
            <tr class="{{ $v->is_active ? '' : 'text-muted' }}">
                <td><a href="{{ route('transport.vehicles.show', $v) }}" class="fw-semibold">{{ $v->bus_no }}</a><div class="small text-muted">{{ $v->registration_no }}</div></td>
                <td>{{ trim($v->make.' '.$v->model) ?: '—' }}</td>
                <td>{{ $v->seats }}</td>
                <td>{{ \App\Models\Transport\Vehicle::FUELS[$v->fuel_type] ?? $v->fuel_type }}</td>
                <td>{{ \App\Models\Transport\Vehicle::OWNERSHIP[$v->ownership] }}</td>
                <td>@include('transport.partials.expiry', ['date' => $v->insurance_expiry])</td>
                <td>@include('transport.partials.expiry', ['date' => $v->puc_expiry])</td>
                <td>@include('transport.partials.expiry', ['date' => $v->permit_expiry])</td>
                <td>{{ $v->routes_count }}</td>
                <td class="text-end">@unless($v->is_active)<span class="badge text-bg-secondary">Inactive</span>@endunless <a href="{{ route('transport.vehicles.show', $v) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="10" class="text-center text-muted py-4">No vehicles yet. Add your first bus.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
