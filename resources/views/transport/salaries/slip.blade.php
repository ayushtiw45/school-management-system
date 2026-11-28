@extends('layouts.print')
@section('title', 'Salary slip')
@section('content')
@php($s = $salary->staff)
<div class="sheet" style="max-width:640px">
    @include('partials.school-head', ['docTitle' => 'Salary Slip · '.\Carbon\Carbon::createFromFormat('Y-m', $salary->month)->format('F Y')])
    <table class="table table-sm table-bordered">
        <tr><th style="width:40%">Name</th><td>{{ $s->name }}</td></tr>
        <tr><th>Employee ID</th><td>{{ $s->employee_id }}</td></tr>
        <tr><th>Role</th><td>{{ \App\Models\Transport\TransportStaff::TYPES[$s->type] }} @if($s->vehicle)· {{ $s->vehicle->label() }}@endif</td></tr>
        <tr><th>Salary</th><td class="text-end">{{ inr($salary->amount) }}</td></tr>
        <tr><th>Bonus</th><td class="text-end">{{ inr($salary->bonus) }}</td></tr>
        <tr><th>Deduction</th><td class="text-end">- {{ inr($salary->deduction) }}</td></tr>
        <tr class="table-light"><th>Net paid</th><th class="text-end">{{ inr($salary->net()) }}</th></tr>
        <tr><th>Paid on</th><td>{{ $salary->paid_on->format('d M Y') }} · {{ \App\Models\Payment::METHODS[$salary->method] ?? $salary->method }}</td></tr>
        @if($salary->note)<tr><th>Note</th><td>{{ $salary->note }}</td></tr>@endif
    </table>
    <p class="small"><strong>In words:</strong> {{ amount_in_words($salary->net()) }}</p>
    <div class="d-flex justify-content-between align-items-end mt-5">
        <div class="text-center border-top pt-1" style="width:180px">Received by</div>
        @include('partials.signatory')
    </div>
</div>
@endsection
