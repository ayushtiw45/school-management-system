@extends('layouts.admin')
@section('title', 'Signing Key')
@section('content')
<div class="card p-4" style="max-width:820px">
    <h1 class="h5 fw-bold">License signing key</h1>
    <p>The Windows installer must contain this <b>public key</b> so the software accepts the license keys this website creates.</p>
    <ol class="small">
        <li>Copy the public key below.</li>
        <li>GitHub → your repository → Settings → Secrets and variables → Actions → <b>Variables</b> → New variable: name <code>TDS_LICENSE_PUBLIC_KEY</code>, value: the key.</li>
        <li>Also add <code>TDS_VENDOR_WEBSITE</code> = <code>{{ url('/') }}</code> so the software's "Buy online" button opens this website.</li>
        <li>Publish a new version (tag <code>v1.0.0</code>). Every installer built after this accepts your keys.</li>
    </ol>
    <div class="input-group mb-4">
        <input class="form-control font-monospace" id="pk" value="{{ $publicKey }}" readonly>
        <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('pk').value); this.textContent='Copied'">Copy</button>
    </div>
    <h2 class="h6 fw-bold">Backup</h2>
    <p class="small text-muted">The backup file contains the <b>secret</b> signing key. Without it you cannot create keys for existing installations if this website is lost. Store it offline (pen drive / password manager). Never send it on WhatsApp or email.</p>
    <a href="{{ route('admin.key.backup') }}" class="btn btn-outline-dark">Download backup</a>
</div>
@endsection
