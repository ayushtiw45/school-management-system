@extends('layouts.super')
@section('title', 'Coupon '.$coupon->code)
@section('content')
<div class="row g-3">
    <div class="col-lg-6">
        <form method="POST" action="{{ route('super.coupons.update', $coupon) }}" class="card">
            @csrf @method('PUT')
            <div class="card-body">@include('super.coupon-fields')</div>
            <div class="card-footer bg-white d-flex"><a href="{{ route('super.coupons') }}" class="btn btn-outline-secondary">Back</a><button class="btn text-white ms-auto" style="background:#FF4B32;border-radius:999px">Save</button></div>
        </form>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-header bg-white fw-semibold">Used by {{ $coupon->redemptions->count() }} school(s)</div>
            <ul class="list-group list-group-flush small">
                @forelse($coupon->redemptions as $r)<li class="list-group-item d-flex">{{ $r->school->name }}<span class="ms-auto">₹{{ number_format($r->monthly_after / 100) }}/month · {{ $r->created_at->format('d M Y') }}</span></li>
                @empty<li class="list-group-item text-muted">Not used yet.</li>@endforelse
            </ul></div>
    </div>
</div>
@endsection
