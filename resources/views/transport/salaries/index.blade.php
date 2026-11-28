@extends('layouts.app')
@section('title', 'Driver & Conductor Salary')
@section('content')
@php($can = auth()->user()->hasRole('admin', 'accountant'))
<form class="d-flex gap-2 mb-3"><input type="month" name="month" value="{{ $month }}" class="form-control form-control-sm" style="max-width:180px" onchange="this.form.submit()">
    <span class="small text-muted align-self-center">Absent days come from attendance; enter a deduction if you cut pay for them.</span></form>
<form method="POST" action="{{ route('transport.salaries.store') }}" class="card">
    @csrf<input type="hidden" name="month" value="{{ $month }}">
    <div class="card-header">{{ $start->format('F Y') }} · paid {{ inr($paid->sum(fn ($p) => $p->net())) }} to {{ $paid->count() }} of {{ $staff->count() }}</div>
    <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
        <thead><tr><th style="width:30px"></th><th>Name</th><th>Absent</th><th style="width:120px">Salary</th><th style="width:110px">Deduction</th><th style="width:110px">Bonus</th><th>Net</th><th>Note</th><th></th></tr></thead>
        <tbody>
        @forelse($staff as $s)
            @php($p = $paid[$s->id] ?? null)
            <tr class="{{ $p ? 'table-success' : '' }}">
                <td>@unless($p)<input type="checkbox" class="form-check-input" name="pay[{{ $s->id }}][save]" value="1">@else<i class="bi bi-check-circle-fill text-success"></i>@endunless</td>
                <td>{{ $s->name }}<div class="small text-muted">{{ \App\Models\Transport\TransportStaff::TYPES[$s->type] }}</div></td>
                <td>{{ ($absent[$s->id] ?? 0) ?: '—' }}</td>
                @if($p)
                    <td>{{ inr($p->amount) }}</td><td>{{ (float) $p->deduction ? inr($p->deduction) : '—' }}</td><td>{{ (float) $p->bonus ? inr($p->bonus) : '—' }}</td><td class="fw-semibold">{{ inr($p->net()) }}</td>
                    <td class="small">{{ $p->paid_on->format('d M') }} · {{ \App\Models\Payment::METHODS[$p->method] ?? $p->method }} {{ $p->note }}</td>
                    <td class="text-end text-nowrap"><a href="{{ route('transport.salaries.slip', $p) }}" target="_blank" class="btn btn-sm btn-outline-secondary">Slip</a>
                        @if($can)<button form="del{{ $p->id }}" class="btn btn-sm btn-link text-danger p-0 ms-1"><i class="bi bi-x-lg"></i></button>@endif</td>
                @else
                    <td><input type="number" step="0.01" min="0" name="pay[{{ $s->id }}][amount]" value="{{ (float) $s->monthly_salary ?: '' }}" class="form-control form-control-sm"></td>
                    <td><input type="number" step="0.01" min="0" name="pay[{{ $s->id }}][deduction]" value="{{ ($absent[$s->id] ?? 0) && (float) $s->monthly_salary ? round($s->monthly_salary / $start->daysInMonth * $absent[$s->id]) : '' }}" class="form-control form-control-sm"></td>
                    <td><input type="number" step="0.01" min="0" name="pay[{{ $s->id }}][bonus]" class="form-control form-control-sm"></td>
                    <td class="text-muted">—</td><td><input name="pay[{{ $s->id }}][note]" class="form-control form-control-sm"></td><td></td>
                @endif
            </tr>
        @empty<tr><td colspan="9" class="text-muted text-center py-3">No drivers or conductors.</td></tr>@endforelse
        </tbody>
    </table></div>
    @if($can)
    <div class="card-footer d-flex flex-wrap gap-2 align-items-end">
        <div><label class="form-label small mb-0">Paid on</label><input type="date" name="paid_on" value="{{ now()->format('Y-m-d') }}" class="form-control form-control-sm"></div>
        <div><label class="form-label small mb-0">Mode</label><select name="method" class="form-select form-select-sm">@foreach(\App\Models\Payment::METHODS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
        <button class="btn btn-primary ms-auto">Record salary for ticked</button>
    </div>
    @endif
</form>
@foreach($paid as $p)<form method="POST" action="{{ route('transport.salaries.destroy', $p) }}" id="del{{ $p->id }}" data-confirm="Delete this salary entry?">@csrf @method('DELETE')</form>@endforeach
@endsection
