@extends('layouts.app')
@section('title', 'New Admission')
@section('content')
<form method="POST" action="{{ route('admissions.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="alert alert-light border d-flex flex-wrap gap-3 align-items-center">
        <div><span class="text-muted small">Session</span><br><strong>{{ $session->name }}</strong></div>
        <div><span class="text-muted small">Admission No. (auto)</span><br><strong>{{ $nextNo }}</strong></div>
    </div>
    @include('partials.student-fields', ['student' => null, 'classes' => $classes])
    <div class="card"><div class="card-body d-flex flex-wrap gap-3 align-items-center">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="confirm_now" value="1" id="confirmNow" checked>
            <label class="form-check-label" for="confirmNow">Confirm admission now and assign fees from the fee structure</label>
        </div>
        <button class="btn btn-primary ms-auto"><i class="bi bi-check2-circle"></i> Save Admission</button>
    </div></div>
</form>
@endsection
