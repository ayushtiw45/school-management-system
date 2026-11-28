@extends('layouts.app')
@section('title', 'Transport Expenses')
@section('content')
@php($C = \App\Models\Transport\TransportExpense::CATEGORIES)
@php($can = auth()->user()->canManageTransport())
<div class="d-flex flex-wrap gap-2 mb-2">
    <span class="small text-muted align-self-center">Fuel, maintenance and salary have their own pages. Use this for EMI, rent, insurance, road tax, toll and other costs.</span>
    @if($can)
        <form method="POST" action="{{ route('transport.expenses.recurring') }}" class="ms-auto d-flex gap-1">@csrf<input type="month" name="month" value="{{ now()->format('Y-m') }}" class="form-control form-control-sm"><button class="btn btn-sm btn-outline-primary text-nowrap" title="Adds each vehicle's monthly EMI and rent">Add EMI & rent</button></form>
        <a href="{{ route('transport.expenses.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Add expense</a>
    @endif
</div>
@include('transport.partials.range', ['extra' => '<div class="col-6 col-md-2"><label class="form-label small mb-0">Category</label><select name="category" class="form-select form-select-sm"><option value="">All</option>'.collect($C)->map(fn ($l, $k) => '<option value="'.$k.'" '.(request('category') === $k ? 'selected' : '').'>'.e($l).'</option>')->implode('').'</select></div>'])
<div class="row g-2 mb-3">
    <div class="col-6 col-md"><div class="card p-2 h-100"><div class="small text-muted">Total</div><div class="fw-bold">{{ inr($byCategory->sum()) }}</div></div></div>
    @foreach($byCategory as $k => $total)<div class="col-6 col-md"><div class="card p-2 h-100"><div class="small text-muted">{{ $C[$k] ?? $k }}</div><div class="fw-bold">{{ inr($total) }}</div></div></div>@endforeach
</div>
<div class="card">
    <div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">
        <thead><tr><th>Date</th><th>Category</th><th>Details</th><th>Vehicle</th><th>Mode</th><th class="text-end">Amount</th><th></th></tr></thead>
        <tbody>
        @forelse($expenses as $e)
            <tr><td>{{ $e->expense_date->format('d M Y') }}</td><td>{{ $C[$e->category] ?? $e->category }}</td><td>{{ $e->title }}</td><td>{{ $e->vehicle?->bus_no ?? 'General' }}</td><td class="small">{{ \App\Models\Payment::METHODS[$e->method] ?? $e->method }}</td><td class="text-end">{{ inr($e->amount) }}</td>
                <td class="text-end text-nowrap">@if($e->bill_path)<a href="{{ route('transport.expenses.bill', $e) }}" target="_blank"><i class="bi bi-paperclip"></i></a>@endif
                    @if($can)<a href="{{ route('transport.expenses.edit', $e) }}" class="ms-1"><i class="bi bi-pencil"></i></a>
                    <form method="POST" action="{{ route('transport.expenses.destroy', $e) }}" class="d-inline" data-confirm="Delete this expense?">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger p-0 ms-1"><i class="bi bi-x-lg"></i></button></form>@endif</td></tr>
        @empty<tr><td colspan="7" class="text-muted text-center py-3">No expenses in this period.</td></tr>@endforelse
        </tbody>
    </table></div>
    @if($expenses->hasPages())<div class="card-footer">{{ $expenses->links('pagination::bootstrap-5') }}</div>@endif
</div>
@endsection
