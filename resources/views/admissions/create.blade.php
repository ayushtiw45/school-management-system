@extends('layouts.app')
@section('title', 'New Admission')
@section('content')
@php
    $prefill = null;
    if ($enquiry) {
        $parts = preg_split('/\s+/', trim($enquiry->child_name), 2);
        $prefill = new \App\Models\Student([
            'first_name' => $parts[0] ?? '', 'last_name' => $parts[1] ?? '', 'father_name' => $enquiry->parent_name,
            'father_phone' => $enquiry->phone, 'mother_phone' => $enquiry->alt_phone, 'email' => $enquiry->email,
            'dob' => $enquiry->dob, 'school_class_id' => $enquiry->school_class_id, 'previous_school' => $enquiry->previous_school,
        ]);
    }
@endphp
<form method="POST" action="{{ route('admissions.store') }}" enctype="multipart/form-data">
    @csrf
    @if($enquiry)<input type="hidden" name="enquiry_id" value="{{ $enquiry->id }}">@endif
    <div class="alert alert-light border d-flex flex-wrap gap-3 align-items-center">
        <div><span class="text-muted small">Session</span><br><strong>{{ $session->name }}</strong></div>
        <div><span class="text-muted small">Admission No. (auto)</span><br><strong>{{ $nextNo }}</strong></div>
        @if($enquiry)<div><span class="text-muted small">From enquiry</span><br><a href="{{ route('enquiries.show', $enquiry) }}">{{ $enquiry->child_name }}</a></div>@endif
        <a href="{{ route('admissions.blank') }}" target="_blank" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-printer"></i> Print blank form</a>
    </div>
    @include('partials.student-fields', ['student' => $prefill, 'classes' => $classes])
    <div class="card"><div class="card-body d-flex flex-wrap gap-3 align-items-center">
        <div class="small text-muted"><i class="bi bi-info-circle"></i> The admission stays <strong>pending</strong> until the admission fee is paid at the accounts desk. Then it is confirmed and the Student ID is given automatically.</div>
        <button class="btn btn-primary ms-auto"><i class="bi bi-check2-circle"></i> Save admission form</button>
    </div></div>
</form>
@endsection
