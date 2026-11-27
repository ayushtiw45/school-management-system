@extends('layouts.app')
@section('title', $meta[0])
@section('content')
@php($filters = $meta[2])
<div class="card mb-3 no-print">
    <div class="card-body">
        <form class="row g-2 align-items-end" id="rf">
            @if(in_array('date', $filters))
                <div class="col-6 col-md-2"><label class="form-label">Date</label><input type="date" name="date" value="{{ request('date', now()->format('Y-m-d')) }}" class="form-control form-control-sm"></div>
            @endif
            @if(in_array('month', $filters))
                <div class="col-6 col-md-2"><label class="form-label">Month</label><input type="month" name="month" value="{{ request('month', now()->format('Y-m')) }}" class="form-control form-control-sm"></div>
            @endif
            @if(in_array('session', $filters))
                <div class="col-6 col-md-2"><label class="form-label">Session</label>
                    <select name="session_id" class="form-select form-select-sm">@foreach($sessions as $s)<option value="{{ $s->id }}" @selected(request('session_id', $currentSession?->id) == $s->id)>{{ $s->name }}</option>@endforeach</select>
                </div>
            @endif
            @if(in_array('from', $filters))
                <div class="col-6 col-md-2"><label class="form-label">From</label><input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm"></div>
                <div class="col-6 col-md-2"><label class="form-label">To</label><input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm"></div>
            @endif
            @if(in_array('upto', $filters))
                <div class="col-6 col-md-2"><label class="form-label">Due till</label><input type="date" name="upto" value="{{ request('upto') }}" class="form-control form-control-sm"></div>
            @endif
            @if(in_array('class', $filters))
                <div class="col-6 col-md-2"><label class="form-label">Class</label>
                    <select name="class_id" id="rClass" class="form-select form-select-sm"><option value="">All classes</option>@foreach($classes as $c)<option value="{{ $c->id }}" @selected(request('class_id') == $c->id)>{{ $c->name }}</option>@endforeach</select>
                </div>
            @endif
            @if(in_array('section', $filters))
                <div class="col-6 col-md-2"><label class="form-label">Section</label>
                    <select name="section_id" class="form-select form-select-sm" data-sections-for="#rClass" data-all-label="All sections"><option value="">All sections</option>@foreach($classes->firstWhere('id', request('class_id'))?->sections ?? [] as $sec)<option value="{{ $sec->id }}" @selected(request('section_id') == $sec->id)>{{ $sec->name }}</option>@endforeach</select>
                </div>
            @endif
            @if(in_array('method', $filters))
                <div class="col-6 col-md-2"><label class="form-label">Mode</label>
                    <select name="method" class="form-select form-select-sm"><option value="">All modes</option>@foreach(\App\Models\Payment::METHODS as $k => $l)<option value="{{ $k }}" @selected(request('method') === $k)>{{ $l }}</option>@endforeach</select>
                </div>
            @endif
            @if(in_array('fee_head', $filters))
                <div class="col-6 col-md-2"><label class="form-label">Fee Category</label>
                    <select name="fee_head_id" class="form-select form-select-sm"><option value="">All</option>@foreach($feeHeads as $h)<option value="{{ $h->id }}" @selected(request('fee_head_id') == $h->id)>{{ $h->name }}</option>@endforeach</select>
                </div>
            @endif
            @if(in_array('fee_status', $filters))
                <div class="col-6 col-md-2"><label class="form-label">Status</label>
                    <select name="fee_status" class="form-select form-select-sm">
                        @foreach(['all' => 'All', 'paid' => 'Paid', 'partial' => 'Partially paid', 'unpaid' => 'Unpaid', 'outstanding' => 'Unpaid + partial'] as $k => $l)<option value="{{ $k }}" @selected(request('fee_status', 'all') === $k)>{{ $l }}</option>@endforeach
                    </select>
                </div>
            @endif
            @if(in_array('expense_category', $filters))
                <div class="col-6 col-md-2"><label class="form-label">Category</label>
                    <select name="expense_category_id" class="form-select form-select-sm"><option value="">All</option>@foreach($expenseCategories as $c)<option value="{{ $c->id }}" @selected(request('expense_category_id') == $c->id)>{{ $c->name }}</option>@endforeach</select>
                </div>
            @endif
            <div class="col-auto"><button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Show</button></div>
            <div class="col-auto ms-auto d-flex gap-1">
                <a href="{{ request()->fullUrlWithQuery(['format' => 'xlsx']) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Excel</a>
                <a href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i></button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <div><div>{{ $report->title }}</div><div class="small text-muted fw-normal">{{ $report->subtitle }}</div></div>
        <a href="{{ route('reports.index') }}" class="ms-auto small no-print">All reports</a>
    </div>
    @if($report->summary)
        <div class="d-flex flex-wrap gap-2 p-3 border-bottom bg-light">
            @foreach($report->summary as $label => $value)
                <div class="bg-white border rounded px-3 py-2"><div class="small text-muted">{{ $label }}</div><div class="fw-semibold">{{ $value }}</div></div>
            @endforeach
        </div>
    @endif
    @include('reports.table', ['report' => $report])
</div>
@endsection
