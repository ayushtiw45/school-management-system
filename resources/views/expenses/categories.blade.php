@extends('layouts.app')
@section('title', 'Expense Categories')
@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card"><div class="card-header">Add Category</div><div class="card-body">
            <form method="POST" action="{{ route('expense-categories.store') }}" class="row g-2">
                @csrf
                <div class="col-12"><input name="name" class="form-control" placeholder="e.g. Sports Equipment" required></div>
                <div class="col-12 form-check ms-2"><input type="checkbox" name="is_salary" value="1" class="form-check-input" id="sal"><label class="form-check-label" for="sal">Salary category (asks staff name & month)</label></div>
                <div class="col-12"><button class="btn btn-primary w-100">Add</button></div>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <table class="table mb-0 align-middle">
                <thead><tr><th>Category</th><th class="text-end">Entries</th><th class="text-end">Total Spent</th><th></th></tr></thead>
                <tbody>
                @forelse($categories as $c)
                    <tr>
                        <td>
                            <form method="POST" action="{{ route('expense-categories.update', $c) }}" class="d-flex gap-2 align-items-center">
                                @csrf @method('PUT')
                                <input name="name" value="{{ $c->name }}" class="form-control form-control-sm" style="max-width:240px">
                                <label class="small text-nowrap"><input type="checkbox" name="is_salary" value="1" class="form-check-input" @checked($c->is_salary)> Salary</label>
                                <button class="btn btn-sm btn-outline-primary"><i class="bi bi-check"></i></button>
                            </form>
                        </td>
                        <td class="text-end">{{ $c->expenses_count }}</td>
                        <td class="text-end">{{ inr($c->expenses_sum_amount ?? 0) }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('expense-categories.destroy', $c) }}" data-confirm="Delete {{ $c->name }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger"><i class="bi bi-trash"></i></button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No categories.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
