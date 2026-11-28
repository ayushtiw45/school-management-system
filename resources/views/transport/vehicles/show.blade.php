@extends('layouts.app')
@section('title', $vehicle->bus_no.' · '.$vehicle->registration_no)
@section('content')
@php($V = \App\Models\Transport\Vehicle::class)
@php($can = auth()->user()->canManageTransport())
<div class="d-flex flex-wrap gap-2 align-items-start mb-3">
    <div>
        <h2 class="h4 fw-bold mb-0">{{ $vehicle->bus_no }} <span class="text-muted fw-normal fs-6">{{ $vehicle->registration_no }}</span> @unless($vehicle->is_active)<span class="badge text-bg-secondary">Inactive</span>@endunless</h2>
        <div class="text-muted small">{{ trim($vehicle->make.' '.$vehicle->model.' '.$vehicle->year) }} · {{ $vehicle->seats }} seats · {{ $V::FUELS[$vehicle->fuel_type] }} · {{ $V::OWNERSHIP[$vehicle->ownership] }}@if($vehicle->owner_name) ({{ $vehicle->owner_name }})@endif</div>
    </div>
    @if($can)
    <div class="ms-auto d-flex flex-wrap gap-2">
        <a href="{{ route('transport.fuel.create', ['vehicle_id' => $vehicle->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-fuel-pump"></i> Add fuel</a>
        <a href="{{ route('transport.maintenance.create', ['vehicle_id' => $vehicle->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-tools"></i> Add service / repair</a>
        <a href="{{ route('transport.maintenance.create', ['vehicle_id' => $vehicle->id, 'type' => 'breakdown']) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-exclamation-triangle"></i> Breakdown</a>
        <a href="{{ route('transport.vehicles.edit', $vehicle) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Edit</a>
        <form method="POST" action="{{ route('transport.vehicles.toggle', $vehicle) }}" data-confirm="{{ $vehicle->is_active ? 'Deactivate this vehicle?' : 'Activate this vehicle?' }}">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $vehicle->is_active ? 'Deactivate' : 'Activate' }}</button></form>
    </div>
    @endif
</div>
<div class="row g-3 mb-3">
    @foreach([['Odometer', number_format($km).' km'], ['Mileage', $mileage ? $mileage.' km/'.\App\Models\Transport\FuelEntry::UNITS[$vehicle->fuel_type] : '—'], ['Fuel (all time)', inr($totals['fuel'])],
              ['Maintenance (all time)', inr($totals['maintenance'])], ['Other expenses', inr($totals['expenses'])], ['Trips logged', number_format($totals['trips'])]] as [$l, $val])
        <div class="col-6 col-md-2"><div class="card p-3 h-100"><div class="small text-muted">{{ $l }}</div><div class="fw-bold">{{ $val }}</div></div></div>
    @endforeach
</div>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">Documents</div>
            <table class="table table-sm mb-0">
                <tr><th>Insurance</th><td>{{ $vehicle->insurance_company }} {{ $vehicle->insurance_policy_no }}@if((float) $vehicle->insurance_amount) · {{ inr($vehicle->insurance_amount) }}@endif<br>@include('transport.partials.expiry', ['date' => $vehicle->insurance_expiry])</td></tr>
                <tr><th>Pollution (PUC)</th><td>{{ $vehicle->puc_no }} @include('transport.partials.expiry', ['date' => $vehicle->puc_expiry])</td></tr>
                <tr><th>Permit</th><td>{{ $vehicle->permit_no }} @include('transport.partials.expiry', ['date' => $vehicle->permit_expiry])</td></tr>
                <tr><th>Fitness</th><td>@include('transport.partials.expiry', ['date' => $vehicle->fitness_expiry])</td></tr>
                <tr><th>Monthly</th><td>EMI {{ inr($vehicle->monthly_emi) }} · Rent {{ inr($vehicle->monthly_rent) }}</td></tr>
                @if($vehicle->service_interval_km)<tr><th>Service</th><td>every {{ number_format($vehicle->service_interval_km) }} km</td></tr>@endif
            </table>
        </div>
        <div class="card mb-3">
            <div class="card-header">Routes & staff</div>
            <ul class="list-group list-group-flush">
                @foreach($vehicle->routes as $r)<li class="list-group-item"><a href="{{ route('transport.routes.show', $r) }}">{{ $r->label() }}</a> <span class="small text-muted">· trip {{ $r->trip_no }} · {{ \App\Models\Transport\TransportRoute::SHIFTS[$r->shift] }} · driver {{ $r->driver?->name ?? '—' }}</span></li>@endforeach
                @foreach($vehicle->staff as $s)<li class="list-group-item"><i class="bi bi-person"></i> <a href="{{ route('transport.staff.show', $s) }}">{{ $s->name }}</a> <span class="small text-muted">{{ \App\Models\Transport\TransportStaff::TYPES[$s->type] }} · {{ $s->phone }}</span></li>@endforeach
                @if($vehicle->routes->isEmpty() && $vehicle->staff->isEmpty())<li class="list-group-item text-muted small">Not assigned to any route yet.</li>@endif
            </ul>
        </div>
        @include('transport.partials.files', ['files' => $vehicle->files, 'ownerType' => 'vehicle', 'ownerId' => $vehicle->id])
        @if($vehicle->notes)<div class="card"><div class="card-body small" style="white-space:pre-line">{{ $vehicle->notes }}</div></div>@endif
    </div>
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header d-flex">Maintenance & repair history <a href="{{ route('transport.maintenance.index', ['vehicle_id' => $vehicle->id]) }}" class="ms-auto small">All</a></div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>Date</th><th>Work</th><th>Km</th><th>Vendor</th><th class="text-end">Cost</th></tr></thead>
                <tbody>
                @forelse($maintenance as $m)
                    <tr><td class="text-nowrap">{{ $m->service_date->format('d M Y') }}</td><td><span class="badge text-bg-light border">{{ \App\Models\Transport\VehicleMaintenance::TYPES[$m->type] }}</span> {{ $m->title }} @if($m->status !== 'done')<span class="badge {{ $m->status === 'open' ? 'text-bg-danger' : 'text-bg-info' }}">{{ \App\Models\Transport\VehicleMaintenance::STATUSES[$m->status] }}</span>@endif</td>
                        <td>{{ $m->odometer ? number_format($m->odometer) : '' }}</td><td class="small">{{ $m->vendor?->name }}</td><td class="text-end">{{ inr($m->total_cost) }}</td></tr>
                @empty<tr><td colspan="5" class="text-muted text-center py-3">No records.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
        <div class="card mb-3">
            <div class="card-header d-flex">Fuel <a href="{{ route('transport.fuel.index', ['vehicle_id' => $vehicle->id]) }}" class="ms-auto small">All</a></div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>Date</th><th>Qty</th><th>Rate</th><th>Odometer</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                @forelse($fuel as $f)
                    <tr><td>{{ $f->entry_date->format('d M Y') }}</td><td>{{ $f->quantity + 0 }} {{ $f->unit() }}</td><td>{{ inr($f->rate) }}</td><td>{{ $f->odometer ? number_format($f->odometer) : '' }}</td><td class="text-end">{{ inr($f->amount) }}</td></tr>
                @empty<tr><td colspan="5" class="text-muted text-center py-3">No fuel entries.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
        <div class="card mb-3">
            <div class="card-header">Recent trips</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>Date</th><th>Shift</th><th>Route</th><th>Driver</th><th class="text-end">Km</th></tr></thead>
                <tbody>
                @forelse($trips as $t)
                    <tr><td>{{ $t->trip_date->format('d M Y') }}</td><td>{{ ucfirst($t->shift) }}</td><td>{{ $t->route?->label() }}</td><td>{{ $t->driver?->name }}</td><td class="text-end">{{ $t->km() ?: '' }}</td></tr>
                @empty<tr><td colspan="5" class="text-muted text-center py-3">No trips logged.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
        <div class="card">
            <div class="card-header d-flex">EMI, rent, insurance & other <a href="{{ route('transport.expenses.index', ['vehicle_id' => $vehicle->id]) }}" class="ms-auto small">All</a></div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <tbody>
                @forelse($expenses as $e)
                    <tr><td>{{ $e->expense_date->format('d M Y') }}</td><td>{{ \App\Models\Transport\TransportExpense::CATEGORIES[$e->category] }}</td><td>{{ $e->title }}</td><td class="text-end">{{ inr($e->amount) }}</td></tr>
                @empty<tr><td class="text-muted text-center py-3">No expenses.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection
