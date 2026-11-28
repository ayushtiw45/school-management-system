@extends('layouts.app')
@section('title', 'Timetable')
@section('content')
@php($can = auth()->user()->canManageStaff())
@php($days = \App\Models\Hr\TimetableEntry::DAYS)
<div class="d-flex flex-wrap gap-2 align-items-center mb-3 no-print">
    <form><select name="section_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width:200px"><option value="">Choose class & section…</option>
        @foreach($classes as $c)@foreach($c->sections as $s)<option value="{{ $s->id }}" @selected($section?->id === $s->id)>{{ $c->name }} – {{ $s->name }}</option>@endforeach @endforeach</select></form>
    <a href="{{ route('staff.timetable.teacher') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-person-video3"></i> Teacher timetable</a>
    <a href="{{ route('staff.substitutions.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left-right"></i> Today's substitutes</a>
    @if($can)<button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#periodsBox"><i class="bi bi-clock"></i> Periods & timings</button>@endif
    @if($section)<button onclick="window.print()" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-printer"></i> Print</button>@endif
</div>

@if($can)
<form method="POST" action="{{ route('staff.periods.save') }}" class="card mb-3 collapse no-print" id="periodsBox">@csrf
    <div class="card-header">Periods</div>
    <div class="card-body">
        @foreach($periods as $p)
            <div class="row g-1 mb-1 align-items-center">
                <div class="col-1"><input type="number" name="periods[{{ $p->id }}][sort_order]" value="{{ $p->sort_order }}" class="form-control form-control-sm"></div>
                <div class="col-3"><input name="periods[{{ $p->id }}][name]" value="{{ $p->name }}" class="form-control form-control-sm"></div>
                <div class="col-2"><input type="time" name="periods[{{ $p->id }}][start_time]" value="{{ substr((string) $p->start_time, 0, 5) }}" class="form-control form-control-sm"></div>
                <div class="col-2"><input type="time" name="periods[{{ $p->id }}][end_time]" value="{{ substr((string) $p->end_time, 0, 5) }}" class="form-control form-control-sm"></div>
                <div class="col-2 small"><label><input type="checkbox" class="form-check-input" name="periods[{{ $p->id }}][is_break]" value="1" @checked($p->is_break)> Break</label></div>
                <div class="col-2 small"><label class="text-danger"><input type="checkbox" class="form-check-input" name="periods[{{ $p->id }}][delete]" value="1"> Delete</label></div>
            </div>
        @endforeach
        <div class="row g-1 mt-2"><div class="col-1"></div>
            <div class="col-3"><input name="new[name]" class="form-control form-control-sm" placeholder="New period"></div>
            <div class="col-2"><input type="time" name="new[start_time]" class="form-control form-control-sm"></div>
            <div class="col-2"><input type="time" name="new[end_time]" class="form-control form-control-sm"></div>
            <div class="col-2 small"><label><input type="checkbox" class="form-check-input" name="new[is_break]" value="1"> Break</label></div></div>
    </div>
    <div class="card-footer text-end"><button class="btn btn-sm btn-primary">Save periods</button></div>
</form>
@endif

@if($section)
    <div class="d-flex align-items-center gap-2 mb-2">
        <h2 class="h5 fw-bold mb-0">{{ $section->schoolClass->name }} – {{ $section->name }}</h2>
        <span class="text-muted small">Class teacher: {{ $section->classTeacher?->name ?? 'not set' }}</span>
        @if($can)<form method="POST" action="{{ route('staff.timetable.copy') }}" class="ms-auto no-print" data-confirm="Copy Monday to the empty periods of the other days?">@csrf<input type="hidden" name="section_id" value="{{ $section->id }}"><input type="hidden" name="from" value="1"><button class="btn btn-sm btn-outline-secondary">Copy Monday to all days</button></form>@endif
    </div>
    <div class="card"><div class="table-responsive"><table class="table table-bordered mb-0 text-center align-middle small">
        <thead class="table-light"><tr><th style="width:90px">Day</th>@foreach($periods as $p)<th class="{{ $p->is_break ? 'text-muted' : '' }}">{{ $p->name }}<div class="fw-normal text-muted" style="font-size:.7rem">{{ $p->timeLabel() }}</div></th>@endforeach</tr></thead>
        <tbody>
        @foreach($days as $d => $dn)
            <tr><th>{{ $dn }}</th>
            @foreach($periods as $p)
                @if($p->is_break)<td class="table-light text-muted">—</td>@continue @endif
                @php($e = $grid[$d.'-'.$p->id] ?? null)
                <td class="tt-cell {{ $can ? 'tt-edit' : '' }}" style="{{ $can ? 'cursor:pointer' : '' }}" data-day="{{ $d }}" data-period="{{ $p->id }}" data-subject="{{ $e?->subject_id }}" data-teacher="{{ $e?->employee_id }}" data-room="{{ $e?->room }}" data-label="{{ $dn }} · {{ $p->name }}">
                    @if($e)<div class="fw-semibold">{{ $e->subject?->name }}</div><div class="text-muted" style="font-size:.72rem">{{ $e->employee?->name }}{{ $e->room ? ' · '.$e->room : '' }}</div>@elseif($can)<span class="text-muted no-print">+</span>@endif
                </td>
            @endforeach</tr>
        @endforeach
        </tbody>
    </table></div></div>
    @if($can)
    <div class="modal fade" id="ttModal" tabindex="-1"><div class="modal-dialog"><form method="POST" action="{{ route('staff.timetable.save') }}" class="modal-content">@csrf
        <input type="hidden" name="section_id" value="{{ $section->id }}"><input type="hidden" name="day" id="ttDay"><input type="hidden" name="period_id" id="ttPeriod">
        <div class="modal-header"><h5 class="modal-title" id="ttLabel"></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-2">
            <div class="col-12"><label class="form-label">Subject</label><select name="subject_id" id="ttSubject" class="form-select"><option value="">— free period —</option>@foreach($subjects as $s)<option value="{{ $s->id }}" data-teacher="{{ $suggest[$s->id] ?? '' }}">{{ $s->name }}</option>@endforeach</select></div>
            <div class="col-8"><label class="form-label">Teacher</label><select name="employee_id" id="ttTeacher" class="form-select"><option value="">—</option>@foreach($teachers as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select><div class="form-text">If the teacher is busy in another class at this time, it is not saved.</div></div>
            <div class="col-4"><label class="form-label">Room</label><input name="room" id="ttRoom" class="form-control"></div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
    </form></div></div>
    @push('scripts')
    <script>
    (function () {
        const m = new bootstrap.Modal(document.getElementById('ttModal')), s = document.getElementById('ttSubject'), t = document.getElementById('ttTeacher');
        document.querySelectorAll('.tt-edit').forEach(c => c.addEventListener('click', () => {
            ttDay.value = c.dataset.day; ttPeriod.value = c.dataset.period; ttLabel.textContent = c.dataset.label;
            s.value = c.dataset.subject || ''; t.value = c.dataset.teacher || ''; ttRoom.value = c.dataset.room || ''; m.show();
        }));
        s.addEventListener('change', () => { const sug = s.selectedOptions[0].dataset.teacher; if (sug && !t.value) t.value = sug; });
    })();
    </script>
    @endpush
    @endif
@else
    <div class="card card-body text-muted">Choose a class and section to see or make its timetable. Set <a href="{{ route('staff.academics') }}">who teaches which subject</a> first and the right teacher is suggested automatically.</div>
@endif
@endsection
