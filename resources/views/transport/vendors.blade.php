@extends('layouts.app')
@section('title', 'Mechanics & Vendors')
@section('content')
@php($can = auth()->user()->canManageTransport())
@php($T = \App\Models\Transport\TransportVendor::TYPES)
<div class="row g-3">
    @if($can)
    <div class="col-lg-4">
        <form method="POST" action="{{ route('transport.vendors.store') }}" class="card">@csrf
            <div class="card-header">Add vendor</div>
            <div class="card-body row g-2">
                <div class="col-12"><input name="name" class="form-control" placeholder="Name *" required></div>
                <div class="col-12"><select name="type" class="form-select">@foreach($T as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                <div class="col-6"><input name="contact_person" class="form-control" placeholder="Contact person"></div>
                <div class="col-6"><input name="phone" class="form-control" placeholder="Mobile"></div>
                <div class="col-12"><input name="address" class="form-control" placeholder="Address"></div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary">Add</button></div>
        </form>
    </div>
    @endif
    <div class="{{ $can ? 'col-lg-8' : 'col-12' }}">
        <div class="card"><div class="table-responsive"><table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Name</th><th>Type</th><th>Contact</th><th>Jobs</th><th class="text-end">Paid</th><th></th></tr></thead>
            <tbody>
            @forelse($vendors as $x)
                <tr><td>{{ $x->name }}<div class="small text-muted">{{ $x->address }}</div></td><td>{{ $T[$x->type] ?? $x->type }}</td><td class="small">{{ $x->contact_person }}<br>@if($x->phone)<a href="tel:{{ $x->phone }}">{{ $x->phone }}</a>@endif</td>
                    <td>{{ $x->maintenances_count }}</td><td class="text-end">{{ inr($x->maintenances_sum_total_cost ?? 0) }}</td>
                    <td class="text-end">@if($can)<form method="POST" action="{{ route('transport.vendors.destroy', $x) }}" data-confirm="Delete this vendor?">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-x-lg"></i></button></form>@endif</td></tr>
            @empty<tr><td colspan="6" class="text-muted text-center py-3">No vendors yet.</td></tr>@endforelse
            </tbody>
        </table></div></div>
    </div>
</div>
@endsection
