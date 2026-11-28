@extends('layouts.guest')
@section('title', 'Application Submitted')
@include('admissions.public-meta')
@section('content')
<div class="container d-flex align-items-center justify-content-center" style="min-height:100vh">
    <div class="card p-4 text-center" style="max-width:460px">
        <div class="display-5 text-success"><i class="bi bi-check-circle"></i></div>
        <h1 class="h4 fw-bold mt-2">Application Submitted</h1>
        <p class="text-muted">Thank you. Your application number is</p>
        <div class="h3 fw-bold">{{ $admissionNo }}</div>
        <p class="text-muted small mt-3 mb-0">Please keep this number for reference. The school office will contact you on the mobile number you provided.</p>
    </div>
</div>
@endsection
