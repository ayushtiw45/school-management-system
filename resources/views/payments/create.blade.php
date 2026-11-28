@extends('layouts.app')
@section('title', 'Collect Fee')
@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form class="d-flex gap-2">
            <input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search by student name, Student ID, admission no, father's name or mobile">
            <button class="btn btn-primary text-nowrap"><i class="bi bi-search"></i> Find Student</button>
        </form>
    </div>
    @if($matches->count() > 1)
        <div class="list-group list-group-flush border-top">
            @foreach($matches as $m)
                <a href="{{ route('payments.create', ['student_id' => $m->id]) }}" class="list-group-item list-group-item-action d-flex gap-2 align-items-center">
                    @include('students.avatar', ['s' => $m])
                    <div><strong>{{ $m->full_name }}</strong> <span class="text-muted small">· {{ $m->student_code ?: $m->admission_no }} · {{ $m->class_label }}</span>@if($m->status === 'pending') <span class="badge text-bg-warning">Admission pending</span>@endif<div class="small text-muted">Father: {{ $m->father_name }} · {{ $m->parent_phone }}</div></div>
                </a>
            @endforeach
        </div>
    @elseif(request('q') && ! $student)
        <div class="card-footer text-muted">No student found.</div>
    @endif
</div>

@if($browse)
    @include('payments.browse')
@endif

@if($student)
<form method="POST" action="{{ route('payments.store', $student) }}" id="payForm">
    @csrf
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex align-items-center gap-2">
                    @include('students.avatar', ['s' => $student])
                    <div><a href="{{ route('students.show', $student) }}">{{ $student->full_name }}</a> <span class="text-muted small fw-normal">· {{ $student->student_code ?: $student->admission_no }} · {{ $student->class_label }}</span>@if($student->status === 'pending') <span class="badge text-bg-warning">Admission pending — confirmed after this payment</span>@endif</div>
                    @if($student->status !== 'active')<span class="ms-auto">@include('students.status-badge', ['status' => $student->status])</span>@endif
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead><tr><th style="width:30px"><input type="checkbox" class="form-check-input" id="pickAll"></th><th>Fee</th><th>Due</th><th class="text-end">Balance</th><th style="width:100px">Fine</th><th style="width:100px">Discount</th><th style="width:120px">Pay Now</th></tr></thead>
                        <tbody>
                        @forelse($dues as $f)
                            <tr class="{{ $f->isOverdue() ? 'table-warning' : '' }}" data-balance="{{ $f->balance() }}">
                                <td><input type="checkbox" class="form-check-input pick"></td>
                                <td>{{ $f->title }}<div class="small text-muted">{{ $f->academicSession?->name }} @if((float) $f->paid > 0) · paid {{ inr($f->paid) }} @endif</div></td>
                                <td class="small text-nowrap">{{ $f->due_date->format('d M Y') }} @if($f->isOverdue())<span class="badge text-bg-danger">Late</span>@endif</td>
                                <td class="text-end">{{ inr($f->balance(), false) }}</td>
                                <td><input type="number" step="0.01" min="0" name="lines[{{ $f->id }}][fine]" value="{{ old("lines.{$f->id}.fine", $f->suggested_fine ?: '') }}" class="form-control form-control-sm text-end fine" placeholder="0" disabled></td>
                                <td><input type="number" step="0.01" min="0" name="lines[{{ $f->id }}][discount]" value="{{ old("lines.{$f->id}.discount") }}" class="form-control form-control-sm text-end disc" placeholder="0" disabled></td>
                                <td><input type="number" step="0.01" min="0" name="lines[{{ $f->id }}][amount]" value="{{ old("lines.{$f->id}.amount") }}" class="form-control form-control-sm text-end amt" placeholder="0" disabled></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No pending fees for this student. 🎉</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-muted">Tick the fees to collect. Pay Now fills with the full balance; reduce it for a partial payment.</div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">Payment Details</div>
                <div class="card-body row g-2">
                    <div class="col-12 d-flex justify-content-between align-items-center bg-light rounded p-2 mb-1">
                        <span>Total</span><span class="fs-4 fw-bold" id="total">₹0.00</span>
                    </div>
                    <div class="col-12"><label class="form-label required">Date</label><input type="date" name="payment_date" value="{{ old('payment_date', now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" class="form-control" required></div>
                    <div class="col-12">
                        <label class="form-label required">Payment Mode</label>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach(\App\Models\Payment::METHODS as $k => $l)
                                <input type="radio" class="btn-check" name="method" value="{{ $k }}" id="m{{ $k }}" @checked(old('method', 'cash') === $k)>
                                <label class="btn btn-sm btn-outline-primary" for="m{{ $k }}">{{ $l }}</label>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-12 ref d-none"><label class="form-label required" id="refLabel">Transaction ID</label><input name="reference_no" value="{{ old('reference_no') }}" class="form-control"></div>
                    <div class="col-7 cheque d-none"><label class="form-label">Bank</label><input name="bank_name" value="{{ old('bank_name') }}" class="form-control"></div>
                    <div class="col-5 cheque d-none"><label class="form-label">Cheque Date</label><input type="date" name="cheque_date" value="{{ old('cheque_date') }}" class="form-control"></div>
                    <div class="col-12"><label class="form-label">Remarks</label><input name="remarks" value="{{ old('remarks') }}" class="form-control"></div>
                    <div class="col-12"><button class="btn btn-primary w-100 btn-lg mt-2" id="payBtn" disabled><i class="bi bi-check2-circle"></i> Save & Print Receipt</button></div>
                </div>
            </div>
        </div>
    </div>
</form>
@push('scripts')
<script>
(function () {
    const rows = [...document.querySelectorAll('#payForm tr[data-balance]')];
    const fmt = n => '₹' + n.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    function recalc() {
        let t = 0;
        rows.forEach(r => { if (r.querySelector('.pick').checked) t += parseFloat(r.querySelector('.amt').value || 0); });
        document.getElementById('total').textContent = fmt(t);
        document.getElementById('payBtn').disabled = t <= 0;
    }
    function fill(r) {
        const bal = parseFloat(r.dataset.balance), fine = parseFloat(r.querySelector('.fine').value || 0), disc = parseFloat(r.querySelector('.disc').value || 0);
        r.querySelector('.amt').value = Math.max(0, bal + fine - disc).toFixed(2);
    }
    rows.forEach(r => {
        const pick = r.querySelector('.pick');
        const inputs = r.querySelectorAll('input[type=number]');
        pick.addEventListener('change', () => {
            inputs.forEach(i => i.disabled = !pick.checked);
            if (pick.checked) fill(r); else r.querySelector('.amt').value = '';
            recalc();
        });
        r.querySelector('.fine').addEventListener('input', () => { fill(r); recalc(); });
        r.querySelector('.disc').addEventListener('input', () => { fill(r); recalc(); });
        r.querySelector('.amt').addEventListener('input', recalc);
        if (r.querySelector('.amt').value) { pick.checked = true; inputs.forEach(i => i.disabled = false); }
    });
    document.getElementById('pickAll')?.addEventListener('change', e => rows.forEach(r => { const p = r.querySelector('.pick'); if (p.checked !== e.target.checked) { p.checked = e.target.checked; p.dispatchEvent(new Event('change')); } }));
    function mode() {
        const m = document.querySelector('input[name=method]:checked').value;
        document.querySelector('.ref').classList.toggle('d-none', m === 'cash');
        document.querySelectorAll('.cheque').forEach(e => e.classList.toggle('d-none', m !== 'cheque'));
        document.getElementById('refLabel').textContent = {upi: 'UPI Transaction ID', bank: 'Bank Reference No', cheque: 'Cheque No', online: 'Gateway Transaction ID'}[m] || 'Reference';
    }
    document.querySelectorAll('input[name=method]').forEach(r => r.addEventListener('change', mode));
    mode(); recalc();
})();
</script>
@endpush
@endif
@endsection
