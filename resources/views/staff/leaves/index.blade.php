@extends('layouts.app')
@section('title', 'Staff Leave')
@section('content')
@php($can = auth()->user()->canManageStaff())
<div class="d-flex flex-wrap gap-2 mb-3">
    <div class="btn-group btn-group-sm">
        @foreach(['pending' => 'Pending ('.$pendingCount.')', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $k => $l)
            <a href="{{ route('staff.leaves.index', ['status' => $k]) }}" class="btn btn-outline-primary {{ $status === $k && ! request('balances') ? 'active' : '' }}">{{ $l }}</a>
        @endforeach
    </div>
    <a href="{{ route('staff.leaves.index', ['balances' => 1, 'status' => $status]) }}" class="btn btn-sm btn-outline-secondary {{ request('balances') ? 'active' : '' }}">Balances of everyone</a>
    <span class="small text-muted align-self-center ms-auto">Leave year {{ $year[0]->format('d M Y') }} – {{ $year[1]->format('d M Y') }}</span>
</div>
<div class="row g-3">
    @if($can)
    <div class="col-lg-4">
        <form method="POST" action="{{ route('staff.leaves.store') }}" class="card mb-3">@csrf
            <div class="card-header">Add leave</div>
            <div class="card-body row g-2">
                <div class="col-12"><select name="employee_id" class="form-select" required><option value="">Staff member…</option>@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach</select></div>
                <div class="col-12"><select name="leave_type_id" class="form-select">@foreach($types as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ $t->code }})</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label small mb-0">From</label><input type="date" name="from_date" value="{{ now()->format('Y-m-d') }}" class="form-control" required></div>
                <div class="col-6"><label class="form-label small mb-0">To</label><input type="date" name="to_date" value="{{ now()->format('Y-m-d') }}" class="form-control" required></div>
                <div class="col-12"><div class="form-check"><input type="checkbox" name="half_day" value="1" class="form-check-input" id="hd"><label class="form-check-label" for="hd">Half day</label></div></div>
                <div class="col-12"><input name="reason" class="form-control" placeholder="Reason"></div>
                <div class="col-12"><select name="status" class="form-select"><option value="approved">Approve now</option><option value="pending">Keep pending</option></select></div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary">Save</button></div>
        </form>
        <form method="POST" action="{{ route('staff.leaves.types') }}" class="card">@csrf
            <div class="card-header">Leave types (days per year)</div>
            <div class="card-body">
                @foreach($types as $t)
                    <div class="row g-1 mb-1 align-items-center">
                        <div class="col-5"><input name="types[{{ $t->id }}][name]" value="{{ $t->name }}" class="form-control form-control-sm"></div>
                        <div class="col-2"><input name="types[{{ $t->id }}][code]" value="{{ $t->code }}" class="form-control form-control-sm"></div>
                        <div class="col-2"><input type="number" step="0.5" name="types[{{ $t->id }}][days_per_year]" value="{{ $t->days_per_year + 0 }}" class="form-control form-control-sm"></div>
                        <div class="col-3 small"><label><input type="checkbox" name="types[{{ $t->id }}][is_paid]" value="1" class="form-check-input" @checked($t->is_paid)> Paid</label></div>
                    </div>
                @endforeach
                <div class="row g-1 mt-2">
                    <div class="col-5"><input name="new[name]" class="form-control form-control-sm" placeholder="New type"></div>
                    <div class="col-2"><input name="new[code]" class="form-control form-control-sm" placeholder="ML"></div>
                    <div class="col-2"><input type="number" name="new[days_per_year]" class="form-control form-control-sm" placeholder="Days"></div>
                    <div class="col-3 small"><label><input type="checkbox" name="new[is_paid]" value="1" class="form-check-input" checked> Paid</label></div>
                </div>
                <div class="form-text">Unpaid leave (e.g. LWP) is cut from salary as loss of pay.</div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-sm btn-outline-primary">Save types</button></div>
        </form>
    </div>
    @endif
    <div class="{{ $can ? 'col-lg-8' : 'col-12' }}">
        @if($balances)
        <div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Name</th>@foreach($types as $t)<th>{{ $t->code }}<div class="small text-muted fw-normal">used / left</div></th>@endforeach</tr></thead>
            <tbody>@foreach($employees as $e)<tr><td><a href="{{ route('staff.employees.show', $e) }}">{{ $e->name }}</a></td>
                @foreach($balances[$e->id] as $b)<td class="{{ $b['balance'] !== null && $b['balance'] < 0 ? 'text-danger fw-bold' : '' }}">{{ $b['used'] + 0 }}{{ $b['balance'] !== null ? ' / '.($b['balance'] + 0) : '' }}</td>@endforeach</tr>@endforeach</tbody>
        </table></div></div>
        @else
        <div class="card">
            <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                <thead><tr><th>Name</th><th>Type</th><th>Dates</th><th>Days</th><th>Reason</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($leaves as $l)
                    <tr><td><a href="{{ route('staff.employees.show', $l->employee) }}">{{ $l->employee?->name }}</a></td><td>{{ $l->type?->code }}</td>
                        <td class="text-nowrap">{{ $l->from_date->format('d M') }}{{ $l->to_date->ne($l->from_date) ? ' – '.$l->to_date->format('d M Y') : ' '.$l->from_date->format('Y') }}{{ $l->half_day ? ' (½)' : '' }}</td>
                        <td>{{ $l->days + 0 }}</td><td class="small">{{ $l->reason }}</td>
                        <td class="text-nowrap">
                            @if($can && $l->status === 'pending')
                                @foreach(['approved' => ['Approve', 'btn-success'], 'rejected' => ['Reject', 'btn-outline-danger']] as $st => [$lbl, $cls])
                                    <form method="POST" action="{{ route('staff.leaves.decide', $l) }}" class="d-inline">@csrf @method('PUT')<input type="hidden" name="status" value="{{ $st }}"><button class="btn btn-sm {{ $cls }}">{{ $lbl }}</button></form>
                                @endforeach
                            @else
                                <span class="badge {{ ['approved' => 'text-bg-success', 'pending' => 'text-bg-warning'][$l->status] ?? 'text-bg-secondary' }}">{{ \App\Models\Hr\EmployeeLeave::STATUSES[$l->status] }}</span>
                                @if($can && $l->status === 'approved' && $l->to_date->isFuture())<form method="POST" action="{{ route('staff.leaves.decide', $l) }}" class="d-inline">@csrf @method('PUT')<input type="hidden" name="status" value="cancelled"><button class="btn btn-link btn-sm p-0 ms-1">Cancel</button></form>@endif
                            @endif
                            @if($l->decider)<div class="small text-muted">{{ $l->decider->name }}</div>@endif
                        </td></tr>
                @empty<tr><td colspan="6" class="text-muted text-center py-3">No leave here.</td></tr>@endforelse
                </tbody>
            </table></div>
            @if($leaves->hasPages())<div class="card-footer">{{ $leaves->links('pagination::bootstrap-5') }}</div>@endif
        </div>
        @endif
    </div>
</div>
@endsection
