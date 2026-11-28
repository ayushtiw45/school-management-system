@extends('layouts.app')
@section('title', 'Daily Report')
@section('content')
<form class="d-flex flex-wrap gap-2 align-items-center mb-3 d-print-none">
    <input type="date" name="date" value="{{ $date->format('Y-m-d') }}" class="form-control form-control-sm" style="max-width:180px" onchange="this.form.submit()">
    <div class="form-check ms-2"><input class="form-check-input" type="checkbox" name="mine" value="1" id="mine" @checked($mine) onchange="this.form.submit()"><label for="mine" class="form-check-label small">Only my entries</label></div>
    <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-printer"></i> Print</button>
</form>
<h2 class="h5 d-none d-print-block">{{ $brandSchool->displayName() }} — Reception report {{ $date->format('d M Y') }}</h2>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card p-3"><div class="small text-muted">New enquiries</div><div class="fs-3 fw-bold">{{ $enquiries->count() }}</div></div></div>
    <div class="col-6 col-md-3"><div class="card p-3"><div class="small text-muted">Follow-ups done</div><div class="fs-3 fw-bold">{{ $followUps->count() }}</div></div></div>
    <div class="col-6 col-md-3"><div class="card p-3"><div class="small text-muted">Admission forms</div><div class="fs-3 fw-bold">{{ $admissions->count() }}</div></div></div>
    <div class="col-6 col-md-3"><a href="{{ route('enquiries.index', ['view' => 'due']) }}" class="card p-3 text-decoration-none"><div class="small text-muted">Follow-ups pending</div><div class="fs-3 fw-bold text-danger">{{ $due }}</div></a></div>
</div>
<div class="card mb-3">
    <div class="card-header">Enquiries added</div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Time</th><th>Child</th><th>Class</th><th>Parent / Mobile</th><th>Source</th><th>Next follow-up</th><th>By</th></tr></thead>
        <tbody>
        @forelse($enquiries as $e)
            <tr><td>{{ $e->created_at->format('h:i A') }}</td><td><a href="{{ route('enquiries.show', $e) }}">{{ $e->child_name }}</a></td><td>{{ $e->schoolClass?->name }}</td>
                <td>{{ $e->parent_name }} · {{ $e->phone }}</td><td>{{ \App\Models\Enquiry::SOURCES[$e->source] ?? '' }}</td><td>{{ $e->next_follow_up?->format('d M') }}</td><td>{{ $e->creator?->name }}</td></tr>
        @empty<tr><td colspan="7" class="text-muted text-center py-3">None</td></tr>@endforelse
        </tbody>
    </table></div>
</div>
<div class="card mb-3">
    <div class="card-header">Follow-ups done</div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Time</th><th>Child</th><th>Note</th><th>Next</th><th>By</th></tr></thead>
        <tbody>
        @forelse($followUps as $f)
            @php($fe = $followEnquiries[$f->enquiry_id] ?? null)
            <tr><td>{{ $f->created_at->format('h:i A') }}</td><td>@if($fe)<a href="{{ route('enquiries.show', $fe) }}">{{ $fe->child_name }}</a>@endif</td><td>{{ $f->note }}</td><td>{{ $f->next_follow_up?->format('d M') }}</td><td>{{ $f->user?->name }}</td></tr>
        @empty<tr><td colspan="5" class="text-muted text-center py-3">None</td></tr>@endforelse
        </tbody>
    </table></div>
</div>
<div class="card">
    <div class="card-header">Admission forms filled</div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Adm. No</th><th>Student</th><th>Class</th><th>Father / Mobile</th><th>Type</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($admissions as $s)
            <tr><td>{{ $s->admission_no }}</td><td><a href="{{ route('students.show', $s) }}">{{ $s->full_name }}</a></td><td>{{ $s->class_label }}</td><td>{{ $s->father_name }} · {{ $s->father_phone }}</td><td>{{ ucfirst($s->admission_type) }}</td><td>@include('students.status-badge', ['status' => $s->status])</td></tr>
        @empty<tr><td colspan="6" class="text-muted text-center py-3">None</td></tr>@endforelse
        </tbody>
    </table></div>
</div>
@endsection
