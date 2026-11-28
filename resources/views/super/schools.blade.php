@extends('layouts.super')
@section('title', 'Schools')
@section('content')
<div class="card">
    <div class="card-header bg-white">
        <form class="row g-2 align-items-center">
            <div class="col-md-4"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="School, phone, email, city"></div>
            <div class="col-md-3"><select name="status" class="form-select form-select-sm"><option value="">All</option>@foreach(['locked' => 'Locked (₹1 not paid)', 'active' => 'Active', 'expired' => 'Plan ended'] as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="col-auto"><button class="btn btn-sm btn-navy">Filter</button></div>
            <div class="col-auto ms-auto"><a class="btn btn-sm btn-outline-success" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"><i class="bi bi-download"></i> Export CSV</a></div>
        </form>
    </div>
    <div class="table-responsive"><table class="table table-hover mb-0 align-middle">
        <thead><tr><th>School</th><th>Admin</th><th>Phone</th><th>City</th><th>Joined</th><th>Users</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($schools as $s)
            <tr>
                <td><a href="{{ route('super.schools.show', $s) }}" class="fw-semibold">{{ $s->name }}</a><div class="small text-muted">{{ $s->email }}</div></td>
                <td>{{ $s->users->first()?->name }}</td>
                <td>{{ $s->phone }}</td><td>{{ $s->city }}{{ $s->state ? ', '.$s->state : '' }}</td>
                <td>{{ $s->created_at->format('d M Y') }}</td><td>{{ $s->users_count }}</td><td>@include('super.status', ['school' => $s])</td>
                <td>@if($s->phone)<a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="https://wa.me/{{ strlen($d = preg_replace('/\D/', '', $s->phone)) === 10 ? '91'.$d : $d }}"><i class="bi bi-whatsapp"></i></a>@endif</td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-center text-muted py-4">No schools found.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="card-footer bg-white d-flex"><span class="small text-muted">{{ $schools->total() }} school(s)</span><div class="ms-auto">{{ $schools->links('pagination::bootstrap-5') }}</div></div>
</div>
@endsection
