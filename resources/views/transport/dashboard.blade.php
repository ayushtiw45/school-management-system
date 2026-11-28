@extends('layouts.app')
@section('title', 'Transport Dashboard')
@section('content')
@php($can = auth()->user()->canManageTransport())
@if($counts['vehicles'] === 0)
    <div class="alert alert-light border"><strong>Start here:</strong> 1) <a href="{{ route('transport.vehicles.create') }}">add your buses</a> → 2) <a href="{{ route('transport.staff.create') }}">add drivers & conductors</a> → 3) <a href="{{ route('transport.routes.create') }}">create routes with stops</a> → 4) add students to each route (their monthly transport fee is created automatically).</div>
@endif
<div class="row g-3 mb-3">
    @foreach([['Vehicles', $counts['vehicles'], 'bi-truck-front', route('transport.vehicles.index')], ['Routes', $counts['routes'], 'bi-signpost-split', route('transport.routes.index')],
              ['Drivers', $counts['drivers'], 'bi-person-vcard', route('transport.staff.index', ['type' => 'driver'])], ['Conductors', $counts['conductors'], 'bi-person-badge', route('transport.staff.index', ['type' => 'conductor'])],
              ['Students on buses', $counts['students'], 'bi-people', route('transport.routes.index')], ['Off road (breakdown)', $counts['off_road'], 'bi-exclamation-triangle', route('transport.maintenance.index', ['status' => 'open'])]] as [$l, $val, $icon, $link])
        <div class="col-6 col-md-4 col-xl-2"><a href="{{ $link }}" class="card stat h-100 text-decoration-none"><div class="card-body">
            <div class="small text-muted"><i class="bi {{ $icon }}"></i> {{ $l }}</div><div class="value text-body {{ $l === 'Off road (breakdown)' && $val ? 'text-danger' : '' }}">{{ $val }}</div></div></a></div>
    @endforeach
</div>

@if(auth()->user()->canSeeAccounts())
<div class="card mb-3"><div class="card-body d-flex flex-wrap gap-4 align-items-center py-2">
    <div class="fw-semibold">{{ now()->format('F Y') }}</div>
    <div><span class="small text-muted">Fee billed</span> <strong>{{ inr($month['billed']) }}</strong></div>
    <div><span class="small text-muted">Fee collected</span> <strong class="text-success">{{ inr($month['income']) }}</strong></div>
    <div><span class="small text-muted">Costs</span> <strong class="text-danger">{{ inr($month['cost']) }}</strong> <span class="small text-muted">(fuel {{ inr($month['fuel']) }} · repairs {{ inr($month['maintenance']) }} · salary {{ inr($month['salary']) }})</span></div>
    <div><span class="small text-muted">Profit</span> <strong class="{{ $month['income'] - $month['cost'] >= 0 ? 'text-success' : 'text-danger' }}">{{ inr($month['income'] - $month['cost']) }}</strong></div>
    @if($month['km'])<div><span class="small text-muted">Cost / km</span> <strong>{{ inr($month['cost'] / $month['km']) }}</strong></div>@endif
    <a href="{{ route('transport.reports.index') }}" class="ms-auto small">All reports</a>
</div></div>
@endif

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header d-flex"><span><i class="bi bi-bell"></i> Reminders (next 30 days)</span><a href="{{ route('transport.reports.show', 'documents') }}" class="ms-auto small">All documents</a></div>
            <ul class="list-group list-group-flush">
                @forelse($reminders as $r)
                    @php($late = ($r['days'] !== null && $r['days'] < 0) || ($r['km_left'] !== null && $r['km_left'] < 0))
                    <a href="{{ $r['url'] }}" class="list-group-item list-group-item-action d-flex gap-2 align-items-center">
                        <i class="bi {{ $late ? 'bi-x-octagon-fill text-danger' : 'bi-exclamation-circle text-warning' }}"></i>
                        <div><strong>{{ $r['what'] }}</strong> · {{ $r['name'] }}</div>
                        <span class="ms-auto small text-nowrap {{ $late ? 'text-danger fw-semibold' : 'text-muted' }}">
                            @if($r['days'] !== null){{ $r['days'] < 0 ? 'expired '.abs($r['days']).' d ago' : ($r['days'] === 0 ? 'today' : 'in '.$r['days'].' d') }} · {{ $r['due']->format('d M') }}@endif
                            @if($r['km_left'] !== null) {{ $r['km_left'] < 0 ? number_format(abs($r['km_left'])).' km over' : number_format($r['km_left']).' km left' }}@endif
                        </span>
                    </a>
                @empty<li class="list-group-item text-muted small"><i class="bi bi-check-circle text-success"></i> Nothing due. Insurance, PUC, permit, fitness, licences and services are fine.</li>@endforelse
            </ul>
        </div>
        @if($today['breakdowns']->isNotEmpty())
        <div class="card mb-3 border-danger"><div class="card-header text-danger">Vehicles off road</div>
            <ul class="list-group list-group-flush">@foreach($today['breakdowns'] as $b)<a href="{{ route('transport.maintenance.edit', $b) }}" class="list-group-item list-group-item-action">{{ $b->vehicle->label() }} · {{ $b->title }} <span class="small text-muted">since {{ $b->service_date->format('d M') }}</span></a>@endforeach</ul></div>
        @endif
    </div>
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header d-flex">Today <span class="small text-muted ms-2">{{ $today['trips'] }} trip(s) logged</span>
                @if($can)<a href="{{ route('transport.trips.index') }}" class="ms-auto small">Log trips</a> · <a href="{{ route('transport.attendance.index') }}" class="small ms-1">Attendance</a>@endif</div>
            <ul class="list-group list-group-flush small">
                @foreach($today['changes'] as $c)<li class="list-group-item"><i class="bi bi-arrow-left-right text-warning"></i> {{ $c->route->label() }}: @if($c->vehicle) {{ $c->vehicle->bus_no }}@endif @if($c->driver) driver {{ $c->driver->name }}@endif @if($c->conductor) conductor {{ $c->conductor->name }}@endif <span class="text-muted">({{ $c->reason }})</span></li>@endforeach
                @foreach($today['absent'] as $a)<li class="list-group-item"><i class="bi bi-person-x text-danger"></i> {{ $a->staff?->name }} — {{ \App\Models\Transport\StaffAttendance::STATUSES[$a->status] }}</li>@endforeach
                @if($today['changes']->isEmpty() && $today['absent']->isEmpty())<li class="list-group-item text-muted">No changes or absences today.</li>@endif
            </ul>
        </div>
        <div class="card">
            <div class="card-header">Seats on each route</div>
            <ul class="list-group list-group-flush small">
                @forelse($routes as $r)
                    @php($lim = $r->seatLimit())
                    <a href="{{ route('transport.routes.show', $r) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                        <span class="text-truncate" style="max-width:55%">{{ $r->label() }} <span class="text-muted">{{ $r->vehicle?->bus_no }}</span></span>
                        <div class="progress flex-grow-1" style="height:6px"><div class="progress-bar {{ $lim && $r->students_count > $lim ? 'bg-danger' : '' }}" style="width:{{ $lim ? min(100, round($r->students_count / $lim * 100)) : 0 }}%;{{ ! $lim || $r->students_count <= $lim ? 'background:#FF4B32' : '' }}"></div></div>
                        <span class="{{ $lim && $r->students_count > $lim ? 'text-danger fw-bold' : '' }}">{{ $r->students_count }}/{{ $lim ?: '–' }}</span>
                    </a>
                @empty<li class="list-group-item text-muted">No routes yet.</li>@endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
