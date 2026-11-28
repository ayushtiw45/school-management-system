@extends('layouts.print')
@section('title', 'Fee Slip '.$student->full_name)
@section('content')
@php($s = $student)
@php($upi = upi_link($total ?: null, 'Fee '.($s->student_code ?: $s->admission_no)))
<div class="sheet" style="max-width:720px">
    @include('partials.school-head', ['docTitle' => 'Fee Slip'])
    <form class="no-print d-flex gap-2 justify-content-center align-items-center mb-3 small">
        Dues up to <input type="date" name="upto" value="{{ $upto?->format('Y-m-d') }}" class="form-control form-control-sm" style="width:auto"> <button class="btn btn-sm btn-outline-secondary">Show</button>
        @if($upto)<a href="{{ route('students.feeslip', $s) }}">All dues</a>@endif
    </form>
    <div class="row small mb-3">
        <div class="col-6">
            <div><strong>{{ $s->full_name }}</strong></div>
            @if($s->student_code)<div>Student ID: <strong>{{ $s->student_code }}</strong></div>@endif
            <div>Adm. No: {{ $s->admission_no }}</div>
            <div>Class: {{ $s->class_label }}</div>
        </div>
        <div class="col-6 text-end">
            <div>Date: {{ now()->format('d M Y') }}</div>
            <div>Father: {{ $s->father_name }}</div>
            <div>Mobile: {{ $s->parent_phone }}</div>
            @if($s->status === 'pending')<div class="fw-semibold">Admission confirmed after payment</div>@endif
        </div>
    </div>
    <table class="table table-bordered table-sm">
        <thead class="table-light"><tr><th style="width:40px">#</th><th>Particulars</th><th>Due date</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th class="text-end">Balance</th></tr></thead>
        <tbody>
        @forelse($dues as $i => $f)
            <tr><td>{{ $i + 1 }}</td><td>{{ $f->title }}</td><td>{{ $f->due_date->format('d M Y') }}</td>
                <td class="text-end">{{ inr($f->netAmount(), false) }}</td><td class="text-end">{{ (float) $f->paid ? inr($f->paid, false) : '—' }}</td><td class="text-end">{{ inr($f->balance(), false) }}</td></tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted">No dues.</td></tr>
        @endforelse
        </tbody>
        <tfoot><tr><th colspan="5" class="text-end">Total payable</th><th class="text-end">{{ inr($total) }}</th></tr></tfoot>
    </table>
    <p class="small"><strong>In words:</strong> {{ amount_in_words($total) }}</p>

    @php($bank = \App\Models\Setting::get('bank_account_no'))
    @if($bank || $upi)
        <div class="border rounded p-2 d-flex gap-3 align-items-center small">
            <div class="flex-grow-1">
                <div class="fw-semibold mb-1">Pay online</div>
                @if($bank)
                    <div>Account name: {{ \App\Models\Setting::get('bank_account_name') ?: school('name') }}</div>
                    <div>Account no.: <strong>{{ $bank }}</strong> · IFSC: <strong>{{ \App\Models\Setting::get('bank_ifsc') }}</strong></div>
                    @if(\App\Models\Setting::get('bank_name'))<div>Bank: {{ \App\Models\Setting::get('bank_name') }}</div>@endif
                @endif
                @if($upi)<div class="mt-1">UPI ID: <strong>{{ \App\Models\Setting::get('upi_id') }}</strong> — scan the QR with any UPI app</div>@endif
                <div class="text-muted mt-1">Please write the Student ID / Adm. No. in the payment note and show the payment message at the accounts desk.</div>
            </div>
            @if($upi)<div id="upiQr" data-upi="{{ $upi }}" style="width:120px;height:120px"></div>@endif
        </div>
    @endif

    <div class="d-flex justify-content-end mt-4">@include('partials.signatory')</div>
    @include('partials.terms', ['key' => 'slip_terms'])
</div>
@if($upi)
<script src="{{ asset('vendor/qrcode/qrcode.min.js') }}"></script>
<script>
    (function () { var el = document.getElementById('upiQr'); var qr = qrcode(0, 'M'); qr.addData(el.dataset.upi); qr.make();
        el.innerHTML = qr.createSvgTag({cellSize: 3, margin: 0}); var svg = el.querySelector('svg'); svg.setAttribute('width', '120'); svg.setAttribute('height', '120'); })();
</script>
@endif
@endsection
