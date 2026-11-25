@extends('layouts.print')
@section('title', 'Receipt '.$payment->receipt_no)
@section('content')
@php($p = $payment)
@php($s = $p->student)
<div class="sheet" style="max-width:720px">
    @include('partials.school-head', ['docTitle' => 'Fee Receipt'])
    <div class="row small mb-3">
        <div class="col-6">
            <div><strong>Receipt No:</strong> {{ $p->receipt_no }}</div>
            <div><strong>Date:</strong> {{ $p->payment_date->format('d M Y') }}</div>
            <div><strong>Mode:</strong> {{ $p->methodLabel() }} @if($p->reference_no)({{ $p->reference_no }})@endif</div>
            @if($p->bank_name)<div><strong>Bank:</strong> {{ $p->bank_name }} @if($p->cheque_date)· {{ $p->cheque_date->format('d M Y') }}@endif</div>@endif
        </div>
        <div class="col-6 text-end">
            <div><strong>{{ $s->full_name }}</strong></div>
            <div>Adm. No: {{ $s->admission_no }}</div>
            <div>Class: {{ $s->class_label }}</div>
            <div>Father: {{ $s->father_name }}</div>
        </div>
    </div>
    <table class="table table-bordered table-sm">
        <thead class="table-light"><tr><th style="width:40px">#</th><th>Particulars</th><th class="text-end">Fine</th><th class="text-end">Discount</th><th class="text-end">Amount Paid</th></tr></thead>
        <tbody>
        @foreach($p->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->studentFee->title }}</td>
                <td class="text-end">{{ (float) $item->fine ? inr($item->fine, false) : '—' }}</td>
                <td class="text-end">{{ (float) $item->discount ? inr($item->discount, false) : '—' }}</td>
                <td class="text-end">{{ inr($item->amount, false) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr><th colspan="4" class="text-end">Total Paid</th><th class="text-end">{{ inr($p->amount) }}</th></tr>
            <tr><td colspan="4" class="text-end small">Balance due after this payment</td><td class="text-end small">{{ inr($balance) }}</td></tr>
        </tfoot>
    </table>
    @if($p->remarks)<p class="small"><strong>Remarks:</strong> {{ $p->remarks }}</p>@endif
    <div class="d-flex justify-content-between align-items-end mt-5">
        <div class="small text-muted">Received by: {{ $p->receiver?->name }}<br>This is a computer-generated receipt.</div>
        <div class="text-center border-top pt-1" style="width:180px">Authorised Signatory</div>
    </div>
</div>
@if(auth()->user()->isAdmin())
    <div class="text-center mb-4 no-print">
        <form method="POST" action="{{ route('payments.destroy', $p) }}" onsubmit="return confirm('Cancel this receipt? The paid amount will be added back to the student\'s dues.')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger">Cancel Receipt</button>
        </form>
    </div>
@endif
@endsection
