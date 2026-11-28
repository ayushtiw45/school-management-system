@extends('layouts.app')
@section('title', 'Salary Advances')
@section('content')
@php($can = auth()->user()->canManagePayroll())
<div class="row g-3">
    @if($can)
    <div class="col-lg-4">
        <form method="POST" action="{{ route('staff.advances.store') }}" class="card">@csrf
            <div class="card-header">Give advance / loan</div>
            <div class="card-body row g-2">
                <div class="col-12"><select name="employee_id" class="form-select" required><option value="">Staff member…</option>@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach</select></div>
                <div class="col-6"><label class="form-label small mb-0">Date</label><input type="date" name="given_on" value="{{ now()->format('Y-m-d') }}" class="form-control"></div>
                <div class="col-6"><label class="form-label small mb-0">Amount (₹)</label><input type="number" step="0.01" min="1" name="amount" class="form-control" required></div>
                <div class="col-12"><label class="form-label small mb-0">Cut from each salary (₹)</label><input type="number" step="0.01" min="1" name="monthly_deduction" class="form-control" required></div>
                <div class="col-12"><input name="note" class="form-control" placeholder="Note"></div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary">Save</button></div>
        </form>
    </div>
    @endif
    <div class="{{ $can ? 'col-lg-8' : 'col-12' }}">
        <div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
            <thead><tr><th>Name</th><th>Given</th><th class="text-end">Amount</th><th class="text-end">Per month</th><th class="text-end">Recovered</th><th class="text-end">Balance</th><th></th></tr></thead>
            <tbody>
            @forelse($advances as $a)
                <tr class="{{ $a->balance() <= 0 ? 'text-muted' : '' }}"><td>{{ $a->employee?->name }}<div class="small text-muted">{{ $a->note }}</div></td><td>{{ $a->given_on->format('d M Y') }}</td><td class="text-end">{{ inr($a->amount) }}</td>
                    <td class="text-end">{{ inr($a->monthly_deduction) }}</td><td class="text-end">{{ inr($a->recovered) }}</td><td class="text-end fw-semibold">{{ $a->balance() > 0 ? inr($a->balance()) : 'Repaid' }}</td>
                    <td>@if($can && (float) $a->recovered == 0)<form method="POST" action="{{ route('staff.advances.destroy', $a) }}" data-confirm="Delete?">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger p-0"><i class="bi bi-x-lg"></i></button></form>@endif</td></tr>
            @empty<tr><td colspan="7" class="text-muted text-center py-3">No advances.</td></tr>@endforelse
            </tbody>
        </table></div></div>
    </div>
</div>
@endsection
