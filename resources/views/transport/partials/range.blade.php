{{-- From/to + vehicle filter. Vars: $from, $to, $vehicles, $extra (html) --}}
<form class="card mb-3"><div class="card-body row g-2 align-items-end py-2">
    <div class="col-6 col-md-2"><label class="form-label small mb-0">From</label><input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="form-control form-control-sm"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-0">To</label><input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="form-control form-control-sm"></div>
    <div class="col-6 col-md-3"><label class="form-label small mb-0">Vehicle</label><select name="vehicle_id" class="form-select form-select-sm"><option value="">All vehicles</option>@foreach($vehicles as $v)<option value="{{ $v->id }}" @selected(request('vehicle_id') == $v->id)>{{ $v->label() }}</option>@endforeach</select></div>
    {!! $extra ?? '' !!}
    <div class="col-6 col-md-auto"><button class="btn btn-sm btn-primary">Show</button></div>
</div></form>
