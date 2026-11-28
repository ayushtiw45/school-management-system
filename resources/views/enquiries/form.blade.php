@extends('layouts.app')
@section('title', 'New Enquiry')
@section('content')
<div class="card" style="max-width:900px">
    <div class="card-body">
        <form method="POST" action="{{ route('enquiries.store') }}">
            @csrf
            @include('enquiries.fields')
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-save"></i> Save enquiry</button>
                <a href="{{ route('enquiries.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
