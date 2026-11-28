@extends('layouts.app')
@section('title', 'Leave & Substitutes')
@section('content')
@php($can = auth()->user()->canManageTransport())
<div class="row g-3">
    @if($can)
    <div class="col-lg-4">
        <form method="POST" action="{{ route('transport.leaves.store') }}" class="card">
            @csrf
            <div class="card-header">Add leave</div>
            <div class="card-body row g-2">
                <div class="col-12"><label class="form-label required">Driver / conductor</label><select name="transport_staff_id" class="form-select" required><option value="">—</option>@foreach($staff as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ \App\Models\Transport\TransportStaff::TYPES[$s->type] }})</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label required">From</label><input type="date" name="from_date" value="{{ now()->format('Y-m-d') }}" class="form-control" required></div>
                <div class="col-6"><label class="form-label required">To</label><input type="date" name="to_date" value="{{ now()->format('Y-m-d') }}" class="form-control" required></div>
                <div class="col-12"><label class="form-label">Reason</label><input name="reason" class="form-control"></div>
                <div class="col-12"><label class="form-label">Substitute</label><select name="substitute_id" class="form-select"><option value="">No substitute</option>@foreach($staff as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ \App\Models\Transport\TransportStaff::TYPES[$s->type] }})</option>@endforeach</select>
                    <div class="form-text">The substitute is put on this person's routes for those days.</div></div>
                <div class="col-12"><label class="form-label">Status</label><select name="status" class="form-select">@foreach(\App\Models\Transport\StaffLeave::STATUSES as $k => $l)<option value="{{ $k }}" @selected($k === 'approved')>{{ $l }}</option>@endforeach</select></div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary">Save leave</button></div>
        </form>
    </div>
    @endif
    <div class="{{ $can ? 'col-lg-8' : 'col-12' }}">
        <div class="card">
            <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                <thead><tr><th>Name</th><th>Dates</th><th>Days</th><th>Reason</th><th>Substitute</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($leaves as $l)
                    <tr><td>{{ $l->staff?->name }}</td><td class="text-nowrap">{{ $l->from_date->format('d M') }} – {{ $l->to_date->format('d M Y') }}</td><td>{{ $l->days() }}</td><td class="small">{{ $l->reason }}</td><td>{{ $l->substitute?->name ?? '—' }}</td>
                        <td>@if($can && $l->status === 'pending')
                                <form method="POST" action="{{ route('transport.leaves.update', $l) }}" class="d-inline">@csrf @method('PUT')<input type="hidden" name="status" value="approved"><button class="btn btn-sm btn-success">Approve</button></form>
                                <form method="POST" action="{{ route('transport.leaves.update', $l) }}" class="d-inline">@csrf @method('PUT')<input type="hidden" name="status" value="rejected"><button class="btn btn-sm btn-outline-danger">Reject</button></form>
                            @else<span class="badge {{ ['approved' => 'text-bg-success', 'pending' => 'text-bg-warning', 'rejected' => 'text-bg-secondary'][$l->status] }}">{{ \App\Models\Transport\StaffLeave::STATUSES[$l->status] }}</span>@endif</td>
                        <td>@if($can)<form method="POST" action="{{ route('transport.leaves.destroy', $l) }}" data-confirm="Delete this leave?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button></form>@endif</td></tr>
                @empty<tr><td colspan="7" class="text-muted text-center py-3">No leave recorded.</td></tr>@endforelse
                </tbody>
            </table></div>
            @if($leaves->hasPages())<div class="card-footer">{{ $leaves->links('pagination::bootstrap-5') }}</div>@endif
        </div>
    </div>
</div>
@endsection
