@extends('layouts.super')
@section('title', 'Coupons')
@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        <form method="POST" action="{{ route('super.coupons.store') }}" class="card">
            @csrf
            <div class="card-header bg-white fw-semibold">New coupon</div>
            <div class="card-body">@include('super.coupon-fields')</div>
            <div class="card-footer bg-white text-end"><button class="btn text-white" style="background:#FF4B32;border-radius:999px">Create coupon</button></div>
        </form>
        <div class="alert alert-light border small mt-3">
            Schools enter the code on <strong>Plan & Billing</strong> before paying. The discount applies to every monthly Autopay charge as long as that Autopay runs.
            "Extra free days" push the first monthly charge further away (added to the ₹{{ \App\Services\Billing::trialPaise() / 100 }} trial for new schools).
            Each school can use a coupon once. Changing a coupon later does not change the price of schools that already used it.
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">All coupons</div>
            <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                <thead><tr><th>Code</th><th>Offer</th><th>Valid</th><th>Used</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($coupons as $c)
                    <tr>
                        <td><strong>{{ $c->code }}</strong><div class="small text-muted">{{ $c->description }}</div></td>
                        <td class="small">{{ $c->label() }}<div class="text-muted">₹{{ number_format($c->apply($monthly) / 100) }}/month{{ $c->school ? ' · only '.$c->school->name : '' }}{{ $c->new_schools_only ? ' · new schools' : '' }}</div></td>
                        <td class="small text-nowrap">{{ $c->valid_from?->format('d M') ?? 'now' }} – {{ $c->valid_until?->format('d M Y') ?? 'no end' }}</td>
                        <td>{{ $c->used_count }}{{ $c->max_uses ? ' / '.$c->max_uses : '' }}</td>
                        <td><span class="badge {{ $c->status() === 'Active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $c->status() }}</span></td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('super.coupons.edit', $c) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            <form method="POST" action="{{ route('super.coupons.toggle', $c) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $c->is_active ? 'Off' : 'On' }}</button></form>
                            @if(! $c->redemptions_count)<form method="POST" action="{{ route('super.coupons.destroy', $c) }}" class="d-inline" onsubmit="return confirm('Delete this coupon?')">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger p-0 ms-1"><i class="bi bi-x-lg"></i></button></form>@endif
                        </td>
                    </tr>
                @empty<tr><td colspan="6" class="text-center text-muted py-4">No coupons yet.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
        <div class="card">
            <div class="card-header bg-white fw-semibold">Recently used</div>
            <div class="table-responsive"><table class="table table-sm mb-0">
                <thead><tr><th>Date</th><th>School</th><th>Coupon</th><th>Monthly</th><th>Free days</th></tr></thead>
                <tbody>
                @forelse($recent as $r)
                    <tr><td>{{ $r->created_at->format('d M Y') }}</td><td><a href="{{ route('super.schools.show', $r->school) }}">{{ $r->school->name }}</a></td><td>{{ $r->coupon->code }}</td>
                        <td>₹{{ number_format($r->monthly_after / 100) }} <span class="text-muted small text-decoration-line-through">₹{{ number_format($r->monthly_before / 100) }}</span></td><td>{{ $r->extra_days ?: '' }}</td></tr>
                @empty<tr><td colspan="5" class="text-center text-muted py-3">No coupon used yet.</td></tr>@endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection
