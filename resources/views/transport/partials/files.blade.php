{{-- Photos & documents. Vars: $files, $ownerType, $ownerId --}}
@php($canEdit = auth()->user()->canManageTransport())
<div class="card mb-3">
    <div class="card-header d-flex align-items-center">Photos & documents <span class="badge text-bg-light border ms-2">{{ $files->count() }}</span></div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 mb-2">
            @forelse($files as $f)
                <div class="border rounded-3 p-1 text-center" style="width:110px">
                    <a href="{{ route('transport.files.show', $f) }}" target="_blank" class="d-block text-decoration-none">
                        @if($f->isImage())
                            <img src="{{ route('transport.files.show', $f) }}" alt="" style="width:100%;height:72px;object-fit:cover;border-radius:6px">
                        @else
                            <div class="d-flex align-items-center justify-content-center bg-light rounded" style="height:72px"><i class="bi bi-file-earmark-pdf fs-2 text-danger"></i></div>
                        @endif
                        <div class="small text-truncate text-body mt-1" title="{{ $f->title }}">{{ $f->title }}</div>
                    </a>
                    @if($canEdit)<form method="POST" action="{{ route('transport.files.destroy', $f) }}" data-confirm="Delete this file?">@csrf @method('DELETE')<button class="btn btn-link btn-sm text-danger p-0 small">Delete</button></form>@endif
                </div>
            @empty
                <div class="text-muted small">No photos or documents yet.</div>
            @endforelse
        </div>
        @if($canEdit)
        <form method="POST" action="{{ route('transport.files.store') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
            @csrf<input type="hidden" name="owner_type" value="{{ $ownerType }}"><input type="hidden" name="owner_id" value="{{ $ownerId }}">
            <div class="col-sm-3"><select name="kind" class="form-select form-select-sm"><option value="photo">Photo</option><option value="document">Document</option></select></div>
            <div class="col-sm-4"><input name="title" class="form-control form-control-sm" placeholder="Name, e.g. RC, Insurance copy"></div>
            <div class="col-sm-5 d-flex gap-1"><input type="file" name="files[]" multiple accept=".pdf,image/*" class="form-control form-control-sm" required><button class="btn btn-sm btn-primary">Upload</button></div>
        </form>
        @endif
    </div>
</div>
