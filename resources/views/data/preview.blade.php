@extends('layouts.app')
@section('title', 'Check import · '.$meta[0])
@section('content')
@php($valid = $import['valid'])
@php($errors2 = $import['errors'])
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card p-3"><div class="small text-muted">Rows in file</div><div class="fs-3 fw-bold">{{ $import['total'] }}</div></div></div>
    <div class="col-6 col-md-3"><div class="card p-3"><div class="small text-muted">Ready to import</div><div class="fs-3 fw-bold text-success">{{ count($valid) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="card p-3"><div class="small text-muted">With problems (skipped)</div><div class="fs-3 fw-bold {{ $errors2 ? 'text-danger' : '' }}">{{ count($errors2) }}</div></div></div>
    <div class="col-6 col-md-3"><div class="card p-3"><div class="small text-muted">File</div><div class="fw-semibold text-truncate">{{ $import['name'] }}</div></div></div>
</div>
@if($import['unknown'])<div class="alert alert-light border small">These columns were not recognised and are ignored: {{ implode(', ', $import['unknown']) }}</div>@endif

@if($errors2)
<div class="card mb-3 border-danger">
    <div class="card-header text-danger">Rows with problems — fix them in Excel and upload again (or import the correct rows now)</div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Row</th><th>Data</th><th>Problem</th></tr></thead>
        <tbody>@foreach(array_slice($errors2, 0, 200) as $er)<tr><td>{{ $er['row'] }}</td><td class="small">{{ \Illuminate\Support\Str::limit(implode(' · ', array_filter($er['data'])), 90) }}</td><td class="small text-danger">{{ implode(' ', $er['messages']) }}</td></tr>@endforeach</tbody>
    </table></div>
</div>
@endif

@if($valid)
<form method="POST" action="{{ route('data.confirm', $token) }}" class="card">
    @csrf
    <div class="card-header">Ready to import ({{ count($valid) }})</div>
    <div class="table-responsive" style="max-height:420px"><table class="table table-sm mb-0">
        @if($import['type'] === 'students')
            <thead><tr><th>Row</th><th>Name</th><th>Class</th><th>Gender</th><th>DOB</th><th>Father / Mobile</th><th>Adm. No</th></tr></thead>
            <tbody>@foreach(array_slice($valid, 0, 300) as $r)<tr><td>{{ $r['_row'] }}</td><td>{{ $r['first_name'] }} {{ $r['last_name'] }}</td><td>{{ $r['_class'] }}</td><td>{{ ucfirst($r['gender']) }}</td><td>{{ \Carbon\Carbon::parse($r['dob'])->format('d M Y') }}</td><td>{{ $r['father_name'] }} · {{ $r['father_phone'] }}</td><td>{{ $r['admission_no'] ?: 'auto' }}</td></tr>@endforeach</tbody>
        @elseif($import['type'] === 'staff')
            <thead><tr><th>Row</th><th>Name</th><th>Type</th><th>Designation</th><th>Mobile</th><th>Joined</th><th>Basic</th></tr></thead>
            <tbody>@foreach(array_slice($valid, 0, 300) as $r)<tr><td>{{ $r['_row'] }}</td><td>{{ $r['name'] }}</td><td>{{ \App\Models\Hr\Employee::TYPES[$r['type']] }}</td><td>{{ $r['designation'] }}</td><td>{{ $r['phone'] }}</td><td>{{ $r['joining_date'] }}</td><td>{{ isset($r['basic']) ? inr($r['basic']) : '' }}</td></tr>@endforeach</tbody>
        @else
            <thead><tr><th>Row</th><th>Student</th><th>Fee</th><th>Due date</th><th class="text-end">Amount</th></tr></thead>
            <tbody>@foreach(array_slice($valid, 0, 300) as $r)<tr><td>{{ $r['_row'] }}</td><td>{{ $r['_student'] }}</td><td>{{ $r['title'] }}</td><td>{{ \Carbon\Carbon::parse($r['due_date'])->format('d M Y') }}</td><td class="text-end">{{ inr($r['amount']) }}</td></tr>@endforeach</tbody>
        @endif
    </table></div>
    <div class="card-body border-top">
        @if($import['type'] === 'students')
            <div class="row g-2">
                <div class="col-md-6"><label class="form-label small mb-0">Status of these students</label>
                    <select name="status" class="form-select form-select-sm"><option value="active">Admitted (active) — they get a Student ID now</option><option value="pending">Pending admission — confirmed when the admission fee is paid</option></select></div>
                <div class="col-md-6 small pt-3">
                    <div class="form-check"><input type="checkbox" name="fees" value="1" id="opFees" class="form-check-input" checked><label for="opFees" class="form-check-label">Create fee dues from the fee structure <span class="text-muted">(from the admission date; if empty, from this month)</span></label></div>
                    <div class="form-check"><input type="checkbox" name="one_time" value="1" id="opOne" class="form-check-input"><label for="opOne" class="form-check-label">Include one-time fees (admission fee) — tick only for new admissions</label></div>
                </div>
            </div>
        @elseif($import['type'] === 'dues')
            <p class="small text-muted mb-0">Each row becomes a pending fee of the student. Accounts can collect it from Collect Fee.</p>
        @endif
    </div>
    <div class="card-footer d-flex gap-2"><a href="{{ route('data.index') }}" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-success ms-auto"><i class="bi bi-check2-circle"></i> Import {{ count($valid) }} record(s)</button></div>
</form>
@else
    <a href="{{ route('data.index') }}" class="btn btn-outline-secondary">Back</a>
@endif
@endsection
