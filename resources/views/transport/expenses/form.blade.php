@extends('layouts.app')
@section('title', $expense->exists ? 'Edit Transport Expense' : 'Add Transport Expense')
@section('content')
@php($v = fn ($f) => old($f, $expense->$f instanceof \Carbon\Carbon ? $expense->$f->format('Y-m-d') : $expense->$f))
<form method="POST" action="{{ $expense->exists ? route('transport.expenses.update', $expense) : route('transport.expenses.store') }}" enctype="multipart/form-data" class="card" style="max-width:760px">
    @csrf @if($expense->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label required">Category</label><select name="category" class="form-select">@foreach(\App\Models\Transport\TransportExpense::CATEGORIES as $k => $l)<option value="{{ $k }}" @selected($v('category') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Vehicle</label><select name="vehicle_id" class="form-select"><option value="">General (all buses)</option>@foreach($vehicles as $x)<option value="{{ $x->id }}" @selected($v('vehicle_id') == $x->id)>{{ $x->label() }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label required">Date</label><input type="date" name="expense_date" value="{{ $v('expense_date') }}" max="{{ now()->format('Y-m-d') }}" class="form-control" required></div>
        <div class="col-md-8"><label class="form-label required">Details</label><input name="title" value="{{ $v('title') }}" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label required">Amount (₹)</label><input type="number" step="0.01" min="0.01" name="amount" value="{{ $v('amount') }}" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label required">Mode</label><select name="method" class="form-select">@foreach(\App\Models\Payment::METHODS as $k => $l)<option value="{{ $k }}" @selected($v('method') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-8"><label class="form-label">Note</label><input name="notes" value="{{ $v('notes') }}" class="form-control"></div>
        <div class="col-12"><label class="form-label">Bill / receipt</label><input type="file" name="bill" accept=".pdf,image/*" class="form-control">@if($expense->bill_path)<div class="form-text"><a href="{{ route('transport.expenses.bill', $expense) }}" target="_blank">View current bill</a></div>@endif</div>
    </div>
    <div class="card-footer d-flex gap-2"><a href="{{ route('transport.expenses.index') }}" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-primary ms-auto">Save</button></div>
</form>
@endsection
