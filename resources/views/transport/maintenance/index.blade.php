@extends('layouts.app')
@section('title', 'Maintenance')
@section('content')
@php($M = \App\Models\Transport\VehicleMaintenance::class)
<div class="d-flex gap-2 mb-2">
    <a href="{{ route('transport.vendors.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-person-gear"></i> Mechanics & vendors</a>
    @if(auth()->user()->canManageTransport())
        <a href="{{ route('transport.maintenance.create', ['type' => 'breakdown']) }}" class="btn btn-sm btn-outline-danger ms-auto"><i class="bi bi-exclamation-triangle"></i> Breakdown</a>
        <a href="{{ route('transport.maintenance.create', ['type' => 'service']) }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Add service / repair</a>
    @endif
</div>
@include('transport.partials.range', ['extra' => '<div class="col-6 col-md-2"><label class="form-label small mb-0">Type</label><select name="type" class="form-select form-select-sm"><option value="">All</option>'.collect($M::TYPES)->map(fn ($l, $k) => '<option value="'.$k.'" '.(request('type') === $k ? 'selected' : '').'>'.e($l).'</option>')->implode('').'</select></div>'])
<div class="row g-2 mb-3">
    @foreach($M::TYPES as $k => $l)
        <div class="col-6 col-md"><div class="card p-2 h-100"><div class="small text-muted">{{ $l }}</div><div class="fw-bold">{{ inr($byType[$k]->total ?? 0) }}</div><div class="small text-muted">{{ $byType[$k]->c ?? 0 }} jobs</div></div></div>
    @endforeach
</div>
<div class="card">
    <div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
        <thead><tr><th>Date</th><th>Vehicle</th><th>Work</th><th>Km</th><th>Vendor</th><th>Next due</th><th class="text-end">Parts</th><th class="text-end">Total</th><th></th></tr></thead>
        <tbody>
        @forelse($records as $m)
            <tr class="{{ $m->status === 'open' ? 'table-danger' : ($m->status === 'scheduled' ? 'table-info' : '') }}">
                <td class="text-nowrap">{{ $m->service_date->format('d M Y') }}</td><td>{{ $m->vehicle?->bus_no }}</td>
                <td><span class="badge text-bg-light border">{{ $M::TYPES[$m->type] }}</span> {{ $m->title }} @if($m->status !== 'done')<span class="badge {{ $m->status === 'open' ? 'text-bg-danger' : 'text-bg-info' }}">{{ $M::STATUSES[$m->status] }}</span>@endif</td>
                <td>{{ $m->odometer ? number_format($m->odometer) : '' }}</td><td class="small">{{ $m->vendor?->name }}</td>
                <td class="small">{{ $m->next_due_date?->format('d M Y') }}{{ $m->next_due_km ? ($m->next_due_date ? ' / ' : '').number_format($m->next_due_km).' km' : '' }}</td>
                <td class="text-end">{{ (float) $m->parts_cost ? inr($m->parts_cost) : '' }}</td><td class="text-end">{{ inr($m->total_cost) }}</td>
                <td class="text-end text-nowrap">@if($m->bill_path)<a href="{{ route('transport.maintenance.bill', $m) }}" target="_blank"><i class="bi bi-paperclip"></i></a>@endif
                    @if(auth()->user()->canManageTransport())<a href="{{ route('transport.maintenance.edit', $m) }}" class="ms-1"><i class="bi bi-pencil"></i></a>
                    <form method="POST" action="{{ route('transport.maintenance.destroy', $m) }}" class="d-inline" data-confirm="Delete this record?">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger p-0 ms-1"><i class="bi bi-x-lg"></i></button></form>@endif</td>
            </tr>
        @empty<tr><td colspan="9" class="text-muted text-center py-3">No maintenance records.</td></tr>@endforelse
        </tbody>
    </table></div>
    @if($records->hasPages())<div class="card-footer">{{ $records->links('pagination::bootstrap-5') }}</div>@endif
</div>
@endsection
