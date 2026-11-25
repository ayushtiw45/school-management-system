@extends('layouts.app')
@section('title', 'Payments')
@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-center">
            <div class="col-md-3"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Receipt no or student"></div>
            <div class="col-6 col-md-2"><input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="form-control form-control-sm"></div>
            <div class="col-6 col-md-2"><input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="form-control form-control-sm"></div>
            <div class="col-md-3"><select name="method" class="form-select form-select-sm"><option value="">All modes</option>@foreach(\App\Models\Payment::METHODS as $k => $l)<option value="{{ $k }}" @selected(request('method') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Filter</button></div>
        </form>
    </div>
    <div class="card-footer d-flex flex-wrap gap-3 small">
        @foreach($summary as $row)<span>{{ \App\Models\Payment::METHODS[$row->method] ?? $row->method }}: <strong>{{ inr($row->total) }}</strong> ({{ $row->cnt }})</span>@endforeach
        <span class="ms-auto">Total: <strong class="fs-6">{{ inr($summary->sum('total')) }}</strong></span>
    </div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr><th>Receipt No</th><th>Date</th><th>Student</th><th>Class</th><th>Mode</th><th>Received By</th><th class="text-end">Amount</th><th></th></tr></thead>
            <tbody>
            @forelse($payments as $p)
                <tr>
                    <td>{{ $p->receipt_no }}</td>
                    <td>{{ $p->payment_date->format('d M Y') }}</td>
                    <td><a href="{{ route('students.show', $p->student) }}">{{ $p->student->full_name }}</a><div class="small text-muted">{{ $p->student->admission_no }}</div></td>
                    <td>{{ $p->student->class_label }}</td>
                    <td>{{ $p->methodLabel() }}@if($p->reference_no)<div class="small text-muted">{{ $p->reference_no }}</div>@endif</td>
                    <td>{{ $p->receiver?->name }}</td>
                    <td class="text-end fw-semibold">{{ inr($p->amount) }}</td>
                    <td class="text-end"><a href="{{ route('payments.show', $p) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No payments in this period.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($payments->hasPages())<div class="card-footer">{{ $payments->links('pagination::bootstrap-5') }}</div>@endif
</div>
@endsection
