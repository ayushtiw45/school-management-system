@extends('layouts.app')
@section('title', 'Import & Export')
@section('content')
@php($admin = auth()->user()->isAdmin())
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center"><span><i class="bi bi-download"></i> Download data (Excel)</span>
                <a href="{{ route('data.export.all') }}" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-file-zip"></i> Download everything</a></div>
            <ul class="list-group list-group-flush">
                @foreach($exports as $k => [$title, $desc])
                    <li class="list-group-item d-flex align-items-center gap-2">
                        <div><div class="fw-semibold">{{ $title }}</div><div class="small text-muted">{{ $desc }}</div></div>
                        <a href="{{ route('data.export', $k) }}" class="btn btn-sm btn-outline-success ms-auto text-nowrap"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                    </li>
                @endforeach
            </ul>
            <div class="card-footer small text-muted">Keep a copy on your computer every month. "Download everything" gives one zip with all the files.</div>
        </div>
    </div>
    <div class="col-lg-7">
        @if($admin)
            <div class="alert alert-light border small mb-3"><strong>How to import:</strong> 1) download the template → 2) fill one row per student / staff in Excel (keep the heading row) → 3) upload it → 4) check the preview: rows with a problem are shown in red and skipped → 5) click Import. Dates can be written as DD-MM-YYYY.</div>
            @foreach($imports as $k => [$title, $desc, $cols])
                <div class="card mb-3">
                    <div class="card-header d-flex align-items-center"><span><i class="bi bi-upload"></i> Import {{ strtolower($title) }}</span>
                        <a href="{{ route('data.template', $k) }}" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-file-earmark-arrow-down"></i> Template</a></div>
                    <div class="card-body">
                        <p class="small text-muted mb-2">{{ $desc }}</p>
                        <p class="small mb-2"><strong>Columns:</strong> @foreach($cols as $c){{ $c[0] }}@if($c[1])<span class="text-danger">*</span>@endif{{ $loop->last ? '' : ', ' }}@endforeach</p>
                        <form method="POST" action="{{ route('data.upload', $k) }}" enctype="multipart/form-data" class="d-flex gap-2">
                            @csrf<input type="file" name="file" accept=".xlsx,.csv" class="form-control form-control-sm" required><button class="btn btn-sm btn-primary text-nowrap">Upload & check</button>
                        </form>
                    </div>
                </div>
            @endforeach
        @else
            <div class="card card-body text-muted">Only the school admin can import data.</div>
        @endif
    </div>
</div>
@endsection
