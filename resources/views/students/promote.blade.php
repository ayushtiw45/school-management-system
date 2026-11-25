@extends('layouts.app')
@section('title', 'Promote Students')
@section('content')
<div class="card mb-3">
    <div class="card-header">Step 1 · Select current class</div>
    <div class="card-body">
        <form class="row g-2">
            <div class="col-md-4">
                <select name="from_class_id" id="pFrom" class="form-select" required>
                    <option value="">From class</option>
                    @foreach($classes as $c)<option value="{{ $c->id }}" @selected(request('from_class_id') == $c->id)>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-4">
                <select name="from_section_id" class="form-select" data-sections-for="#pFrom" data-all-label="All sections">
                    <option value="">All sections</option>
                    @foreach($classes->firstWhere('id', request('from_class_id'))?->sections ?? [] as $sec)<option value="{{ $sec->id }}" @selected(request('from_section_id') == $sec->id)>{{ $sec->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Load Students</button></div>
        </form>
    </div>
</div>

@if(request('from_class_id'))
    <form method="POST" action="{{ route('students.promote.store') }}" class="card">
        @csrf
        <div class="card-header">Step 2 · Choose students and the new class</div>
        <div class="card-body row g-2 border-bottom">
            @php($fromIdx = $classes->search(fn ($c) => $c->id == request('from_class_id')))
            @php($next = $fromIdx !== false ? $classes->get($fromIdx + 1) : null)
            <div class="col-md-3">
                <label class="form-label required">New Session</label>
                <select name="to_session_id" class="form-select" required>@foreach($sessions as $ses)<option value="{{ $ses->id }}">{{ $ses->name }}</option>@endforeach</select>
            </div>
            <div class="col-md-3">
                <label class="form-label required">Promote to Class</label>
                <select name="to_class_id" id="pTo" class="form-select" required>
                    @foreach($classes as $c)<option value="{{ $c->id }}" @selected($next?->id === $c->id)>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Section</label>
                <select name="to_section_id" class="form-select" data-sections-for="#pTo">
                    <option value="">Select section</option>
                    @foreach($next?->sections ?? [] as $sec)<option value="{{ $sec->id }}">{{ $sec->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check"><input type="checkbox" class="form-check-input" name="generate_fees" value="1" id="gf" checked><label class="form-check-label small" for="gf">Assign new session fees</label></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead><tr><th style="width:36px"><input type="checkbox" class="form-check-input" checked onclick="document.querySelectorAll('.pick').forEach(c => c.checked = this.checked)"></th><th>Adm. No</th><th>Student</th><th>Section</th><th class="text-end">Pending Dues</th></tr></thead>
                <tbody>
                @forelse($students as $s)
                    <tr>
                        <td><input type="checkbox" class="form-check-input pick" name="student_ids[]" value="{{ $s->id }}" checked></td>
                        <td>{{ $s->admission_no }}</td>
                        <td>{{ $s->full_name }}</td>
                        <td>{{ $s->section?->name }}</td>
                        <td class="text-end {{ $s->due > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">{{ inr($s->due) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No active students in this class.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($students->count())
            <div class="card-footer d-flex"><span class="small text-muted">Pending dues stay on the student's account and can still be collected.</span><button class="btn btn-primary ms-auto" onclick="return confirm('Promote the selected students?')"><i class="bi bi-arrow-up-circle"></i> Promote Selected</button></div>
        @endif
    </form>
@endif
@endsection
