@extends('layouts.app')
@section('title', 'Transfer / Leaving: '.$student->full_name)
@section('content')
<div class="card" style="max-width:720px">
    <div class="card-body">
        <p class="mb-3">{{ $student->full_name }} · {{ $student->admission_no }} · {{ $student->class_label }}</p>
        @if($due > 0)
            <div class="alert alert-warning">This student has an outstanding balance of <strong>{{ inr($due) }}</strong>. Collect it before issuing the TC, or cancel future fees below.</div>
        @endif
        <form method="POST" action="{{ route('students.leave.store', $student) }}" class="row g-3">
            @csrf
            <div class="col-md-4"><label class="form-label required">Leaving Date</label><input type="date" name="leaving_date" value="{{ old('leaving_date', now()->format('Y-m-d')) }}" class="form-control" required></div>
            <div class="col-md-8">
                <label class="form-label required">Reason</label>
                <input name="leaving_reason" list="reasons" value="{{ old('leaving_reason') }}" class="form-control" required>
                <datalist id="reasons"><option>Transfer to another school</option><option>Family relocation</option><option>Completed Class 12</option><option>Fee default</option><option>Other</option></datalist>
            </div>
            <div class="col-12"><label class="form-label">Remarks</label><textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $student->remarks) }}</textarea></div>
            <div class="col-12 form-check ms-2">
                <input type="checkbox" class="form-check-input" name="cancel_future_fees" value="1" id="cff" checked>
                <label class="form-check-label" for="cff">Remove unpaid fees that are due after the leaving date</label>
            </div>
            <div class="col-12 d-flex gap-2">
                <a href="{{ route('students.show', $student) }}" class="btn btn-outline-secondary">Cancel</a>
                <button class="btn btn-danger ms-auto">Mark as Left</button>
            </div>
        </form>
    </div>
</div>
@endsection
