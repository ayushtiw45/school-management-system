@extends('layouts.app')
@section('title', 'All Students')
@section('content')
@php($canEdit = auth()->user()->canAdmit())
<div class="card">
    <div class="card-header">
        <form class="row g-2 align-items-center">
            <div class="col-md-3"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Name, admission no, father, mobile"></div>
            <div class="col-6 col-md-2">
                <select name="class_id" id="fClass" class="form-select form-select-sm">
                    <option value="">All classes</option>
                    @foreach($classes as $c)<option value="{{ $c->id }}" @selected(request('class_id') == $c->id)>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="section_id" class="form-select form-select-sm" data-sections-for="#fClass" data-all-label="All sections">
                    <option value="">All sections</option>
                    @foreach($classes->firstWhere('id', request('class_id'))?->sections ?? [] as $sec)<option value="{{ $sec->id }}" @selected(request('section_id') == $sec->id)>{{ $sec->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm">
                    @foreach(['active' => 'Active', 'inactive' => 'Inactive', 'left' => 'Left / TC', 'pending' => 'Pending', 'all' => 'All statuses'] as $k => $l)
                        <option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-1">
                <select name="gender" class="form-select form-select-sm">
                    <option value="">All</option><option value="male" @selected(request('gender') === 'male')>Boys</option><option value="female" @selected(request('gender') === 'female')>Girls</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button class="btn btn-sm btn-primary flex-fill">Filter</button>
                @if(request('class_id'))<a class="btn btn-sm btn-outline-secondary" title="Print ID cards" href="{{ route('students.idcards', ['class_id' => request('class_id'), 'section_id' => request('section_id')]) }}"><i class="bi bi-person-badge"></i></a>@endif
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr><th></th><th>Student ID / Adm. No</th><th>Student</th><th>Class</th><th>Roll</th><th>Father / Mobile</th><th>Status</th><th class="text-end"></th></tr></thead>
            <tbody>
            @forelse($students as $s)
                <tr>
                    <td style="width:46px">@include('students.avatar', ['s' => $s])</td>
                    <td><span class="fw-semibold">{{ $s->student_code ?: '—' }}</span><div class="small text-muted">{{ $s->admission_no }}</div></td>
                    <td><a href="{{ route('students.show', $s) }}" class="fw-semibold">{{ $s->full_name }}</a><div class="text-muted small">{{ ucfirst($s->gender) }}</div></td>
                    <td>{{ $s->class_label }}</td>
                    <td>{{ $s->roll_no }}</td>
                    <td>{{ $s->father_name }}<div class="text-muted small">{{ $s->father_phone }}</div></td>
                    <td>@include('students.status-badge', ['status' => $s->status])</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('students.show', $s) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        @if($canEdit)<a href="{{ route('students.edit', $s) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No students found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex align-items-center">
        <span class="text-muted small">{{ $students->total() }} student(s)</span>
        <div class="ms-auto">{{ $students->links('pagination::bootstrap-5') }}</div>
    </div>
</div>
@endsection
