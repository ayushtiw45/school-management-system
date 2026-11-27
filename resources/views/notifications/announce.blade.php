@extends('layouts.app')
@section('title', 'Send Announcement')
@section('content')
<form method="POST" action="{{ route('notifications.announce.send') }}" class="card" style="max-width:760px">
    @csrf
    <div class="card-body row g-3">
        <div class="col-md-4">
            <label class="form-label">Send to</label>
            <select name="class_id" id="aClass" class="form-select"><option value="">All parents (active students)</option>@foreach($classes as $c)<option value="{{ $c->id }}" @selected(old('class_id') == $c->id)>{{ $c->name }}</option>@endforeach</select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Section</label>
            <select name="section_id" class="form-select" data-sections-for="#aClass" data-all-label="All sections"><option value="">All sections</option></select>
        </div>
        <div class="col-md-4">
            <label class="form-label required">Channel</label>
            <select name="channel" class="form-select"><option value="sms">SMS</option><option value="whatsapp">WhatsApp</option><option value="both">SMS + WhatsApp</option></select>
        </div>
        <div class="col-12">
            <label class="form-label required">Message</label>
            <textarea name="message" rows="5" maxlength="1000" class="form-control" id="msg" required placeholder="Dear Parent, school will remain closed on ... - {school}">{{ old('message') }}</textarea>
            <div class="form-text d-flex"><span>You can use {student}, {class}, {school}. One message per parent mobile (siblings get one).</span><span class="ms-auto" id="len">0</span></div>
        </div>
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary" onclick="return confirm('Send this announcement?')"><i class="bi bi-send"></i> Send Announcement</button></div>
</form>
@push('scripts')
<script>
    const m = document.getElementById('msg'), l = document.getElementById('len');
    const upd = () => l.textContent = m.value.length + ' characters' + (m.value.length > 160 ? ' (' + Math.ceil(m.value.length / 153) + ' SMS)' : '');
    m.addEventListener('input', upd); upd();
</script>
@endpush
@endsection
