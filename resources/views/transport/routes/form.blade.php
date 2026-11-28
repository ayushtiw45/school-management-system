@extends('layouts.app')
@section('title', $route->exists ? 'Edit '.$route->name : 'New Route')
@section('content')
@php($v = fn ($f) => old($f, in_array($f, ['start_time', 'end_time']) ? substr((string) $route->$f, 0, 5) : $route->$f))
<form method="POST" action="{{ $route->exists ? route('transport.routes.update', $route) : route('transport.routes.store') }}" class="card" style="max-width:900px">
    @csrf @if($route->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label required">Route name</label><input name="name" value="{{ $v('name') }}" class="form-control" placeholder="e.g. Gomti Nagar – Indira Nagar" required></div>
        <div class="col-md-2"><label class="form-label">Route no.</label><input name="route_no" value="{{ $v('route_no') }}" class="form-control" placeholder="R1"></div>
        <div class="col-md-4"><label class="form-label required">Runs</label><select name="shift" class="form-select">@foreach(\App\Models\Transport\TransportRoute::SHIFTS as $k => $l)<option value="{{ $k }}" @selected($v('shift') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label required">Trip no.</label><input type="number" min="1" max="9" name="trip_no" value="{{ $v('trip_no') }}" class="form-control"><div class="form-text">2 = second run of the same bus</div></div>
        <div class="col-md-2"><label class="form-label">Start time</label><input type="time" name="start_time" value="{{ $v('start_time') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Reach school</label><input type="time" name="end_time" value="{{ $v('end_time') }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Distance (km, one way)</label><input type="number" step="0.1" min="0" name="distance_km" value="{{ $v('distance_km') }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Monthly fee (₹)</label><input type="number" step="0.01" min="0" name="monthly_fee" value="{{ $v('monthly_fee') }}" class="form-control"><div class="form-text">Used when the stop has no fee of its own</div></div>
        <div class="col-md-4"><label class="form-label">Vehicle</label><select name="vehicle_id" class="form-select"><option value="">—</option>@foreach($vehicles as $x)<option value="{{ $x->id }}" @selected($v('vehicle_id') == $x->id)>{{ $x->label() }} ({{ $x->seats }} seats)</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Driver</label><select name="driver_id" class="form-select"><option value="">—</option>@foreach($drivers as $x)<option value="{{ $x->id }}" @selected($v('driver_id') == $x->id)>{{ $x->name }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Conductor</label><select name="conductor_id" class="form-select"><option value="">—</option>@foreach($conductors as $x)<option value="{{ $x->id }}" @selected($v('conductor_id') == $x->id)>{{ $x->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Student limit</label><input type="number" min="1" name="capacity" value="{{ $v('capacity') }}" class="form-control" placeholder="= seats"></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" rows="2" class="form-control">{{ $v('notes') }}</textarea></div>
        @if($route->exists)<div class="col-12"><div class="form-check"><input type="hidden" name="is_active" value="0"><input type="checkbox" class="form-check-input" name="is_active" value="1" id="act" @checked($route->is_active)><label for="act" class="form-check-label">Route is running</label></div></div>@endif
    </div>
    <div class="card-footer d-flex gap-2"><a href="{{ route('transport.routes.index') }}" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-primary ms-auto">Save route</button></div>
</form>
@endsection
