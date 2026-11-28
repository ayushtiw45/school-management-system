@extends('layouts.app')
@section('title', $m->exists ? 'Edit Maintenance' : ($m->type === 'breakdown' ? 'Report Breakdown' : 'Add Service / Repair'))
@section('content')
@php($v = fn ($f) => old($f, $m->$f instanceof \Carbon\Carbon ? $m->$f->format('Y-m-d') : $m->$f))
<form method="POST" action="{{ $m->exists ? route('transport.maintenance.update', $m) : route('transport.maintenance.store') }}" enctype="multipart/form-data" class="card" style="max-width:900px">
    @csrf @if($m->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label required">Vehicle</label><select name="vehicle_id" class="form-select" required><option value="">—</option>@foreach($vehicles as $x)<option value="{{ $x->id }}" @selected($v('vehicle_id') == $x->id)>{{ $x->label() }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label required">Type</label><select name="type" class="form-select">@foreach(\App\Models\Transport\VehicleMaintenance::TYPES as $k => $l)<option value="{{ $k }}" @selected($v('type') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label required">Date</label><input type="date" name="service_date" value="{{ $v('service_date') }}" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label required">Status</label><select name="status" class="form-select">@foreach(\App\Models\Transport\VehicleMaintenance::STATUSES as $k => $l)<option value="{{ $k }}" @selected($v('status') === $k)>{{ $l }}</option>@endforeach</select><div class="form-text">"Scheduled" = future service (reminder)</div></div>
        <div class="col-md-8"><label class="form-label required">Work done / problem</label><input name="title" value="{{ $v('title') }}" class="form-control" placeholder="e.g. Oil + filter change, 2 rear tyres, clutch plate" required></div>
        <div class="col-md-4"><label class="form-label">Odometer (km)</label><input type="number" min="0" name="odometer" value="{{ $v('odometer') }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Mechanic / vendor</label><select name="transport_vendor_id" class="form-select"><option value="">—</option>@foreach($vendors as $x)<option value="{{ $x->id }}" @selected($v('transport_vendor_id') == $x->id)>{{ $x->name }}</option>@endforeach</select><div class="form-text"><a href="{{ route('transport.vendors.index') }}" target="_blank">Add a vendor</a></div></div>
        <div class="col-md-4"><label class="form-label">Driver at the time</label><select name="driver_id" class="form-select"><option value="">—</option>@foreach($drivers as $x)<option value="{{ $x->id }}" @selected($v('driver_id') == $x->id)>{{ $x->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Spare parts (₹)</label><input type="number" step="0.01" min="0" name="parts_cost" value="{{ $v('parts_cost') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Labour (₹)</label><input type="number" step="0.01" min="0" name="labour_cost" value="{{ $v('labour_cost') }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Next service date</label><input type="date" name="next_due_date" value="{{ $v('next_due_date') }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Next service at (km)</label><input type="number" min="0" name="next_due_km" value="{{ $v('next_due_km') }}" class="form-control"><div class="form-text">Auto from the vehicle's service interval</div></div>
        <div class="col-md-6"><label class="form-label">Bill photo / PDF</label><input type="file" name="bill" accept=".pdf,image/*" class="form-control">@if($m->bill_path)<div class="form-text"><a href="{{ route('transport.maintenance.bill', $m) }}" target="_blank">View current bill</a></div>@endif</div>
        <div class="col-md-6"><label class="form-label">Notes</label><input name="notes" value="{{ $v('notes') }}" class="form-control"></div>
    </div>
    <div class="card-footer d-flex gap-2"><a href="{{ route('transport.maintenance.index') }}" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-primary ms-auto">Save</button></div>
</form>
@endsection
