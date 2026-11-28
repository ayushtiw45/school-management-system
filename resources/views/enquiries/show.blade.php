@extends('layouts.app')
@section('title', 'Enquiry: '.$enquiry->child_name)
@section('content')
@php($digits = substr(preg_replace('/\D/', '', $enquiry->phone), -10))
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-start">
                    <div>
                        <h2 class="h5 fw-bold mb-1">{{ $enquiry->child_name }}</h2>
                        <div class="text-muted small">{{ $enquiry->schoolClass?->name ?? 'Class not decided' }} · {{ \App\Models\Enquiry::SOURCES[$enquiry->source] ?? $enquiry->source }}</div>
                    </div>
                    <div class="ms-auto">@include('enquiries.status', ['status' => $enquiry->status])</div>
                </div>
                <dl class="row small mt-3 mb-0">
                    <dt class="col-5">Parent</dt><dd class="col-7">{{ $enquiry->parent_name ?: '—' }}</dd>
                    <dt class="col-5">Mobile</dt><dd class="col-7"><a href="tel:{{ $enquiry->phone }}">{{ $enquiry->phone }}</a>@if($enquiry->alt_phone), {{ $enquiry->alt_phone }}@endif</dd>
                    @if($enquiry->email)<dt class="col-5">Email</dt><dd class="col-7">{{ $enquiry->email }}</dd>@endif
                    @if($enquiry->dob)<dt class="col-5">Date of birth</dt><dd class="col-7">{{ $enquiry->dob->format('d M Y') }}</dd>@endif
                    @if($enquiry->previous_school)<dt class="col-5">Previous school</dt><dd class="col-7">{{ $enquiry->previous_school }}</dd>@endif
                    <dt class="col-5">Next follow-up</dt><dd class="col-7 {{ $enquiry->followUpDue() ? 'text-danger fw-semibold' : '' }}">{{ $enquiry->next_follow_up?->format('d M Y') ?? '—' }}</dd>
                    <dt class="col-5">Added</dt><dd class="col-7">{{ $enquiry->created_at->format('d M Y, h:i A') }} by {{ $enquiry->creator?->name }}</dd>
                </dl>
                @if($enquiry->notes)<div class="mt-2 p-2 bg-light rounded small" style="white-space:pre-line">{{ $enquiry->notes }}</div>@endif
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a href="tel:{{ $enquiry->phone }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-telephone"></i> Call</a>
                    <a href="https://wa.me/91{{ $digits }}?text={{ rawurlencode('Namaste '.($enquiry->parent_name ?: '').', thank you for your enquiry at '.$brandSchool->displayName().'. Online admission form: '.route('apply', $brandSchool)) }}" target="_blank" rel="noopener" class="btn btn-sm btn-success"><i class="bi bi-whatsapp"></i> Send form on WhatsApp</a>
                    @if($enquiry->student)
                        <a href="{{ route('students.show', $enquiry->student) }}" class="btn btn-sm btn-outline-success"><i class="bi bi-person-check"></i> View admission</a>
                    @elseif($enquiry->status !== 'converted')
                        <a href="{{ route('admissions.create', ['enquiry' => $enquiry->id]) }}" class="btn btn-sm btn-primary"><i class="bi bi-person-plus"></i> Start admission</a>
                    @endif
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#editEnq"><i class="bi bi-pencil"></i> Edit</button>
                </div>
            </div>
        </div>
        <div class="card collapse {{ $errors->any() && old('child_name') ? 'show' : '' }}" id="editEnq">
            <div class="card-body">
                <form method="POST" action="{{ route('enquiries.update', $enquiry) }}">
                    @csrf @method('PUT')
                    @include('enquiries.fields', ['withStatus' => true])
                    <button class="btn btn-primary mt-3">Save changes</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        @if($enquiry->isOpen())
        <div class="card mb-3">
            <div class="card-header">Add follow-up</div>
            <div class="card-body">
                <form method="POST" action="{{ route('enquiries.followup', $enquiry) }}" class="row g-2">
                    @csrf
                    <div class="col-12"><textarea name="note" rows="2" class="form-control" placeholder="What did the parent say? e.g. Will visit on Saturday with documents" required>{{ old('note') }}</textarea></div>
                    <div class="col-sm-5"><label class="form-label small mb-0">Next follow-up</label><input type="date" name="next_follow_up" value="{{ now()->addDays(3)->format('Y-m-d') }}" class="form-control"></div>
                    <div class="col-sm-4"><label class="form-label small mb-0">Status</label>
                        <select name="status" class="form-select"><option value="follow_up">Keep following up</option><option value="not_interested">Not interested (close)</option></select></div>
                    <div class="col-sm-3 d-flex align-items-end"><button class="btn btn-primary w-100">Save</button></div>
                </form>
            </div>
        </div>
        @endif
        <div class="card">
            <div class="card-header">Follow-up history</div>
            <ul class="list-group list-group-flush">
                @forelse($enquiry->followUps as $f)
                    <li class="list-group-item">
                        <div class="small text-muted">{{ $f->created_at->format('d M Y, h:i A') }} · {{ $f->user?->name }} @if($f->next_follow_up)· next: {{ $f->next_follow_up->format('d M Y') }}@endif</div>
                        <div style="white-space:pre-line">{{ $f->note }}</div>
                    </li>
                @empty
                    <li class="list-group-item text-muted">No follow-ups yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
