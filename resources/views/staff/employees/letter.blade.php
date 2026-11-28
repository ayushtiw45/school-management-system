@extends('layouts.print')
@section('title', ucfirst($type).' letter')
@section('content')
@php($E = \App\Models\Hr\Employee::class)
@php($he = $e->gender === 'female' ? ['she', 'her', 'Ms.'] : ($e->gender === 'male' ? ['he', 'his', 'Mr.'] : ['they', 'their', '']))
<div class="sheet" style="max-width:760px">
    @include('partials.school-head')
    <div class="d-flex justify-content-between small mb-4"><span>Ref: {{ strtoupper(substr($type, 0, 3)) }}/{{ $e->employee_id }}/{{ $date->format('Y') }}</span><span>Date: {{ $date->format('d M Y') }}</span></div>
    @if($type === 'joining')
        <h5 class="text-center fw-bold mb-4">APPOINTMENT LETTER</h5>
        <p>To,<br>{{ trim($he[2].' '.$e->name) }}<br>{{ $e->address }}</p>
        <p>Dear {{ $e->name }},</p>
        <p>We are pleased to appoint you as <strong>{{ $e->designation ?: $E::TYPES[$e->type].' staff' }}</strong>@if($e->department) in the {{ $e->department }} department @endif of {{ school('name') }}, with effect from <strong>{{ $e->joining_date?->format('d M Y') ?? '__________' }}</strong>, on a <strong>{{ strtolower($E::EMPLOYMENT[$e->employment_type]) }}</strong> basis @if($e->contract_end) up to {{ $e->contract_end->format('d M Y') }}@endif.</p>
        @if($e->gross() > 0)<p>Your monthly gross salary will be <strong>{{ inr($e->gross()) }}</strong> ({{ amount_in_words($e->gross()) }}), subject to applicable deductions.</p>@endif
        <p>You will follow the rules and regulations of the school as amended from time to time. Please sign the copy of this letter as your acceptance.</p>
        <p class="mt-5">Accepted by: ______________________ ({{ $e->name }})</p>
    @elseif($type === 'experience')
        <h5 class="text-center fw-bold mb-4">EXPERIENCE CERTIFICATE</h5>
        <p class="mt-3"><strong>TO WHOM IT MAY CONCERN</strong></p>
        <p>This is to certify that <strong>{{ trim($he[2].' '.$e->name) }}</strong> (Employee ID {{ $e->employee_id }}) has worked with {{ school('name') }} as <strong>{{ $e->designation ?: $E::TYPES[$e->type].' staff' }}</strong>
            from <strong>{{ $e->joining_date?->format('d M Y') ?? '__________' }}</strong> to <strong>{{ $e->leaving_date?->format('d M Y') ?? 'date' }}</strong>.</p>
        <p>During this period {{ $he[1] }} work and conduct were found to be good. We wish {{ $he[0] === 'they' ? 'them' : ($he[0] === 'she' ? 'her' : 'him') }} success in future.</p>
    @else
        <h5 class="text-center fw-bold mb-4">RELIEVING LETTER</h5>
        <p>To,<br>{{ trim($he[2].' '.$e->name) }}<br>Employee ID {{ $e->employee_id }}</p>
        <p>This is to confirm that you have been relieved from your duties as <strong>{{ $e->designation ?: $E::TYPES[$e->type].' staff' }}</strong> at {{ school('name') }} with effect from the close of working hours on <strong>{{ $e->leaving_date?->format('d M Y') ?? '__________' }}</strong>.</p>
        <p>All dues have been settled and you have handed over the charge entrusted to you. We thank you for your service and wish you the very best.</p>
    @endif
    <div class="d-flex justify-content-end mt-5"><div class="text-center" style="min-width:200px"><div style="height:48px"></div><div class="border-top pt-1">Principal / Manager</div><div class="small text-muted">{{ school('name') }}</div></div></div>
</div>
@endsection
