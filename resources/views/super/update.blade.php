@extends('layouts.super')
@section('title', 'Software Update')
@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card p-4 mb-3">
            <div class="text-muted small">Current version</div>
            <div class="display-6 fw-bold" style="font-family:'Bricolage Grotesque',sans-serif">{{ $version }}</div>
        </div>

        @if($pending)
            <div class="card p-4 mb-3 border-2" style="border-color:#FF4B32">
                <h2 class="h5 fw-bold">Ready to update: {{ $version }} → {{ $pending['version'] }}</h2>
                <div class="small text-muted mb-2">{{ $pending['files'] }} files · built {{ \Illuminate\Support\Carbon::parse($pending['built_at'] ?? now())->format('d M Y, h:i A') }} · {{ ! empty($pending['vendor']) ? 'full package (with libraries)' : 'code only' }}</div>
                @if(! empty($pending['notes']))
                    <div class="small fw-semibold mt-2">What's new</div>
                    <ul class="small mb-3">@foreach((array) $pending['notes'] as $n)<li>{{ $n }}</li>@endforeach</ul>
                @endif
                <div class="alert alert-light border small mb-3">
                    Before updating, a backup of the code and the full database is saved automatically. The website shows a short
                    "maintenance" page for about a minute. School data, uploaded files and your settings are not touched.
                </div>
                <div class="d-flex gap-2">
                    <form method="POST" action="{{ route('super.update.apply') }}" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='Updating… please wait, do not close this page';">
                        @csrf<button class="btn btn-lg text-white fw-semibold" style="background:#FF4B32;border-radius:999px"><i class="bi bi-arrow-repeat"></i> Update now</button>
                    </form>
                    <form method="POST" action="{{ route('super.update.cancel') }}">@csrf<button class="btn btn-lg btn-outline-secondary" style="border-radius:999px">Cancel</button></form>
                </div>
            </div>
        @else
            <div class="card p-4 mb-3">
                <h2 class="h6 fw-bold">Upload an update file</h2>
                <p class="small text-muted">Choose the <code>tds-update-x.y.z.zip</code> file you received, enter your password and click Check. Nothing changes until you confirm on the next step.</p>
                <form method="POST" action="{{ route('super.update.upload') }}" enctype="multipart/form-data" class="row g-2">
                    @csrf
                    <div class="col-12"><input type="file" name="package" accept=".zip" class="form-control" required></div>
                    <div class="col-12"><input type="password" name="password" class="form-control" placeholder="Your owner password" required autocomplete="current-password"></div>
                    <div class="col-12"><button class="btn btn-navy w-100"><i class="bi bi-shield-check"></i> Upload & check</button></div>
                </form>
                <p class="small text-muted mt-3 mb-0">Server upload limit: {{ $limits['upload'] }} (post {{ $limits['post'] }}). If a file is bigger, raise <code>upload_max_filesize</code> and <code>post_max_size</code> in hPanel → PHP Configuration → PHP options.</p>
            </div>
        @endif

        <div class="card p-3">
            <h2 class="h6 fw-bold">Backups</h2>
            <p class="small text-muted">The last 3 code and database backups are kept on the server. Download them to keep a copy on your computer.</p>
            <ul class="list-group list-group-flush small">
                @forelse($backups as $b)
                    <li class="list-group-item d-flex align-items-center px-0">
                        <i class="bi {{ str_starts_with($b['name'], 'database') ? 'bi-database' : 'bi-file-zip' }} me-2"></i>
                        <span class="text-truncate">{{ $b['name'] }}</span>
                        <span class="text-muted ms-2 text-nowrap">{{ number_format($b['size'] / 1048576, 1) }} MB</span>
                        <a href="{{ route('super.backups.download', $b['name']) }}" class="ms-auto">Download</a>
                    </li>
                @empty
                    <li class="list-group-item px-0 text-muted">No backups yet. One is made before every update.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header bg-white">Update history</div>
            <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                <thead><tr><th>Date</th><th>Action</th><th>Version</th><th>Status</th><th>By</th><th></th></tr></thead>
                <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="text-nowrap small">{{ $log->created_at->format('d M Y, h:i A') }}</td>
                        <td>{{ ucfirst($log->action) }}</td>
                        <td class="text-nowrap">{{ $log->from_version }} → {{ $log->to_version }}</td>
                        <td><span class="badge {{ $log->status === 'success' ? 'text-bg-success' : 'text-bg-danger' }}">{{ ucfirst($log->status) }}</span>
                            @if($log->message)<div class="small text-muted" style="max-width:260px;white-space:pre-line">{{ \Illuminate\Support\Str::limit($log->message, 200) }}</div>@endif</td>
                        <td class="small">{{ $log->user?->name }}</td>
                        <td>
                            @if($log->action === 'update' && $log->status === 'success' && $log->code_backup && is_file(\App\Services\Updater::backupDir().'/'.$log->code_backup))
                                <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="collapse" data-bs-target="#rb{{ $log->id }}">Undo</button>
                                <form method="POST" action="{{ route('super.update.rollback', $log) }}" class="collapse mt-2" id="rb{{ $log->id }}">
                                    @csrf
                                    <div class="small text-muted mb-1">Puts back the code from before this update (version {{ $log->from_version }}). The database is not changed.</div>
                                    <div class="input-group input-group-sm"><input type="password" name="password" class="form-control" placeholder="Password" required><button class="btn btn-danger">Undo update</button></div>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No updates yet.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection
