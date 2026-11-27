@extends('layouts.app')
@section('title', $expense->exists ? 'Edit Expense' : 'Add Expense')
@section('content')
@php($salaryIds = $categories->where('is_salary', true)->pluck('id')->all())
<form method="POST" action="{{ $expense->exists ? route('expenses.update', $expense) : route('expenses.store') }}" enctype="multipart/form-data" class="card" style="max-width:760px">
    @csrf @if($expense->exists) @method('PUT') @endif
    <div class="card-body row g-3">
        <div class="col-md-6">
            <label class="form-label required">Category</label>
            <select name="expense_category_id" id="expCat" class="form-select" required>
                <option value="">Select</option>
                @foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('expense_category_id', $expense->expense_category_id) == $c->id)>{{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3"><label class="form-label required">Date</label><input type="date" name="expense_date" value="{{ old('expense_date', $expense->expense_date?->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label required">Amount (₹)</label><input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', $expense->amount) }}" class="form-control" required></div>
        <div class="col-md-6"><label class="form-label required">Details</label><input name="title" value="{{ old('title', $expense->title) }}" class="form-control" placeholder="e.g. Electricity bill for October" required></div>
        <div class="col-md-6"><label class="form-label" id="paidToLabel">Paid To</label><input name="paid_to" value="{{ old('paid_to', $expense->paid_to) }}" class="form-control" placeholder="Vendor / staff name"></div>
        <div class="col-md-4 salary-only"><label class="form-label required">Salary Month</label><input type="month" name="salary_month" value="{{ old('salary_month', $expense->salary_month ?? now()->format('Y-m')) }}" class="form-control"></div>
        <div class="col-md-4">
            <label class="form-label required">Payment Mode</label>
            <select name="payment_method" class="form-select">@foreach(\App\Models\Payment::METHODS as $k => $l)<option value="{{ $k }}" @selected(old('payment_method', $expense->payment_method) === $k)>{{ $l }}</option>@endforeach</select>
        </div>
        <div class="col-md-4"><label class="form-label">Reference / Cheque No.</label><input name="reference_no" value="{{ old('reference_no', $expense->reference_no) }}" class="form-control"></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" rows="2" class="form-control">{{ old('notes', $expense->notes) }}</textarea></div>
        <div class="col-md-8">
            <label class="form-label">Bill / Voucher</label><input type="file" name="bill" accept=".pdf,image/*" class="form-control">
            <div class="form-text">PDF/JPG/PNG, max 4 MB. @if($expense->bill_path)<a href="{{ route('expenses.bill', $expense) }}" target="_blank">View current bill</a> (uploading replaces it).@endif</div>
        </div>
    </div>
    <div class="card-footer d-flex gap-2">
        <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-primary ms-auto">Save Expense</button>
    </div>
</form>
@push('scripts')
<script>
    (function () {
        const salary = @json($salaryIds), sel = document.getElementById('expCat');
        function toggle() {
            const isSalary = salary.includes(parseInt(sel.value));
            document.querySelectorAll('.salary-only').forEach(e => e.classList.toggle('d-none', !isSalary));
            const label = document.getElementById('paidToLabel');
            label.textContent = isSalary ? 'Staff Name' : 'Paid To';
            label.classList.toggle('required', isSalary);
        }
        sel.addEventListener('change', toggle); toggle();
    })();
</script>
@endpush
@endsection
