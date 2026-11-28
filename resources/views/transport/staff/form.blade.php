@extends('layouts.app')
@section('title', $member->exists ? 'Edit '.$member->name : 'Add '.\App\Models\Transport\TransportStaff::TYPES[$member->type])
@section('content')
@php($S = \App\Models\Transport\TransportStaff::class)
@php($v = fn ($f) => old($f, $member->$f instanceof \Carbon\Carbon ? $member->$f->format('Y-m-d') : (in_array($f, ['duty_start', 'duty_end']) ? substr((string) $member->$f, 0, 5) : $member->$f)))
<form method="POST" action="{{ $member->exists ? route('transport.staff.update', $member) : route('transport.staff.store') }}" class="card" style="max-width:980px">
    @csrf @if($member->exists) @method('PUT') @endif
    <div class="card-header">Profile</div>
    <div class="card-body row g-3">
        <div class="col-md-3"><label class="form-label required">Role</label><select name="type" class="form-select" id="stType">@foreach($S::TYPES as $k => $l)<option value="{{ $k }}" @selected($v('type') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-5"><label class="form-label required">Full name</label><input name="name" value="{{ $v('name') }}" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Employee ID</label><input name="employee_id" value="{{ $v('employee_id') }}" class="form-control" placeholder="Leave empty for automatic (DRV-001)"></div>
        <div class="col-md-3"><label class="form-label">Mobile</label><input name="phone" value="{{ $v('phone') }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Other mobile</label><input name="alt_phone" value="{{ $v('alt_phone') }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Date of birth</label><input type="date" name="dob" value="{{ $v('dob') }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Joining date</label><input type="date" name="joining_date" value="{{ $v('joining_date') }}" class="form-control"></div>
        <div class="col-md-8"><label class="form-label">Address</label><input name="address" value="{{ $v('address') }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Aadhaar no.</label><input name="aadhaar_no" value="{{ $v('aadhaar_no') }}" class="form-control"></div>
    </div>
    <div class="card-header border-top driver-only">Driving licence <span class="text-muted small fw-normal">(reminder 30 days before expiry)</span></div>
    <div class="card-body row g-3 driver-only">
        <div class="col-md-4"><label class="form-label">Licence no.</label><input name="licence_no" value="{{ $v('licence_no') }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Licence type</label><input name="licence_type" value="{{ $v('licence_type') }}" class="form-control" placeholder="HMV / LMV / Transport"></div>
        <div class="col-md-4"><label class="form-label">Valid till</label><input type="date" name="licence_expiry" value="{{ $v('licence_expiry') }}" class="form-control"></div>
    </div>
    <div class="card-header border-top">Verification, duty & salary</div>
    <div class="card-body row g-3">
        <div class="col-md-3"><label class="form-label required">Police verification</label><select name="verification_status" class="form-select">@foreach($S::VERIFICATION as $k => $l)<option value="{{ $k }}" @selected($v('verification_status') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Verified on</label><input type="date" name="verified_on" value="{{ $v('verified_on') }}" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Verification note</label><input name="verification_note" value="{{ $v('verification_note') }}" class="form-control" placeholder="Police station, certificate no., medical check…"></div>
        <div class="col-md-2"><label class="form-label">Duty from</label><input type="time" name="duty_start" value="{{ $v('duty_start') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Duty to</label><input type="time" name="duty_end" value="{{ $v('duty_end') }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Assigned vehicle</label><select name="vehicle_id" class="form-select"><option value="">—</option>@foreach($vehicles as $veh)<option value="{{ $veh->id }}" @selected($v('vehicle_id') == $veh->id)>{{ $veh->label() }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Monthly salary (₹)</label><input type="number" step="0.01" min="0" name="monthly_salary" value="{{ $v('monthly_salary') }}" class="form-control"></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" rows="2" class="form-control">{{ $v('notes') }}</textarea></div>
    </div>
    <div class="card-footer d-flex gap-2"><a href="{{ route('transport.staff.index') }}" class="btn btn-outline-secondary">Cancel</a><button class="btn btn-primary ms-auto">Save</button></div>
</form>
@push('scripts')
<script>
(function () { const t = document.getElementById('stType'); const f = () => document.querySelectorAll('.driver-only').forEach(e => e.classList.toggle('d-none', t.value !== 'driver')); t.addEventListener('change', f); f(); })();
</script>
@endpush
@endsection
