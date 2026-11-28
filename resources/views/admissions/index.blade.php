@extends('layouts.app')
@section('title', 'Admission List')
@section('content')
<div class="card">
    <div class="card-header">
        <form class="row g-2 align-items-center">
            <div class="col-auto">
                <div class="btn-group btn-group-sm">
                    @foreach(['pending' => 'Pending', 'confirmed' => 'Confirmed', 'all' => 'All'] as $k => $l)
                        <a href="{{ route('admissions.index', ['status' => $k] + request()->except('status', 'page')) }}" class="btn btn-outline-primary {{ $status === $k ? 'active' : '' }}">{{ $l }}</a>
                    @endforeach
                </div>
            </div>
            <input type="hidden" name="status" value="{{ $status }}">
            <div class="col-auto">
                <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Online + Manual</option>
                    <option value="online" @selected(request('type') === 'online')>Online only</option>
                    <option value="manual" @selected(request('type') === 'manual')>Manual only</option>
                </select>
            </div>
            <div class="col"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search name, admission no, father, mobile"></div>
            <div class="col-auto"><button class="btn btn-sm btn-primary">Search</button></div>
        </form>
    </div>
    @php($applyUrl = route('apply', \App\Support\Tenant::school()))
    <div class="card-body border-bottom bg-light d-flex flex-wrap gap-2 align-items-center py-2">
        <span class="small text-muted me-1"><i class="bi bi-link-45deg"></i> Admission form for parents:</span>
        <input class="form-control form-control-sm" style="max-width:340px" value="{{ $applyUrl }}" readonly id="applyUrl" onclick="this.select()">
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('applyUrl').value);this.innerHTML='<i class=&quot;bi bi-check2&quot;></i> Copied'"><i class="bi bi-clipboard"></i> Copy</button>
        <a href="https://wa.me/?text={{ rawurlencode('Admission open at '.$brandSchool->displayName().'. Fill the admission form online: '.$applyUrl) }}" target="_blank" rel="noopener" class="btn btn-sm btn-success"><i class="bi bi-whatsapp"></i> Share on WhatsApp</a>
        <a href="{{ $applyUrl }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-up-right"></i> Open</a>
        <a href="{{ route('admissions.blank') }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Print blank form</a>
        <a href="{{ route('admissions.create') }}" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-person-plus"></i> New admission</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr><th>Adm. No</th><th>Student</th><th>Class</th><th>Father / Mobile</th><th>Applied</th><th>Type</th><th>Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            @forelse($students as $s)
                <tr>
                    <td>{{ $s->admission_no }}@if($s->student_code)<div class="small text-muted">{{ $s->student_code }}</div>@endif</td>
                    <td><a href="{{ route('students.show', $s) }}" class="fw-semibold">{{ $s->full_name }}</a><div class="text-muted small">{{ ucfirst($s->gender) }} · DOB {{ $s->dob?->format('d M Y') }}</div></td>
                    <td>{{ $s->class_label }}</td>
                    <td>{{ $s->father_name }}<div class="text-muted small">{{ $s->father_phone }}</div></td>
                    <td>{{ $s->admission_date->format('d M Y') }}</td>
                    <td><span class="badge {{ $s->admission_type === 'online' ? 'text-bg-info' : 'text-bg-light border' }}">{{ ucfirst($s->admission_type) }}</span></td>
                    <td>@include('students.status-badge', ['status' => $s->status])</td>
                    <td class="text-end text-nowrap">
                        @if($s->status === 'pending')
                            <a href="{{ route('students.edit', $s) }}" class="btn btn-sm btn-outline-secondary" title="Edit / assign section"><i class="bi bi-pencil"></i></a>
                            @if(auth()->user()->canManageFees())
                                <a href="{{ route('payments.create', ['student_id' => $s->id]) }}" class="btn btn-sm btn-success"><i class="bi bi-cash-coin"></i> Collect fee</a>
                            @else
                                <span class="badge text-bg-warning">Awaiting fee</span>
                            @endif
                            @if(auth()->user()->hasRole('admin', 'principal'))
                                <form method="POST" action="{{ route('admissions.confirm', $s) }}" class="d-inline" data-confirm="Approve this admission without fee payment?">@csrf<button class="btn btn-sm btn-outline-success" title="Approve without fee"><i class="bi bi-check2"></i></button></form>
                            @endif
                            <form method="POST" action="{{ route('admissions.reject', $s) }}" class="d-inline" data-confirm="Delete this admission application?">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-x"></i></button></form>
                        @else
                            <a href="{{ route('admissions.print', $s) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Form</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No admissions found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($students->hasPages())<div class="card-footer">{{ $students->links('pagination::bootstrap-5') }}</div>@endif
</div>
@endsection
