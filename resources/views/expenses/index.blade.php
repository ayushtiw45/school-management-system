@extends('layouts.app')
@section('title', 'Expenses')
@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-center">
            <div class="col-md-3"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search details or paid to"></div>
            <div class="col-6 col-md-2"><input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="form-control form-control-sm"></div>
            <div class="col-6 col-md-2"><input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="form-control form-control-sm"></div>
            <div class="col-md-2"><select name="category_id" class="form-select form-select-sm"><option value="">All categories</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
            <div class="col-auto"><button class="btn btn-sm btn-primary">Filter</button></div>
            <div class="col-auto ms-auto"><a href="{{ route('expenses.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus"></i> Add Expense</a></div>
        </form>
    </div>
    <div class="card-footer d-flex flex-wrap gap-3 small">
        @foreach($categories as $c)@if($byCategory->has($c->id))<span>{{ $c->name }}: <strong>{{ inr($byCategory[$c->id]) }}</strong></span>@endif @endforeach
        <span class="ms-auto">Total: <strong class="fs-6 text-danger">{{ inr($total) }}</strong></span>
    </div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr><th>Date</th><th>Category</th><th>Details</th><th>Paid To</th><th>Mode</th><th class="text-end">Amount</th><th></th></tr></thead>
            <tbody>
            @forelse($expenses as $e)
                <tr>
                    <td class="text-nowrap">{{ $e->expense_date->format('d M Y') }}</td>
                    <td>{{ $e->category->name }}</td>
                    <td>{{ $e->title }} @if($e->salary_month)<span class="badge text-bg-light border">{{ \Carbon\Carbon::parse($e->salary_month.'-01')->format('M Y') }}</span>@endif
                        @if($e->notes)<div class="small text-muted">{{ \Illuminate\Support\Str::limit($e->notes, 80) }}</div>@endif</td>
                    <td>{{ $e->paid_to }}</td>
                    <td>{{ $e->methodLabel() }}@if($e->reference_no)<div class="small text-muted">{{ $e->reference_no }}</div>@endif</td>
                    <td class="text-end fw-semibold">{{ inr($e->amount) }}</td>
                    <td class="text-end text-nowrap">
                        @if($e->bill_path)<a href="{{ route('expenses.bill', $e) }}" target="_blank" class="btn btn-sm btn-link" title="Bill"><i class="bi bi-paperclip"></i></a>@endif
                        <a href="{{ route('expenses.edit', $e) }}" class="btn btn-sm btn-link"><i class="bi bi-pencil"></i></a>
                        <form method="POST" action="{{ route('expenses.destroy', $e) }}" class="d-inline" data-confirm="Delete this expense?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger"><i class="bi bi-trash"></i></button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No expenses in this period.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($expenses->hasPages())<div class="card-footer">{{ $expenses->links('pagination::bootstrap-5') }}</div>@endif
</div>
@endsection
