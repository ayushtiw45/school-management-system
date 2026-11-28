@extends('layouts.guest')
@section('title', 'Online Admission Form')
@include('admissions.public-meta')
@section('content')
<div class="container py-4" style="max-width:980px">
    <div class="text-center text-white mb-4">
        @if($school->logoUrl())<img src="{{ $school->logoUrl() }}" alt="" style="height:72px;max-width:120px;object-fit:contain;background:#fff;border-radius:14px;padding:6px" class="mb-2">@endif
        <div class="small text-uppercase accent fw-semibold" style="letter-spacing:.1em">Online Admission · Session {{ $session->name }}</div>
        <h1 class="h3 fw-bold mt-1">{{ school('name') }}</h1>
        <p class="opacity-75 small mb-0">Fill the form below. The school office will contact you after reviewing your application.</p>
    </div>
    <div class="bg-light rounded-4 p-3 p-md-4">
        @include('partials.flash')
        <form method="POST" action="{{ route('apply.store', $school) }}" enctype="multipart/form-data">
            @csrf
            @include('partials.student-fields', ['student' => null, 'classes' => $classes, 'public' => true])
            <button class="btn btn-primary btn-lg w-100">Submit Application</button>
        </form>
        <div class="text-center mt-3"><a href="{{ route('apply.form', $school) }}" target="_blank" class="small"><i class="bi bi-printer"></i> Prefer paper? Download / print the blank form</a></div>
    </div>
</div>
@endsection
