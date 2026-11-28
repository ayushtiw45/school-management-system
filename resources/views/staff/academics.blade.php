@extends('layouts.app')
@section('title', 'Subjects & Class Teachers')
@section('content')
@php($can = auth()->user()->canManageStaff())
<div class="row g-3">
    <div class="col-lg-7">
        <form method="POST" action="{{ route('staff.classteachers') }}" class="card mb-3">@csrf
            <div class="card-header">Class teachers</div>
            <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                <tbody>
                @foreach($classes as $c)@foreach($c->sections as $sec)
                    <tr><td style="width:40%">{{ $c->name }} – {{ $sec->name }}</td>
                        <td><select name="ct[{{ $sec->id }}]" class="form-select form-select-sm"><option value="">— not set —</option>@foreach($teachers as $t)<option value="{{ $t->id }}" @selected($sec->class_teacher_id === $t->id)>{{ $t->name }}</option>@endforeach</select></td></tr>
                @endforeach @endforeach
                </tbody>
            </table></div>
            @if($can)<div class="card-footer text-end"><button class="btn btn-sm btn-primary">Save class teachers</button></div>@endif
        </form>
        <div class="card">
            <div class="card-header">Who teaches what</div>
            @if($can)
            <form method="POST" action="{{ route('staff.assignments.store') }}" class="card-body border-bottom row g-2">@csrf
                <div class="col-md-4"><select name="employee_id" class="form-select form-select-sm" required><option value="">Teacher…</option>@foreach($teachers as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><select name="subject_id" class="form-select form-select-sm" required><option value="">Subject…</option>@foreach($subjects as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><select name="school_class_id" id="asClass" class="form-select form-select-sm" required><option value="">Class…</option>@foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                <div class="col-md-2"><input type="number" name="periods_per_week" min="1" class="form-control form-control-sm" placeholder="Periods/wk"></div>
                <div class="col-md-10 small" id="asSections">@foreach($classes as $c)<span class="as-sec d-none" data-class="{{ $c->id }}">@foreach($c->sections as $sec)<label class="me-2"><input type="checkbox" name="section_ids[]" value="{{ $sec->id }}" class="form-check-input"> {{ $sec->name }}</label>@endforeach</span>@endforeach <span class="text-muted">No section ticked = all sections</span></div>
                <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Assign</button></div>
            </form>
            @endif
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>Class</th><th>Subject</th><th>Teacher</th><th>Periods/wk</th><th></th></tr></thead>
                <tbody>
                @forelse($assignments as $a)
                    <tr><td>{{ $a->schoolClass?->name }} {{ $a->section?->name ?? '(all)' }}</td><td>{{ $a->subject?->name }}</td><td><a href="{{ route('staff.employees.show', $a->employee) }}">{{ $a->employee?->name }}</a></td><td>{{ $a->periods_per_week }}</td>
                        <td class="text-end">@if($can)<form method="POST" action="{{ route('staff.assignments.destroy', $a) }}" data-confirm="Remove?">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-x-lg"></i></button></form>@endif</td></tr>
                @empty<tr><td colspan="5" class="text-muted text-center py-3">Nothing assigned yet. The timetable suggests these teachers automatically.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Subjects</div>
            <ul class="list-group list-group-flush">
                @foreach($subjects as $s)
                    <li class="list-group-item d-flex align-items-center py-1">{{ $s->name }} @if($s->code)<span class="small text-muted ms-1">{{ $s->code }}</span>@endif <span class="badge text-bg-light border ms-2">{{ $s->teachers_count }}</span>
                        @if($can)<form method="POST" action="{{ route('staff.subjects.destroy', $s) }}" class="ms-auto" data-confirm="Remove this subject? It is also removed from the timetable.">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-x-lg"></i></button></form>@endif</li>
                @endforeach
            </ul>
            @if($can)
            <form method="POST" action="{{ route('staff.subjects.store') }}" class="card-footer d-flex gap-1">@csrf
                <input name="name" class="form-control form-control-sm" placeholder="New subject" required><input name="code" class="form-control form-control-sm" style="max-width:90px" placeholder="Code"><button class="btn btn-sm btn-primary">Add</button>
            </form>
            @endif
        </div>
    </div>
</div>
@push('scripts')
<script>
(function () { const c = document.getElementById('asClass'); if (!c) return; c.addEventListener('change', () => document.querySelectorAll('.as-sec').forEach(s => { s.classList.toggle('d-none', s.dataset.class !== c.value); s.querySelectorAll('input').forEach(i => i.checked = false); })); })();
</script>
@endpush
@endsection
