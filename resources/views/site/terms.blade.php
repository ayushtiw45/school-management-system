@extends('layouts.site')
@section('title', __('Terms'))
@section('content')
<div class="container py-5" style="max-width:820px">
    <h1 class="fw-bold mb-4">{{ __('Terms of Use') }}</h1>
    <ol class="lh-lg">
        <li>{{ __('The free trial lets you use all features for the trial period shown on this website.') }}</li>
        <li>{{ __('A license is valid for one computer (one Installation ID) for the period you buy. Lifetime licenses do not expire.') }}</li>
        <li>{{ __('After the trial or license ends, the software stays usable in read-only mode. Your data is never deleted by the software.') }}</li>
        <li>{{ __('You are responsible for backing up the data folder on your computer.') }}</li>
        <li>{{ __('License fees are non-refundable once the license key has been issued, except where required by law.') }}</li>
        <li>{{ __('The software is provided as is. We will do our best to help you with problems through phone, WhatsApp or email.') }}</li>
    </ol>
</div>
@endsection
