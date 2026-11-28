@extends('layouts.app')
@section('title', 'Route '.$route->label())
@section('content')
@php($can = auth()->user()->canManageTransport())
@php($limit = $route->seatLimit())
@php($hm = fn ($t) => $t ? substr($t, 0, 5) : '')
<div class="d-flex flex-wrap gap-2 align-items-start mb-3">
    <div>
        <h2 class="h4 fw-bold mb-0">{{ $route->label() }} @unless($route->is_active)<span class="badge text-bg-secondary">Not running</span>@endunless</h2>
        <div class="text-muted small">{{ \App\Models\Transport\TransportRoute::SHIFTS[$route->shift] }} · trip {{ $route->trip_no }} @if($route->start_time)· {{ $hm($route->start_time) }}–{{ $hm($route->end_time) }}@endif @if((float) $route->distance_km)· {{ (float) $route->distance_km }} km @endif
            · Vehicle <strong>{{ $route->vehicle?->label() ?? '—' }}</strong> · Driver <strong>{{ $route->driver?->name ?? '—' }}</strong> · Conductor <strong>{{ $route->conductor?->name ?? '—' }}</strong></div>
    </div>
    @if($can)<div class="ms-auto d-flex gap-2">
        <a href="{{ route('transport.routes.edit', $route) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Edit</a>
        @if($students->isEmpty())<form method="POST" action="{{ route('transport.routes.destroy', $route) }}" data-confirm="Delete this route?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>@endif
    </div>@endif
</div>
@if($today)
    <div class="alert alert-warning py-2"><i class="bi bi-arrow-left-right"></i> <strong>Today:</strong>
        @if($today->vehicle) vehicle {{ $today->vehicle->label() }}@endif @if($today->driver) · driver {{ $today->driver->name }}@endif @if($today->conductor) · conductor {{ $today->conductor->name }}@endif
        ({{ $today->reason }}, till {{ $today->to_date->format('d M') }})</div>
@endif
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">Stops <span class="badge text-bg-light border ms-2">{{ $route->stops->count() }}</span>
                @if($can && $route->stops->count() > 1)<form method="POST" action="{{ route('transport.stops.sort', $route) }}" class="ms-auto">@csrf<button class="btn btn-sm btn-outline-secondary" title="Arrange stops from the farthest to the nearest"><i class="bi bi-sort-down"></i> Arrange by distance</button></form>@endif</div>
            <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                <thead><tr><th>#</th><th>Stop</th><th>Pick-up</th><th>Drop</th><th>Km</th><th>Fee</th><th></th></tr></thead>
                <tbody>
                @forelse($route->stops as $s)
                    <tr>
                        <td>{{ $s->sequence }}</td><td>{{ $s->name }}</td><td>{{ $hm($s->pickup_time) }}</td><td>{{ $hm($s->drop_time) }}</td><td>{{ $s->distance_km !== null ? (float) $s->distance_km : '' }}</td>
                        <td>{{ $s->monthly_fee !== null ? inr($s->monthly_fee) : 'route fee' }}</td>
                        <td class="text-end text-nowrap">@if($can)
                            <button class="btn btn-sm btn-link p-0" data-bs-toggle="collapse" data-bs-target="#st{{ $s->id }}"><i class="bi bi-pencil"></i></button>
                            <form method="POST" action="{{ route('transport.stops.destroy', $s) }}" class="d-inline" data-confirm="Remove this stop?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger p-0 ms-1"><i class="bi bi-x-lg"></i></button></form>
                        @endif</td>
                    </tr>
                    @if($can)
                    <tr class="collapse" id="st{{ $s->id }}"><td colspan="7">
                        <form method="POST" action="{{ route('transport.stops.update', $s) }}" class="row g-1">@csrf @method('PUT')
                            <div class="col-2"><input name="sequence" type="number" value="{{ $s->sequence }}" class="form-control form-control-sm"></div>
                            <div class="col-4"><input name="name" value="{{ $s->name }}" class="form-control form-control-sm" required></div>
                            <div class="col-3"><input name="pickup_time" type="time" value="{{ $hm($s->pickup_time) }}" class="form-control form-control-sm"></div>
                            <div class="col-3"><input name="drop_time" type="time" value="{{ $hm($s->drop_time) }}" class="form-control form-control-sm"></div>
                            <div class="col-3"><input name="distance_km" type="number" step="0.1" value="{{ $s->distance_km }}" class="form-control form-control-sm" placeholder="km"></div>
                            <div class="col-4"><input name="monthly_fee" type="number" step="0.01" value="{{ $s->monthly_fee }}" class="form-control form-control-sm" placeholder="Fee"></div>
                            <div class="col-5"><button class="btn btn-sm btn-primary w-100">Save</button></div>
                        </form>
                    </td></tr>
                    @endif
                @empty<tr><td colspan="7" class="text-muted text-center py-3">No stops yet.</td></tr>@endforelse
                </tbody>
            </table></div>
            @if($can)
            <div class="card-footer">
                <form method="POST" action="{{ route('transport.stops.store', $route) }}" class="row g-1">@csrf
                    <div class="col-md-4"><input name="name" class="form-control form-control-sm" placeholder="Stop name" required></div>
                    <div class="col-md-2"><input name="pickup_time" type="time" class="form-control form-control-sm" title="Morning pick-up"></div>
                    <div class="col-md-2"><input name="drop_time" type="time" class="form-control form-control-sm" title="Afternoon drop"></div>
                    <div class="col-md-1"><input name="distance_km" type="number" step="0.1" class="form-control form-control-sm" placeholder="km"></div>
                    <div class="col-md-2"><input name="monthly_fee" type="number" step="0.01" class="form-control form-control-sm" placeholder="Fee ₹"></div>
                    <div class="col-md-1"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus"></i></button></div>
                </form>
            </div>
            @endif
        </div>

        <div class="card mb-3">
            <div class="card-header">Temporary changes & substitutes</div>
            <ul class="list-group list-group-flush small">
                @forelse($route->changes as $c)
                    <li class="list-group-item d-flex gap-2 {{ $c->to_date->isPast() && ! $c->to_date->isToday() ? 'text-muted' : '' }}">
                        <div><strong>{{ $c->from_date->format('d M') }} – {{ $c->to_date->format('d M Y') }}</strong>:
                            @if($c->vehicle) vehicle {{ $c->vehicle->label() }}@endif @if($c->driver) · driver {{ $c->driver->name }}@endif @if($c->conductor) · conductor {{ $c->conductor->name }}@endif
                            <div class="text-muted">{{ $c->reason }}</div></div>
                        @if($can)<form method="POST" action="{{ route('transport.changes.destroy', $c) }}" class="ms-auto" data-confirm="Remove this change?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button></form>@endif
                    </li>
                @empty<li class="list-group-item text-muted">None. Use this when a bus is in the workshop or a driver is on leave.</li>@endforelse
            </ul>
            @if($can)
            <div class="card-footer">
                <form method="POST" action="{{ route('transport.changes.store', $route) }}" class="row g-1">@csrf
                    <div class="col-6 col-md-3"><input type="date" name="from_date" value="{{ now()->format('Y-m-d') }}" class="form-control form-control-sm" required></div>
                    <div class="col-6 col-md-3"><input type="date" name="to_date" value="{{ now()->format('Y-m-d') }}" class="form-control form-control-sm" required></div>
                    <div class="col-md-6"><input name="reason" class="form-control form-control-sm" placeholder="Reason, e.g. Bus 2 in workshop"></div>
                    <div class="col-md-4"><select name="vehicle_id" class="form-select form-select-sm"><option value="">Same vehicle</option>@foreach($vehicles as $x)<option value="{{ $x->id }}">{{ $x->label() }}</option>@endforeach</select></div>
                    <div class="col-md-3"><select name="driver_id" class="form-select form-select-sm"><option value="">Same driver</option>@foreach($drivers as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select></div>
                    <div class="col-md-3"><select name="conductor_id" class="form-select form-select-sm"><option value="">Same conductor</option>@foreach($conductors as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select></div>
                    <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Save</button></div>
                </form>
            </div>
            @endif
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">Students <span class="badge ms-2 {{ $limit && $students->count() > $limit ? 'text-bg-danger' : 'text-bg-light border' }}">{{ $students->count() }}{{ $limit ? ' / '.$limit.' seats' : '' }}</span>
                <button class="btn btn-sm btn-outline-secondary ms-auto" onclick="window.print()"><i class="bi bi-printer"></i> Print list</button></div>
            <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                <thead><tr><th>Student</th><th>Class</th><th>Stop</th><th>Mobile</th><th class="text-end">Fee</th><th class="no-print"></th></tr></thead>
                <tbody>
                @forelse($students as $st)
                    <tr><td><a href="{{ route('students.show', $st) }}">{{ $st->full_name }}</a><div class="small text-muted">{{ $st->student_code ?: $st->admission_no }}</div></td><td>{{ $st->class_label }}</td>
                        <td class="small">{{ $st->routeStop?->name ?? '—' }}</td><td class="small">{{ $st->parent_phone }}</td><td class="text-end">{{ inr($st->transport_fee) }}</td>
                        <td class="no-print text-end">@if($can)<a href="{{ route('transport.student.edit', $st) }}" class="btn btn-sm btn-link p-0">Change</a>@endif</td></tr>
                @empty<tr><td colspan="6" class="text-muted text-center py-3">No students on this route yet.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
        @if($can)
        <div class="card no-print">
            <div class="card-header">Add students to this route</div>
            <div class="card-body">
                <form class="d-flex gap-2 mb-2">
                    <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Choose class…</option>@foreach($classes as $c)<option value="{{ $c->id }}" @selected(request('class_id') == $c->id)>{{ $c->name }}</option>@endforeach</select>
                </form>
                @if(request('class_id'))
                    <form method="POST" action="{{ route('transport.routes.students', $route) }}">@csrf
                        <div class="border rounded p-2 mb-2" style="max-height:240px;overflow:auto">
                            @forelse($candidates as $c)
                                <div class="form-check"><input type="checkbox" name="student_ids[]" value="{{ $c->id }}" class="form-check-input" id="cs{{ $c->id }}"><label class="form-check-label" for="cs{{ $c->id }}">{{ $c->full_name }} <span class="small text-muted">{{ $c->section?->name }} · {{ $c->father_name }}</span></label></div>
                            @empty<div class="text-muted small">Every student of this class already has transport or none are active.</div>@endforelse
                        </div>
                        <div class="row g-2">
                            <div class="col-md-5"><select name="route_stop_id" class="form-select form-select-sm"><option value="">Stop…</option>@foreach($route->stops as $s)<option value="{{ $s->id }}">{{ $s->name }}{{ $s->monthly_fee !== null ? ' ('.inr($s->monthly_fee).')' : '' }}</option>@endforeach</select></div>
                            <div class="col-md-3"><input type="number" step="0.01" min="0" name="fee" class="form-control form-control-sm" placeholder="Fee (auto)"></div>
                            <div class="col-md-4"><input type="date" name="from" value="{{ now()->startOfMonth()->format('Y-m-d') }}" class="form-control form-control-sm" title="Transport from"></div>
                            <div class="col-12"><button class="btn btn-sm btn-primary w-100">Add & create monthly transport fee</button></div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
