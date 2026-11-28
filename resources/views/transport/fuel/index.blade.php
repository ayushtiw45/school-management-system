@extends('layouts.app')
@section('title', 'Fuel')
@section('content')
<div class="d-flex mb-2">@if(auth()->user()->canManageTransport())<a href="{{ route('transport.fuel.create', ['vehicle_id' => request('vehicle_id')]) }}" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-plus-lg"></i> Add fuel entry</a>@endif</div>
@include('transport.partials.range')
<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card p-3"><div class="small text-muted">Fuel cost</div><div class="fs-4 fw-bold">{{ inr($byVehicle->sum('amount')) }}</div></div></div>
    <div class="col-md-9"><div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Vehicle</th><th>Fills</th><th>Quantity</th><th class="text-end">Amount</th></tr></thead>
        <tbody>@forelse($byVehicle as $vid => $b)@php($veh = $vehicles->firstWhere('id', $vid))<tr><td>{{ $veh?->label() }}</td><td>{{ $b->fills }}</td><td>{{ (float) $b->qty }} {{ \App\Models\Transport\FuelEntry::UNITS[$veh?->fuel_type] ?? 'L' }}</td><td class="text-end">{{ inr($b->amount) }}</td></tr>
        @empty<tr><td colspan="4" class="text-muted text-center">No fuel in this period.</td></tr>@endforelse</tbody>
    </table></div></div></div>
</div>
<div class="card">
    <div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
        <thead><tr><th>Date</th><th>Vehicle</th><th>Fuel</th><th>Qty</th><th>Rate</th><th>Odometer</th><th>Station</th><th>Driver</th><th class="text-end">Amount</th><th></th></tr></thead>
        <tbody>
        @forelse($entries as $e)
            <tr><td>{{ $e->entry_date->format('d M Y') }}</td><td>{{ $e->vehicle?->bus_no }}</td><td>{{ \App\Models\Transport\Vehicle::FUELS[$e->fuel_type] ?? $e->fuel_type }}</td>
                <td>{{ (float) $e->quantity }} {{ $e->unit() }}@unless($e->full_tank) <span class="badge text-bg-light border">part</span>@endunless</td><td>{{ inr($e->rate) }}</td><td>{{ $e->odometer ? number_format($e->odometer) : '' }}</td>
                <td class="small">{{ $e->station }}</td><td class="small">{{ $e->driver?->name }}</td><td class="text-end">{{ inr($e->amount) }}</td>
                <td class="text-end text-nowrap">@if($e->bill_path)<a href="{{ route('transport.fuel.bill', $e) }}" target="_blank" title="Bill"><i class="bi bi-paperclip"></i></a>@endif
                    @if(auth()->user()->canManageTransport())<a href="{{ route('transport.fuel.edit', $e) }}" class="ms-1"><i class="bi bi-pencil"></i></a>
                    <form method="POST" action="{{ route('transport.fuel.destroy', $e) }}" class="d-inline" data-confirm="Delete this entry?">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger p-0 ms-1"><i class="bi bi-x-lg"></i></button></form>@endif</td></tr>
        @empty<tr><td colspan="10" class="text-muted text-center py-3">No fuel entries.</td></tr>@endforelse
        </tbody>
    </table></div>
    @if($entries->hasPages())<div class="card-footer">{{ $entries->links('pagination::bootstrap-5') }}</div>@endif
</div>
@endsection
