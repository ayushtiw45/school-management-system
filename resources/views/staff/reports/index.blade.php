@extends('layouts.app')
@section('title', 'Staff Reports')
@section('content')
<div class="row g-3">
    @foreach($reports as $k => [$title, $desc])
        <div class="col-md-6 col-xl-4"><a href="{{ route('staff.reports.show', $k) }}" class="card p-3 h-100 text-decoration-none"><div class="fw-semibold text-body">{{ $title }}</div><div class="small text-muted">{{ $desc }}</div></a></div>
    @endforeach
    <div class="col-md-6 col-xl-4"><a href="{{ route('staff.attendance.monthly') }}" class="card p-3 h-100 text-decoration-none"><div class="fw-semibold text-body">Attendance Month Sheet</div><div class="small text-muted">Day-by-day P/A/L grid for printing.</div></a></div>
    <div class="col-md-6 col-xl-4"><a href="{{ route('staff.idcards') }}" target="_blank" class="card p-3 h-100 text-decoration-none"><div class="fw-semibold text-body">Staff ID Cards</div><div class="small text-muted">Print ID cards of all staff.</div></a></div>
</div>
@endsection
