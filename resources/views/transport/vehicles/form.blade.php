@extends('layouts.app')
@section('title', $vehicle->exists ? 'Edit '.$vehicle->bus_no : 'Add Vehicle')
@section('content')
@php($v = fn ($f) => old($f, $vehicle->$f instanceof \Carbon\Carbon ? $vehicle->$f->format('Y-m-d') : $vehicle->$f))
<form method="POST" action="{{ $vehicle->exists ? route('transport.vehicles.update', $vehicle) : route('transport.vehicles.store') }}" class="card" style="max-width:980px">
    @csrf @if($vehicle->exists) @method('PUT') @endif
    <div class="card-header">Vehicle</div>
    <div class="card-body row g-3">
        <div class="col-md-3"><label class="form-label required">Bus number</label><input name="bus_no" value="{{ $v('bus_no') }}" class="form-control" placeholder="Bus 1" required></div>
        <div class="col-md-3"><label class="form-label required">Registration no.</label><input name="registration_no" value="{{ $v('registration_no') }}" class="form-control text-uppercase" placeholder="UP32 AB 1234" required></div>
        <div class="col-md-3"><label class="form-label">Company</label><input name="make" value="{{ $v('make') }}" class="form-control" placeholder="Tata, Force, Eicher…"></div>
        <div class="col-md-3"><label class="form-label">Model</label><input name="model" value="{{ $v('model') }}" class="form-control" placeholder="Starbus 40"></div>
        <div class="col-md-2"><label class="form-label">Year</label><input type="number" name="year" value="{{ $v('year') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label required">Seats</label><input type="number" name="seats" min="1" value="{{ $v('seats') }}" class="form-control" required></div>
        <div class="col-md-2"><label class="form-label required">Fuel</label><select name="fuel_type" class="form-select">@foreach(\App\Models\Transport\Vehicle::FUELS as $k => $l)<option value="{{ $k }}" @selected($v('fuel_type') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Odometer (km)</label><input type="number" name="odometer" min="0" value="{{ $v('odometer') }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Service every (km)</label><input type="number" name="service_interval_km" min="0" value="{{ $v('service_interval_km') }}" class="form-control" placeholder="e.g. 10000"></div>
    </div>
    <div class="card-header border-top">Ownership & monthly cost</div>
    <div class="card-body row g-3">
        <div class="col-md-3"><label class="form-label required">Owned / rented</label><select name="ownership" class="form-select">@foreach(\App\Models\Transport\Vehicle::OWNERSHIP as $k => $l)<option value="{{ $k }}" @selected($v('ownership') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Owner (if rented)</label><input name="owner_name" value="{{ $v('owner_name') }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Monthly rent (₹)</label><input type="number" step="0.01" min="0" name="monthly_rent" value="{{ $v('monthly_rent') }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Monthly EMI (₹)</label><input type="number" step="0.01" min="0" name="monthly_emi" value="{{ $v('monthly_emi') }}" class="form-control"></div>
    </div>
    <div class="card-header border-top">Insurance, pollution, permit & fitness <span class="text-muted small fw-normal">(you get a reminder 30 days before expiry)</span></div>
    <div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label">Insurance company</label><input name="insurance_company" value="{{ $v('insurance_company') }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Policy no.</label><input name="insurance_policy_no" value="{{ $v('insurance_policy_no') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Valid till</label><input type="date" name="insurance_expiry" value="{{ $v('insurance_expiry') }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Premium (₹)</label><input type="number" step="0.01" min="0" name="insurance_amount" value="{{ $v('insurance_amount') }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">PUC certificate no.</label><input name="puc_no" value="{{ $v('puc_no') }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">PUC valid till</label><input type="date" name="puc_expiry" value="{{ $v('puc_expiry') }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Permit no.</label><input name="permit_no" value="{{ $v('permit_no') }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Permit valid till</label><input type="date" name="permit_expiry" value="{{ $v('permit_expiry') }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Fitness valid till</label><input type="date" name="fitness_expiry" value="{{ $v('fitness_expiry') }}" class="form-control"></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" rows="2" class="form-control">{{ $v('notes') }}</textarea></div>
    </div>
    <div class="card-footer d-flex gap-2"><a href="{{ $vehicle->exists ? route('transport.vehicles.show', $vehicle) : route('transport.vehicles.index') }}" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-primary ms-auto">Save vehicle</button></div>
</form>
@endsection
