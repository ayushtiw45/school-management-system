@extends('layouts.app')
@section('title', 'Transport · '.$student->full_name)
@section('content')
<div class="card" style="max-width:760px">
    <div class="card-header">{{ $student->full_name }} <span class="text-muted small">· {{ $student->class_label }} · {{ $student->student_code ?: $student->admission_no }}</span></div>
    <div class="card-body">
        @if($student->transportRoute)
            <div class="alert alert-light border">Now on <strong>{{ $student->transportRoute->label() }}</strong>@if($student->routeStop), stop {{ $student->routeStop->name }}@endif · {{ inr($student->transport_fee) }}/month since {{ $student->transport_from?->format('d M Y') }}</div>
        @endif
        <form method="POST" action="{{ route('transport.student.update', $student) }}" class="row g-3">
            @csrf @method('PUT')
            <div class="col-md-6"><label class="form-label required">Route</label>
                <select name="transport_route_id" id="trRoute" class="form-select" required><option value="">Choose…</option>
                    @foreach($routes as $r)<option value="{{ $r->id }}" data-fee="{{ (float) $r->monthly_fee }}" @selected(old('transport_route_id', $student->transport_route_id) == $r->id)>{{ $r->label() }} ({{ $r->students_count }}/{{ $r->seatLimit() ?: '–' }})</option>@endforeach
                </select></div>
            <div class="col-md-6"><label class="form-label">Stop</label>
                <select name="route_stop_id" id="trStop" class="form-select"><option value="">—</option>
                    @foreach($routes as $r)@foreach($r->stops as $s)<option value="{{ $s->id }}" data-route="{{ $r->id }}" data-fee="{{ $s->monthly_fee }}" @selected(old('route_stop_id', $student->route_stop_id) == $s->id)>{{ $s->name }}{{ $s->pickup_time ? ' · '.substr($s->pickup_time, 0, 5) : '' }}</option>@endforeach @endforeach
                </select></div>
            <div class="col-md-6"><label class="form-label">Monthly fee (₹)</label><input type="number" step="0.01" min="0" name="transport_fee" id="trFee" value="{{ old('transport_fee', $student->transport_fee) }}" class="form-control"><div class="form-text">Filled from the stop / route; change it for a special rate.</div></div>
            <div class="col-md-6"><label class="form-label required">From</label><input type="date" name="from" value="{{ old('from', now()->startOfMonth()->format('Y-m-d')) }}" class="form-control" required><div class="form-text">Monthly dues are created from this month to the end of the session. Unpaid future dues of an earlier route are replaced.</div></div>
            <div class="col-12 d-flex gap-2"><a href="{{ route('students.show', $student) }}" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-primary ms-auto">Save transport</button></div>
        </form>
        @if($student->transportRoute)
            <hr>
            <form method="POST" action="{{ route('transport.student.update', $student) }}" class="d-flex gap-2 align-items-end" data-confirm="Stop transport for this student? Unpaid transport dues from this date are removed.">
                @csrf @method('PUT')<input type="hidden" name="remove" value="1">
                <div><label class="form-label small mb-0">Stop transport from</label><input type="date" name="from" value="{{ now()->addMonth()->startOfMonth()->format('Y-m-d') }}" class="form-control form-control-sm"></div>
                <button class="btn btn-sm btn-outline-danger">Stop transport</button>
            </form>
        @endif
    </div>
</div>
@push('scripts')
<script>
(function () {
    const r = document.getElementById('trRoute'), s = document.getElementById('trStop'), f = document.getElementById('trFee');
    function filter() { [...s.options].forEach(o => { if (o.dataset.route) { o.hidden = o.dataset.route !== r.value; if (o.hidden && o.selected) s.value = ''; } }); }
    function fee() { const o = s.selectedOptions[0]; const v = (o && o.dataset.fee) || (r.selectedOptions[0] && r.selectedOptions[0].dataset.fee) || ''; if (v && parseFloat(v) > 0) f.value = v; }
    r.addEventListener('change', () => { filter(); fee(); }); s.addEventListener('change', fee); filter();
})();
</script>
@endpush
@endsection
