@extends('layouts.app')
@section('title', 'Reports')
@section('content')
@php($icons = ['daily' => 'bi-calendar-day', 'monthly' => 'bi-calendar-month', 'datewise' => 'bi-calendar-range', 'classwise' => 'bi-diagram-3', 'summary' => 'bi-clipboard-data', 'studentwise' => 'bi-person-lines-fill', 'pending' => 'bi-hourglass-split', 'paid_unpaid' => 'bi-check2-square', 'discount' => 'bi-percent', 'expenses' => 'bi-journal-minus', 'expense_monthly' => 'bi-graph-down'])
@foreach($reports as $group => $items)
    <h2 class="h6 text-uppercase text-muted fw-semibold mt-3 mb-2" style="letter-spacing:.08em">{{ $group }}</h2>
    <div class="row g-3">
        @foreach($items as $key => [$title, $desc])
            <div class="col-md-6 col-xl-4">
                <a href="{{ route('reports.show', $key) }}" class="card h-100 text-decoration-none text-body">
                    <div class="card-body d-flex gap-3">
                        <div class="stat"><div class="icon bg-primary-subtle text-primary"><i class="bi {{ $icons[$key] ?? 'bi-file-earmark' }}"></i></div></div>
                        <div><div class="fw-semibold">{{ $title }}</div><div class="small text-muted">{{ $desc }}</div></div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endforeach
<p class="small text-muted mt-3 mb-0">Every report can be downloaded as Excel (.xlsx) or PDF.</p>
@endsection
