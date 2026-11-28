@extends('layouts.app')
@section('title', $meta[0])
@section('content')
<form class="card mb-3 no-print"><div class="card-body d-flex flex-wrap gap-2 align-items-end py-2">
    @if($meta[2] === 'month')<div><label class="form-label small mb-0">Month</label><input type="month" name="month" value="{{ $month }}" class="form-control form-control-sm"></div><button class="btn btn-sm btn-primary">Show</button>@endif
    <div class="ms-auto d-flex gap-1">
        <a href="{{ request()->fullUrlWithQuery(['format' => 'xlsx']) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        <a href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
    </div>
</div></form>
<div class="d-flex align-items-baseline gap-2 mb-2"><h2 class="h5 fw-bold mb-0">{{ $report->title }}</h2><span class="text-muted small">{{ $report->subtitle }}</span></div>
@if($report->summary)<div class="row g-2 mb-3">@foreach($report->summary as $l => $v)<div class="col-auto"><div class="card px-3 py-2"><div class="small text-muted">{{ $l }}</div><div class="fw-semibold">{{ $v }}</div></div></div>@endforeach</div>@endif
@include('reports.table', ['report' => $report])
@endsection
