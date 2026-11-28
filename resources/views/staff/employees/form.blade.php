@extends('layouts.app')
@section('title', $employee->exists ? 'Edit '.$employee->name : ($employee->type === 'teaching' ? 'Add Teacher' : 'Add Staff'))
@section('content')
@php($E = \App\Models\Hr\Employee::class)
@php($v = fn ($f) => old($f, $employee->$f instanceof \Carbon\Carbon ? $employee->$f->format('Y-m-d') : $employee->$f))
<form method="POST" action="{{ $employee->exists ? route('staff.employees.update', $employee) : route('staff.employees.store') }}" enctype="multipart/form-data" class="card" style="max-width:1000px">
    @csrf @if($employee->exists) @method('PUT') @endif
    <div class="card-header">Personal details</div>
    <div class="card-body row g-3">
        <div class="col-md-3"><label class="form-label required">Type</label><select name="type" class="form-select">@foreach($E::TYPES as $k => $l)<option value="{{ $k }}" @selected($v('type') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-5"><label class="form-label required">Full name</label><input name="name" value="{{ $v('name') }}" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Employee ID</label><input name="employee_id" value="{{ $v('employee_id') }}" class="form-control" placeholder="Empty = automatic (TCH-001)"></div>
        <div class="col-md-2"><label class="form-label">Gender</label><select name="gender" class="form-select"><option value="">—</option>@foreach(['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $k => $l)<option value="{{ $k }}" @selected($v('gender') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">Date of birth</label><input type="date" name="dob" value="{{ $v('dob') }}" class="form-control"></div>
        <div class="col-md-2"><label class="form-label">Blood group</label><input name="blood_group" value="{{ $v('blood_group') }}" class="form-control"></div>
        <div class="col-md-5"><label class="form-label">Photo</label><input type="file" name="photo" accept="image/*" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Mobile</label><input name="phone" value="{{ $v('phone') }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Other mobile</label><input name="alt_phone" value="{{ $v('alt_phone') }}" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" value="{{ $v('email') }}" class="form-control"></div>
        <div class="col-12"><label class="form-label">Address</label><input name="address" value="{{ $v('address') }}" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Emergency contact name</label><input name="emergency_name" value="{{ $v('emergency_name') }}" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Emergency contact mobile</label><input name="emergency_phone" value="{{ $v('emergency_phone') }}" class="form-control"></div>
    </div>
    <div class="card-header border-top">Job</div>
    <div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label">Designation</label><input name="designation" value="{{ $v('designation') }}" class="form-control" list="desigs"><datalist id="desigs">@foreach($E::DESIGNATIONS as $d)<option>{{ $d }}</option>@endforeach</datalist></div>
        <div class="col-md-4"><label class="form-label">Department</label><input name="department" value="{{ $v('department') }}" class="form-control" list="depts"><datalist id="depts">@foreach($departments as $d)<option>{{ $d }}</option>@endforeach</datalist></div>
        <div class="col-md-4"><label class="form-label">Joining date</label><input type="date" name="joining_date" value="{{ $v('joining_date') }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label required">Employment</label><select name="employment_type" class="form-select">@foreach($E::EMPLOYMENT as $k => $l)<option value="{{ $k }}" @selected($v('employment_type') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Contract / probation ends</label><input type="date" name="contract_end" value="{{ $v('contract_end') }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Experience before joining (years)</label><input type="number" step="0.5" min="0" name="experience_years" value="{{ $v('experience_years') }}" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Qualification</label><input name="qualification" value="{{ $v('qualification') }}" class="form-control" placeholder="M.Sc. B.Ed."></div>
        <div class="col-md-6"><label class="form-label">Specialization / subjects</label><input name="specialization" value="{{ $v('specialization') }}" class="form-control" placeholder="Mathematics, Physics"></div>
    </div>
    <div class="card-header border-top">IDs & bank <span class="text-muted small fw-normal">(for salary)</span></div>
    <div class="card-body row g-3">
        <div class="col-md-3"><label class="form-label">Aadhaar</label><input name="aadhaar_no" value="{{ $v('aadhaar_no') }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">PAN</label><input name="pan_no" value="{{ $v('pan_no') }}" class="form-control text-uppercase"></div>
        <div class="col-md-3"><label class="form-label">PF UAN</label><input name="uan_no" value="{{ $v('uan_no') }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">ESI no.</label><input name="esi_no" value="{{ $v('esi_no') }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Bank</label><input name="bank_name" value="{{ $v('bank_name') }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Account no.</label><input name="bank_account" value="{{ $v('bank_account') }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">IFSC</label><input name="bank_ifsc" value="{{ $v('bank_ifsc') }}" class="form-control text-uppercase"></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" rows="2" class="form-control">{{ $v('notes') }}</textarea></div>
    </div>
    <div class="card-footer d-flex gap-2"><span class="small text-muted align-self-center">Salary structure is set on the profile page (Admin / Accounts).</span>
        <a href="{{ $employee->exists ? route('staff.employees.show', $employee) : route('staff.employees.index') }}" class="btn btn-outline-secondary ms-auto">Cancel</a><button class="btn btn-primary">Save</button></div>
</form>
@endsection
