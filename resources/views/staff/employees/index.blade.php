@extends('layouts.app')
@section('title', 'All Staff')
@section('content')
@php($E = \App\Models\Hr\Employee::class)
<div class="card mb-3"><div class="card-body py-2">
    <form class="row g-2 align-items-center">
        <div class="col-auto"><div class="btn-group btn-group-sm">
            @foreach(['' => 'All', 'teaching' => 'Teachers', 'non_teaching' => 'Non-teaching'] as $k => $l)
                <a href="{{ route('staff.employees.index', array_filter(['type' => $k, 'status' => request('status')])) }}" class="btn btn-outline-primary {{ request('type', '') === $k ? 'active' : '' }}">{{ $l }}</a>
            @endforeach
        </div></div>
        <input type="hidden" name="type" value="{{ request('type') }}">
        <div class="col-auto"><select name="status" class="form-select form-select-sm" onchange="this.form.submit()"><option value="active">Working</option><option value="left" @selected($status === 'left')>Left</option><option value="all" @selected($status === 'all')>All</option></select></div>
        <div class="col-auto"><select name="department" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">All departments</option>@foreach($departments as $d)<option @selected(request('department') === $d)>{{ $d }}</option>@endforeach</select></div>
        <div class="col"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search name, ID, mobile, designation"></div>
        <div class="col-auto"><button class="btn btn-sm btn-primary">Search</button></div>
        <div class="col-auto"><a href="{{ route('staff.idcards', ['type' => request('type')]) }}" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="bi bi-person-badge"></i> ID cards</a></div>
        @if(auth()->user()->canManageStaff())
            <div class="col-auto"><a href="{{ route('staff.employees.create', ['type' => 'teaching']) }}" class="btn btn-sm btn-success"><i class="bi bi-plus-lg"></i> Add teacher</a></div>
            <div class="col-auto"><a href="{{ route('staff.employees.create', ['type' => 'non_teaching']) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-plus-lg"></i> Add other staff</a></div>
        @endif
    </form>
</div></div>
<div class="card">
    <div class="table-responsive"><table class="table table-hover mb-0 align-middle">
        <thead><tr><th></th><th>Name</th><th>Designation</th><th>Department</th><th>Mobile</th><th>Joined</th><th>Periods/wk</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($employees as $e)
            <tr>
                <td style="width:46px">@if($e->photo)<img src="{{ route('staff.employees.photo', $e) }}" class="rounded-circle" style="width:36px;height:36px;object-fit:cover" alt="">@else<span class="avatar rounded-circle d-inline-flex align-items-center justify-content-center bg-light" style="width:36px;height:36px">{{ strtoupper(substr($e->name, 0, 1)) }}</span>@endif</td>
                <td><a href="{{ route('staff.employees.show', $e) }}" class="fw-semibold">{{ $e->name }}</a><div class="small text-muted">{{ $e->employee_id }} · {{ $E::TYPES[$e->type] }}</div></td>
                <td>{{ $e->designation }}</td><td class="small">{{ $e->department }}</td>
                <td>@if($e->phone)<a href="tel:{{ $e->phone }}">{{ $e->phone }}</a>@endif</td>
                <td class="small">{{ $e->joining_date?->format('d M Y') }}</td>
                <td>{{ $e->isTeacher() ? $e->timetable_count : '' }}</td>
                <td>@if($e->isActive())<span class="badge text-bg-success">{{ $E::EMPLOYMENT[$e->employment_type] }}</span>@else<span class="badge text-bg-secondary">{{ $E::STATUSES[$e->status] }}</span>@endif</td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-4">No staff found.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="card-footer small text-muted">{{ $employees->count() }} staff · {{ $employees->where('type', 'teaching')->count() }} teachers</div>
</div>
@endsection
