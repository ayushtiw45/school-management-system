@extends('layouts.app')
@section('title', 'Payroll · '.$start->format('F Y'))
@section('content')
@php($can = auth()->user()->canManagePayroll())
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <form><input type="month" name="month" value="{{ $month }}" max="{{ now()->format('Y-m') }}" class="form-control form-control-sm" onchange="this.form.submit()"></form>
    <a href="{{ route('staff.advances.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-cash"></i> Salary advances</a>
    <a href="{{ route('staff.reports.show', ['salary', 'month' => $month]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-file-earmark-spreadsheet"></i> Salary register</a>
    @if($payrolls->isNotEmpty())<a href="{{ route('staff.payroll.slips', ['month' => $month]) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Print all slips</a>@endif
    @if($can && $missing->isNotEmpty())
        <form method="POST" action="{{ route('staff.payroll.generate') }}" class="ms-auto">@csrf<input type="hidden" name="month" value="{{ $month }}"><button class="btn btn-sm btn-primary"><i class="bi bi-magic"></i> Make salary for {{ $missing->count() }} staff</button></form>
    @endif
</div>
<div class="row g-2 mb-3">
    @foreach([['Gross', $payrolls->sum('gross')], ['Deductions', $payrolls->sum('total_deduction')], ['Net pay', $payrolls->sum('net')], ['Paid', $payrolls->where('status', 'paid')->sum('net')], ['To pay', $payrolls->where('status', 'draft')->sum('net')]] as [$l, $v])
        <div class="col-6 col-md"><div class="card p-2"><div class="small text-muted">{{ $l }}</div><div class="fw-bold">{{ inr($v) }}</div></div></div>
    @endforeach
</div>
<form method="POST" action="{{ route('staff.payroll.pay') }}" class="card" id="payForm">@csrf
    <div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
        <thead><tr><th style="width:30px"><input type="checkbox" class="form-check-input" onclick="document.querySelectorAll('.pick').forEach(c => c.checked = this.checked)"></th><th>Name</th><th>Days</th><th class="text-end">Gross</th><th class="text-end">Loss of pay</th><th class="text-end">Other deductions</th><th class="text-end">Net</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($payrolls as $p)
            <tr>
                <td>@if($p->status === 'draft')<input type="checkbox" name="ids[]" value="{{ $p->id }}" class="form-check-input pick">@endif</td>
                <td><a href="{{ route('staff.employees.show', $p->employee) }}">{{ $p->employee->name }}</a><div class="small text-muted">{{ $p->employee->designation }}</div></td>
                <td class="small">{{ $p->paid_days + 0 }}/{{ $p->working_days + 0 }}@if((float) $p->lop_days) <span class="text-danger">({{ $p->lop_days + 0 }} LOP)</span>@endif</td>
                <td class="text-end">{{ inr($p->gross, false) }}</td><td class="text-end">{{ (float) $p->lop_deduction ? inr($p->lop_deduction, false) : '—' }}</td>
                <td class="text-end">{{ inr($p->total_deduction - $p->lop_deduction, false) }}</td><td class="text-end fw-semibold">{{ inr($p->net, false) }}</td>
                <td>@if($p->status === 'paid')<span class="badge text-bg-success">Paid {{ $p->paid_on->format('d M') }}</span>@else<span class="badge text-bg-warning">Draft</span>@endif</td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('staff.payroll.slip', $p) }}" target="_blank" class="btn btn-sm btn-outline-secondary">Slip</a>
                    @if($can && $p->status === 'draft')
                        <a href="{{ route('staff.payroll.edit', $p) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        <button form="rf{{ $p->id }}" class="btn btn-sm btn-link p-0" title="Recalculate from attendance"><i class="bi bi-arrow-clockwise"></i></button>
                        <button form="dl{{ $p->id }}" class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button>
                    @endif
                    @if($p->status === 'paid' && auth()->user()->isAdmin())<button form="up{{ $p->id }}" class="btn btn-sm btn-link text-danger p-0">Undo</button>@endif
                </td>
            </tr>
        @empty<tr><td colspan="9" class="text-muted text-center py-4">No salary made for this month yet. Click "Make salary" — it uses each person's salary structure and attendance (absent days = loss of pay).</td></tr>@endforelse
        </tbody>
    </table></div>
    @if($can && $payrolls->where('status', 'draft')->isNotEmpty())
    <div class="card-footer d-flex flex-wrap gap-2 align-items-end">
        <div><label class="form-label small mb-0">Paid on</label><input type="date" name="paid_on" value="{{ now()->format('Y-m-d') }}" class="form-control form-control-sm"></div>
        <div><label class="form-label small mb-0">Mode</label><select name="method" class="form-select form-select-sm">@foreach(\App\Models\Payment::METHODS as $k => $l)<option value="{{ $k }}" @selected($k === 'bank')>{{ $l }}</option>@endforeach</select></div>
        <div><label class="form-label small mb-0">Reference</label><input name="reference_no" class="form-control form-control-sm" placeholder="Cheque / UTR"></div>
        <div class="small text-muted">Paid salary is added to Expenses → Staff Salary automatically.</div>
        <button class="btn btn-success ms-auto">Mark ticked as paid</button>
    </div>
    @endif
</form>
@foreach($payrolls as $p)
    @if($p->status === 'draft')
        <form method="POST" action="{{ route('staff.payroll.refresh', $p) }}" id="rf{{ $p->id }}">@csrf</form>
        <form method="POST" action="{{ route('staff.payroll.destroy', $p) }}" id="dl{{ $p->id }}" data-confirm="Delete this draft?">@csrf @method('DELETE')</form>
    @else
        <form method="POST" action="{{ route('staff.payroll.unpay', $p) }}" id="up{{ $p->id }}" data-confirm="Undo this salary payment? The expense entry is removed.">@csrf</form>
    @endif
@endforeach
@if($missing->isNotEmpty())<p class="small text-muted mt-2">Not yet made: {{ $missing->pluck('name')->implode(', ') }}. Staff without a salary structure are skipped — set it on their profile.</p>@endif
@endsection
