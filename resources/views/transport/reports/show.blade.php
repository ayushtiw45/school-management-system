@extends('layouts.app')
@section('title', $meta[0])
@section('content')
<form class="card mb-3 no-print"><div class="card-body row g-2 align-items-end py-2">
    @if($meta[2] === 'range')
        <div class="col-6 col-md-2"><label class="form-label small mb-0">From</label><input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-0">To</label><input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="form-control form-control-sm"></div>
    @elseif($meta[2] === 'year')
        <div class="col-6 col-md-3"><label class="form-label small mb-0">Session</label><select name="session_id" class="form-select form-select-sm">@foreach($sessions as $s)<option value="{{ $s->id }}" @selected($session?->id === $s->id)>{{ $s->name }}</option>@endforeach</select></div>
    @endif
    @if($meta[2] !== 'none')<div class="col-auto"><button class="btn btn-sm btn-primary">Show</button></div>@endif
    <div class="col-auto ms-auto d-flex gap-1">
        <a href="{{ request()->fullUrlWithQuery(['format' => 'xlsx']) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Excel</a>
        <a href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
    </div>
</div></form>
<div class="d-flex align-items-baseline gap-2 mb-2"><h2 class="h5 fw-bold mb-0">{{ $report->title }}</h2><span class="text-muted small">{{ $report->subtitle }}</span></div>
@if($report->summary)
    <div class="row g-2 mb-3">@foreach($report->summary as $label => $value)<div class="col-auto"><div class="card px-3 py-2"><div class="small text-muted">{{ $label }}</div><div class="fw-semibold">{{ $value }}</div></div></div>@endforeach</div>
@endif
@include('reports.table', ['report' => $report])
@endsection
