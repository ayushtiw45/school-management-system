@extends('layouts.app')
@section('title', 'Salary · '.$p->employee->name.' · '.$p->monthLabel())
@section('content')
@php($E = \App\Models\Hr\Employee::class)
<form method="POST" action="{{ route('staff.payroll.update', $p) }}" class="card" style="max-width:820px">@csrf @method('PUT')
    <div class="card-body row g-3">
        <div class="col-md-3"><label class="form-label">Working days</label><input type="number" step="0.5" name="working_days" value="{{ $p->working_days + 0 }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Loss of pay days</label><input type="number" step="0.5" name="lop_days" value="{{ $p->lop_days + 0 }}" class="form-control"></div>
        <div class="col-12 fw-semibold mt-2">Earnings</div>
        @foreach([...array_keys($E::EARNINGS), 'bonus'] as $k)<div class="col-md-3"><label class="form-label small">{{ $E::EARNINGS[$k] ?? 'Bonus / arrears' }}</label><input type="number" step="0.01" min="0" name="{{ $k }}" value="{{ $p->$k + 0 }}" class="form-control"></div>@endforeach
        <div class="col-12 fw-semibold mt-2">Deductions</div>
        @foreach([...array_keys($E::DEDUCTIONS), 'advance_deduction', 'lop_deduction'] as $k)<div class="col-md-3"><label class="form-label small">{{ $E::DEDUCTIONS[$k] ?? ($k === 'lop_deduction' ? 'Loss of pay' : 'Advance recovery') }}</label><input type="number" step="0.01" min="0" name="{{ $k }}" value="{{ $p->$k + 0 }}" class="form-control"></div>@endforeach
        <div class="col-12"><label class="form-label">Note (shown on slip)</label><input name="note" value="{{ $p->note }}" class="form-control"></div>
    </div>
    <div class="card-footer d-flex gap-2"><span class="align-self-center">Current net: <strong>{{ inr($p->net) }}</strong></span><a href="{{ route('staff.payroll.index', ['month' => $p->month]) }}" class="btn btn-outline-secondary ms-auto">Cancel</a><button class="btn btn-primary">Save</button></div>
</form>
@endsection
