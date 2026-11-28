@extends('layouts.app')
@section('title', $entry->exists ? 'Edit Fuel Entry' : 'Add Fuel Entry')
@section('content')
@php($v = fn ($f) => old($f, $entry->$f instanceof \Carbon\Carbon ? $entry->$f->format('Y-m-d') : $entry->$f))
<form method="POST" action="{{ $entry->exists ? route('transport.fuel.update', $entry) : route('transport.fuel.store') }}" enctype="multipart/form-data" class="card" style="max-width:820px">
    @csrf @if($entry->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <div class="col-md-5"><label class="form-label required">Vehicle</label><select name="vehicle_id" id="fVeh" class="form-select" required><option value="">—</option>@foreach($vehicles as $x)<option value="{{ $x->id }}" data-fuel="{{ $x->fuel_type }}" @selected($v('vehicle_id') == $x->id)>{{ $x->label() }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label required">Date</label><input type="date" name="entry_date" value="{{ $v('entry_date') }}" max="{{ now()->format('Y-m-d') }}" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label required">Fuel</label><select name="fuel_type" id="fType" class="form-select">@foreach(\App\Models\Transport\Vehicle::FUELS as $k => $l)<option value="{{ $k }}" @selected($v('fuel_type') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label required">Quantity <span class="small text-muted">(L / kg / kWh)</span></label><input type="number" step="0.01" min="0.01" name="quantity" id="fQty" value="{{ $v('quantity') }}" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label required">Rate (₹)</label><input type="number" step="0.01" min="0.01" name="rate" id="fRate" value="{{ $v('rate') }}" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Amount (₹)</label><input type="number" step="0.01" min="0" name="amount" id="fAmt" value="{{ $v('amount') }}" class="form-control"><div class="form-text">Auto: qty × rate</div></div>
        <div class="col-md-3"><label class="form-label">Odometer (km)</label><input type="number" min="0" name="odometer" value="{{ $v('odometer') }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Petrol pump / station</label><input name="station" value="{{ $v('station') }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Driver</label><select name="driver_id" class="form-select"><option value="">—</option>@foreach($drivers as $d)<option value="{{ $d->id }}" @selected($v('driver_id') == $d->id)>{{ $d->name }}</option>@endforeach</select></div>
        <div class="col-md-4 d-flex align-items-end"><div class="form-check"><input type="hidden" name="full_tank" value="0"><input type="checkbox" class="form-check-input" name="full_tank" value="1" id="ft" @checked($v('full_tank'))><label class="form-check-label" for="ft">Full tank (for mileage)</label></div></div>
        <div class="col-md-6"><label class="form-label">Bill photo / PDF</label><input type="file" name="bill" accept=".pdf,image/*" class="form-control">@if($entry->bill_path)<div class="form-text"><a href="{{ route('transport.fuel.bill', $entry) }}" target="_blank">View current bill</a></div>@endif</div>
        <div class="col-md-6"><label class="form-label">Note</label><input name="notes" value="{{ $v('notes') }}" class="form-control"></div>
    </div>
    <div class="card-footer d-flex gap-2"><a href="{{ route('transport.fuel.index') }}" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-primary ms-auto">Save</button></div>
</form>
@push('scripts')
<script>
(function () {
    const q = document.getElementById('fQty'), r = document.getElementById('fRate'), a = document.getElementById('fAmt'), veh = document.getElementById('fVeh'), t = document.getElementById('fType');
    const calc = () => { if (q.value && r.value) a.value = (parseFloat(q.value) * parseFloat(r.value)).toFixed(2); };
    q.addEventListener('input', calc); r.addEventListener('input', calc);
    veh.addEventListener('change', () => { const o = veh.selectedOptions[0]; if (o && o.dataset.fuel) t.value = o.dataset.fuel; });
})();
</script>
@endpush
@endsection
