@extends('layouts.app')
@section('title', 'Transport Reports')
@section('content')
<div class="row g-3">
    @foreach($reports as $k => [$title, $desc])
        <div class="col-md-6 col-xl-4"><a href="{{ route('transport.reports.show', $k) }}" class="card p-3 h-100 text-decoration-none">
            <div class="fw-semibold text-body">{{ $title }}</div><div class="small text-muted">{{ $desc }}</div></a></div>
    @endforeach
</div>
<p class="small text-muted mt-3">Income = transport fee actually collected from students on each route. Costs = fuel + maintenance + driver/conductor salary + EMI + rent + insurance + other transport expenses. Every report opens in Excel or PDF too.</p>
@endsection
