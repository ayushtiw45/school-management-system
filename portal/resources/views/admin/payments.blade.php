@extends('layouts.admin')
@section('title', 'Payments')
@section('content')
<div class="card">
    <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center">
        <form class="d-flex gap-2"><select name="status" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">All statuses</option>@foreach(['paid', 'created', 'failed'] as $st)<option value="{{ $st }}" @selected(request('status') === $st)>{{ ucfirst($st) }}</option>@endforeach</select></form>
        <span class="ms-auto">Total received: <strong>₹{{ number_format($total, 2) }}</strong></span>
    </div>
    <div class="table-responsive"><table class="table table-sm mb-0">
        <thead><tr><th>Created</th><th>School</th><th>Installation ID</th><th>Plan</th><th>Razorpay order / payment</th><th>Status</th><th class="text-end">Amount</th></tr></thead>
        <tbody>
        @forelse($payments as $p)
            <tr>
                <td>{{ $p->created_at->format('d M Y H:i') }}</td>
                <td><a href="{{ route('admin.schools.show', $p->user) }}">{{ $p->user->school_name }}</a></td>
                <td class="font-monospace">{{ $p->installation_id }}</td>
                <td>{{ \App\Services\LicenseIssuer::PLANS[$p->plan]['label'] ?? $p->plan }}</td>
                <td class="small">{{ $p->razorpay_order_id }}<br>{{ $p->razorpay_payment_id }}</td>
                <td><span class="badge {{ ['paid' => 'text-bg-success', 'failed' => 'text-bg-danger'][$p->status] ?? 'text-bg-secondary' }}">{{ $p->status === 'created' ? 'not completed' : $p->status }}</span></td>
                <td class="text-end">₹{{ number_format($p->rupees(), 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-center text-muted py-4">No payments yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if($payments->hasPages())<div class="card-footer bg-white">{{ $payments->links('pagination::bootstrap-5') }}</div>@endif
</div>
@endsection
