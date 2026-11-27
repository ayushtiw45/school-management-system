@extends('layouts.app')
@section('title', 'Pending Fees')
@section('content')
<div class="card">
    <div class="card-header">
        <form class="row g-2 align-items-center">
            <div class="col-md-3"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search student"></div>
            <div class="col-6 col-md-2">
                <select name="class_id" id="pc" class="form-select form-select-sm"><option value="">All classes</option>@foreach($classes as $c)<option value="{{ $c->id }}" @selected(request('class_id') == $c->id)>{{ $c->name }}</option>@endforeach</select>
            </div>
            <div class="col-6 col-md-2">
                <select name="section_id" class="form-select form-select-sm" data-sections-for="#pc" data-all-label="All sections"><option value="">All sections</option>@foreach($classes->firstWhere('id', request('class_id'))?->sections ?? [] as $sec)<option value="{{ $sec->id }}" @selected(request('section_id') == $sec->id)>{{ $sec->name }}</option>@endforeach</select>
            </div>
            <div class="col-md-3 d-flex align-items-center gap-1"><label class="small text-muted text-nowrap">Due till</label><input type="date" name="upto" value="{{ $upto->format('Y-m-d') }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Filter</button></div>
        </form>
    </div>
    <div class="px-3 py-2 bg-danger-subtle d-flex"><span>Total pending (till {{ $upto->format('d M Y') }})</span><strong class="ms-auto">{{ inr($total) }}</strong></div>
    <form method="POST" action="{{ route('notifications.remind') }}" id="remindForm">
    @csrf
    <input type="hidden" name="upto" value="{{ $upto->format('Y-m-d') }}">
    <input type="hidden" name="class_id" value="{{ request('class_id') }}">
    <input type="hidden" name="section_id" value="{{ request('section_id') }}">
    <div class="px-3 py-2 border-bottom d-flex flex-wrap gap-2 align-items-center">
        <span class="small text-muted">Send fee reminder to</span>
        <select name="channel" class="form-select form-select-sm" style="width:auto"><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option><option value="both">SMS + WhatsApp</option></select>
        <button class="btn btn-sm btn-outline-primary" onclick="return confirm(document.querySelectorAll('.rpick:checked').length ? 'Send reminder to the selected students?' : 'No student selected: send reminder to ALL students in this list (all pages)?')"><i class="bi bi-send"></i> Send Reminder</button>
        <span class="small text-muted">(tick students to send only to them)</span>
        <a href="{{ route('reports.show', ['pending', 'upto' => $upto->format('Y-m-d'), 'class_id' => request('class_id'), 'section_id' => request('section_id')]) }}" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-download"></i> Excel / PDF</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr><th style="width:30px"><input type="checkbox" class="form-check-input" onclick="document.querySelectorAll('.rpick').forEach(c => c.checked = this.checked)"></th><th>Adm. No</th><th>Student</th><th>Class</th><th>Father / Mobile</th><th class="text-end">Dues</th><th class="text-end">Pending</th><th></th></tr></thead>
            <tbody>
            @forelse($rows as $s)
                <tr>
                    <td><input type="checkbox" class="form-check-input rpick" name="student_ids[]" value="{{ $s->id }}"></td>
                    <td>{{ $s->admission_no }}</td>
                    <td><a href="{{ route('students.show', $s) }}">{{ $s->full_name }}</a></td>
                    <td>{{ $s->class_label }}</td>
                    <td>{{ $s->father_name }}<div class="small text-muted">{{ $s->parent_phone }}</div></td>
                    <td class="text-end">{{ $s->due_count }}</td>
                    <td class="text-end fw-semibold text-danger">{{ inr($s->due_amount) }}</td>
                    <td class="text-end text-nowrap">
                        @if($s->wa_link)<a href="{{ $s->wa_link }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success" title="Send reminder on WhatsApp (free)"><i class="bi bi-whatsapp"></i></a>@endif
                        <a href="{{ route('payments.create', ['student_id' => $s->id]) }}" class="btn btn-sm btn-primary">Collect</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No pending fees. 🎉</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    </form>
    @if($rows->hasPages())<div class="card-footer">{{ $rows->links('pagination::bootstrap-5') }}</div>@endif
</div>
@endsection
