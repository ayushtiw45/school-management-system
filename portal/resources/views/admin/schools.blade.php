@extends('layouts.admin')
@section('title', 'Schools')
@section('content')
<div class="card">
    <div class="card-header bg-white">
        <form class="row g-2 align-items-center">
            <div class="col-md-4"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="School, name, phone, email, city"></div>
            <div class="col-md-3"><select name="filter" class="form-select form-select-sm"><option value="">All</option><option value="licensed" @selected(request('filter') === 'licensed')>Has license</option><option value="not_licensed" @selected(request('filter') === 'not_licensed')>No license yet</option><option value="not_downloaded" @selected(request('filter') === 'not_downloaded')>Not downloaded</option></select></div>
            <div class="col-auto"><button class="btn btn-sm btn-navy">Filter</button></div>
            <div class="col-auto ms-auto"><a class="btn btn-sm btn-outline-success" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}"><i class="bi bi-download"></i> Export CSV</a></div>
        </form>
    </div>
    <div class="table-responsive"><table class="table table-hover mb-0 align-middle">
        <thead><tr><th>School</th><th>Contact</th><th>Phone</th><th>Email</th><th>City</th><th>Joined</th><th>Downloads</th><th>Licenses</th><th></th></tr></thead>
        <tbody>
        @forelse($schools as $s)
            <tr>
                <td><a href="{{ route('admin.schools.show', $s) }}" class="fw-semibold">{{ $s->school_name }}</a></td>
                <td>{{ $s->name }}</td>
                <td>{{ $s->phone }}</td><td class="small">{{ $s->email }}</td><td>{{ $s->city }}{{ $s->state ? ', '.$s->state : '' }}</td>
                <td>{{ $s->created_at->format('d M Y') }}</td><td>{{ $s->downloads_count }}</td><td>{{ $s->licenses_count }}</td>
                <td class="text-nowrap"><a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="https://wa.me/{{ strlen($d = preg_replace('/\D/', '', $s->phone)) === 10 ? '91'.$d : $d }}"><i class="bi bi-whatsapp"></i></a></td>
            </tr>
        @empty
            <tr><td colspan="9" class="text-center text-muted py-4">No schools found.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="card-footer bg-white d-flex"><span class="small text-muted">{{ $schools->total() }} school(s)</span><div class="ms-auto">{{ $schools->links('pagination::bootstrap-5') }}</div></div>
</div>
@endsection
