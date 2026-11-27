@extends('layouts.admin')
@section('title', 'Licenses')
@section('content')
<div class="row g-3">
    <div class="col-lg-4" id="issue">
        <div class="card p-3">
            <h2 class="h6 fw-bold">Issue license manually</h2>
            <p class="small text-muted">For schools that paid by UPI / cash. Renewals start when the current license ends.</p>
            <form method="POST" action="{{ route('admin.licenses.store') }}" class="row g-2">
                @csrf
                <input type="hidden" name="user_id" value="{{ old('user_id', $prefillUser?->id) }}">
                <div class="col-12"><label class="form-label small fw-semibold">Installation ID</label><input name="installation_id" value="{{ old('installation_id') }}" class="form-control font-monospace text-uppercase" placeholder="TDS-XXXX-XXXX-XXXX" required></div>
                <div class="col-12"><label class="form-label small fw-semibold">School name</label><input name="school_name" value="{{ old('school_name', $prefillUser?->school_name) }}" class="form-control" required></div>
                <div class="col-12"><label class="form-label small fw-semibold">Plan</label>
                    <select name="plan" class="form-select" onchange="document.getElementById('cd').classList.toggle('d-none', this.value !== 'custom')">
                        <option value="1y">1 Year</option><option value="2y">2 Years</option><option value="life">Lifetime</option><option value="custom">Custom expiry date</option>
                    </select>
                </div>
                <div class="col-12 d-none" id="cd"><label class="form-label small fw-semibold">Expiry date</label><input type="date" name="custom_date" class="form-control"></div>
                <div class="col-12"><label class="form-label small fw-semibold">Notes</label><input name="notes" value="{{ old('notes') }}" class="form-control" placeholder="UPI ref, amount…"></div>
                <div class="col-12"><button class="btn btn-navy w-100">Create key</button></div>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white">
                <form class="row g-2">
                    <div class="col-md-5"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="School or Installation ID"></div>
                    <div class="col-md-4"><select name="filter" class="form-select form-select-sm"><option value="">All</option><option value="expiring" @selected(request('filter') === 'expiring')>Expiring in 30 days</option><option value="expired" @selected(request('filter') === 'expired')>Expired</option></select></div>
                    <div class="col-auto"><button class="btn btn-sm btn-navy">Filter</button></div>
                </form>
            </div>
            <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                <thead><tr><th>Issued</th><th>School</th><th>Installation ID</th><th>Plan</th><th>Valid till</th><th>Source</th><th></th></tr></thead>
                <tbody>
                @forelse($licenses as $l)
                    <tr class="{{ session('new_license_id') === $l->id ? 'table-warning' : '' }}">
                        <td>{{ $l->created_at->format('d M Y') }}</td>
                        <td>@if($l->user)<a href="{{ route('admin.schools.show', $l->user) }}">{{ $l->school_name }}</a>@else{{ $l->school_name }}@endif<div class="small text-muted">{{ $l->notes }}</div></td>
                        <td class="font-monospace">{{ $l->installation_id }}</td>
                        <td>{{ \App\Services\LicenseIssuer::LABELS[$l->plan] ?? 'Custom' }}</td>
                        <td class="{{ $l->isActive() ? '' : 'text-danger' }}">{{ $l->expires_on?->format('d M Y') ?? 'Lifetime' }}</td>
                        <td>{{ ucfirst($l->source) }}</td>
                        <td class="text-nowrap">
                            <input type="hidden" id="k{{ $l->id }}" value="{{ $l->license_key }}">
                            <button class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('k{{ $l->id }}').value); this.textContent='Copied'">Copy key</button>
                            @if($l->user)<a class="btn btn-sm btn-outline-success" target="_blank" rel="noopener" href="https://wa.me/{{ strlen($d = preg_replace('/\D/', '', $l->user->phone)) === 10 ? '91'.$d : $d }}?text={{ rawurlencode("Your TDS School Management Software license key:\n\n".$l->license_key."\n\nOpen the software → License → paste the key → Activate.") }}"><i class="bi bi-whatsapp"></i></a>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No licenses yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
            @if($licenses->hasPages())<div class="card-footer bg-white">{{ $licenses->links('pagination::bootstrap-5') }}</div>@endif
        </div>
    </div>
</div>
@endsection
