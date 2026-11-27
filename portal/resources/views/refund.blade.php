@extends('layouts.site')
@section('title', __('Refund & Cancellation Policy'))
@section('content')
@php($trialDays = \App\Models\Setting::get('trial_days')) @php($trial = \App\Models\Setting::get('trial_price')) @php($monthly = \App\Models\Setting::get('monthly_price'))
<div class="container py-5" style="max-width:820px">
    <h1 class="fw-bold mb-4">{{ __('Refund & Cancellation Policy') }}</h1>
    <h2 class="h5 mt-4">{{ __('Trial') }}</h2>
    <p>{{ __('The trial costs ₹:trial and gives full access for :days days. The ₹:trial trial fee is not refundable.', ['trial' => $trial, 'days' => $trialDays]) }}</p>
    <h2 class="h5 mt-4">{{ __('Monthly subscription') }}</h2>
    <p>{{ __('After the trial, ₹:monthly is charged every month through UPI Autopay or card until you cancel. Razorpay / your bank informs you before every automatic payment.', ['monthly' => $monthly]) }}</p>
    <h2 class="h5 mt-4">{{ __('How to cancel') }}</h2>
    <p>{{ __('Log in to your account on this website and click "Cancel Autopay", or cancel the mandate in your UPI app. If you cancel during the trial, nothing more is charged. If you cancel later, no further months are charged and the software keeps working until the end of the month already paid.') }}</p>
    <h2 class="h5 mt-4">{{ __('Refunds') }}</h2>
    <p>{{ __('Monthly payments already made are not refunded, except when you were charged by mistake (for example, charged after cancelling). Write to us within 7 days of such a charge and we refund it to the original payment method within 5–7 working days.') }}</p>
    <h2 class="h5 mt-4">{{ __('Delivery') }}</h2>
    <p>{{ __('This is downloadable software. The software is activated online immediately after payment; nothing is shipped.') }}</p>
    <p class="mt-4"><a href="{{ route('contact') }}">{{ __('Contact us') }}</a></p>
</div>
@endsection
