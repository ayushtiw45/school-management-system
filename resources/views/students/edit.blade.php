@extends('layouts.app')
@section('title', 'Edit: '.$student->full_name)
@section('content')
<form method="POST" action="{{ route('students.update', $student) }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    <div class="alert alert-light border"><span class="text-muted small">Admission No.</span> <strong>{{ $student->admission_no }}</strong></div>
    @include('partials.student-fields', ['student' => $student, 'classes' => $classes])
    <div class="d-flex gap-2">
        <a href="{{ route('students.show', $student) }}" class="btn btn-outline-secondary">Cancel</a>
        <button class="btn btn-primary ms-auto"><i class="bi bi-save"></i> Save Changes</button>
    </div>
</form>
@endsection
