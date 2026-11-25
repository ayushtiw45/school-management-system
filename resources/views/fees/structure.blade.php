@extends('layouts.app')
@section('title', 'Class-wise Fee Structure')
@section('content')
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center">
        <form class="d-flex gap-2 align-items-center">
            <label class="small text-muted">Session</label>
            <select name="session_id" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach($sessions as $ses)<option value="{{ $ses->id }}" @selected($ses->id === $session->id)>{{ $ses->name }}</option>@endforeach
            </select>
        </form>
        <span class="small text-muted">Enter the amount <strong>per instalment</strong> (e.g. monthly tuition per month). Leave blank if not charged.</span>
        <form method="POST" action="{{ route('fee-structure.generate') }}" class="ms-auto" data-confirm="Assign fees from this structure to all active students of {{ $session->name }}? Already-assigned fees are not duplicated.">
            @csrf <input type="hidden" name="session_id" value="{{ $session->id }}">
            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Assign to all students</button>
        </form>
    </div>
</div>

@if($heads->isEmpty() || $classes->isEmpty())
    <div class="alert alert-info">Add <a href="{{ route('fee-heads.index') }}">fee categories</a> and classes first.</div>
@else
<form method="POST" action="{{ route('fee-structure.store') }}" class="card">
    @csrf <input type="hidden" name="session_id" value="{{ $session->id }}">
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead>
                <tr>
                    <th>Class</th>
                    @foreach($heads as $h)<th class="text-center">{{ $h->name }}<div class="fw-normal text-lowercase" style="font-size:.68rem">{{ $h->frequencyLabel() }}</div></th>@endforeach
                    <th class="text-end">Yearly Total</th>
                </tr>
            </thead>
            <tbody>
            @foreach($classes as $c)
                @php($yearly = 0)
                <tr>
                    <td class="fw-semibold text-nowrap">{{ $c->name }}</td>
                    @foreach($heads as $h)
                        @php($amt = $grid[$c->id][$h->id] ?? null)
                        @php($yearly += (float) $amt * (\App\Models\FeeHead::INSTALMENTS[$h->frequency] ?? 1))
                        <td><input type="number" step="0.01" min="0" name="amounts[{{ $c->id }}][{{ $h->id }}]" value="{{ $amt !== null ? (float) $amt : '' }}" class="form-control form-control-sm text-end" style="min-width:90px"></td>
                    @endforeach
                    <td class="text-end fw-semibold text-nowrap">{{ inr($yearly) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary"><i class="bi bi-save"></i> Save Fee Structure</button></div>
</form>
@endif
@endsection
