{{-- Shared admission / edit form. Vars: $student (Student|null), $classes, $public (bool) --}}
@php
    $s = $student ?? new \App\Models\Student();
    $public = $public ?? false;
    $v = fn ($f) => old($f, $s->$f instanceof \Carbon\Carbon ? $s->$f->format('Y-m-d') : $s->$f);
@endphp

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-mortarboard me-1"></i> Admission Details</div>
    <div class="card-body row g-3">
        @unless($public)
            <div class="col-md-3">
                <label class="form-label required">Admission Date</label>
                <input type="date" name="admission_date" class="form-control" value="{{ $v('admission_date') ?: now()->format('Y-m-d') }}" required>
            </div>
        @endunless
        <div class="col-md-3">
            <label class="form-label required">Class</label>
            <select name="school_class_id" id="classSelect" class="form-select" required>
                <option value="">Select class</option>
                @foreach($classes as $c)
                    <option value="{{ $c->id }}" @selected($v('school_class_id') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        @unless($public)
            <div class="col-md-3">
                <label class="form-label">Section</label>
                <select name="section_id" class="form-select" data-sections-for="#classSelect">
                    <option value="">Select section</option>
                    @foreach($classes->firstWhere('id', $v('school_class_id'))?->sections ?? [] as $sec)
                        <option value="{{ $sec->id }}" @selected($v('section_id') == $sec->id)>{{ $sec->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Roll No.</label>
                <input name="roll_no" class="form-control" value="{{ $v('roll_no') }}">
            </div>
        @endunless
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-person me-1"></i> Student Personal Details</div>
    <div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label required">First Name</label><input name="first_name" class="form-control" value="{{ $v('first_name') }}" required></div>
        <div class="col-md-4"><label class="form-label">Last Name</label><input name="last_name" class="form-control" value="{{ $v('last_name') }}"></div>
        <div class="col-md-4">
            <label class="form-label required">Gender</label>
            <select name="gender" class="form-select" required>
                <option value="">Select</option>
                @foreach(['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $k => $l)
                    <option value="{{ $k }}" @selected($v('gender') === $k)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3"><label class="form-label required">Date of Birth</label><input type="date" name="dob" class="form-control" value="{{ $v('dob') }}" required></div>
        <div class="col-md-3">
            <label class="form-label">Blood Group</label>
            <select name="blood_group" class="form-select">
                <option value="">—</option>
                @foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg)<option @selected($v('blood_group') === $bg)>{{ $bg }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3"><label class="form-label">Religion</label><input name="religion" class="form-control" value="{{ $v('religion') }}"></div>
        <div class="col-md-3">
            <label class="form-label">Category</label>
            <select name="category" class="form-select">
                <option value="">—</option>
                @foreach(['General','OBC','SC','ST','EWS','Other'] as $cat)<option @selected($v('category') === $cat)>{{ $cat }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-6"><label class="form-label">Address</label><textarea name="address" rows="2" class="form-control">{{ $v('address') }}</textarea></div>
        <div class="col-md-2"><label class="form-label">City</label><input name="city" class="form-control" value="{{ $v('city') }}"></div>
        <div class="col-md-2"><label class="form-label">State</label><input name="state" class="form-control" value="{{ $v('state') }}"></div>
        <div class="col-md-2"><label class="form-label">PIN Code</label><input name="pincode" class="form-control" value="{{ $v('pincode') }}"></div>
        <div class="col-md-6">
            <label class="form-label">Student Photograph</label>
            <input type="file" name="photo" accept="image/*" class="form-control">
            <div class="form-text">JPG/PNG, max 2 MB. @if($s->photo) Current photo will be replaced. @endif</div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-people me-1"></i> Parent / Guardian Details</div>
    <div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label required">Father's Name</label><input name="father_name" class="form-control" value="{{ $v('father_name') }}" required></div>
        <div class="col-md-4"><label class="form-label required">Father's Mobile</label><input name="father_phone" class="form-control" value="{{ $v('father_phone') }}" required></div>
        <div class="col-md-4"><label class="form-label">Father's Occupation</label><input name="father_occupation" class="form-control" value="{{ $v('father_occupation') }}"></div>
        <div class="col-md-4"><label class="form-label">Mother's Name</label><input name="mother_name" class="form-control" value="{{ $v('mother_name') }}"></div>
        <div class="col-md-4"><label class="form-label">Mother's Mobile</label><input name="mother_phone" class="form-control" value="{{ $v('mother_phone') }}"></div>
        <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ $v('email') }}"></div>
        <div class="col-md-4"><label class="form-label">Guardian Name <span class="text-muted fw-normal">(if other than parents)</span></label><input name="guardian_name" class="form-control" value="{{ $v('guardian_name') }}"></div>
        <div class="col-md-4"><label class="form-label">Relation</label><input name="guardian_relation" class="form-control" value="{{ $v('guardian_relation') }}"></div>
        <div class="col-md-4"><label class="form-label">Guardian Mobile</label><input name="guardian_phone" class="form-control" value="{{ $v('guardian_phone') }}"></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-building me-1"></i> Previous School Details</div>
    <div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label">Previous School Name</label><input name="previous_school" class="form-control" value="{{ $v('previous_school') }}"></div>
        <div class="col-md-3"><label class="form-label">Last Class Passed</label><input name="previous_class" class="form-control" value="{{ $v('previous_class') }}"></div>
        <div class="col-md-3"><label class="form-label">TC Number</label><input name="tc_number" class="form-control" value="{{ $v('tc_number') }}"></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-paperclip me-1"></i> Documents <span class="text-muted fw-normal small">(Birth certificate, Aadhaar, TC, report card… PDF/JPG/PNG, max 4 MB each)</span></div>
    <div class="card-body row g-2">
        @foreach(range(0, 2) as $i)
            <div class="col-md-5"><input name="documents[{{ $i }}][title]" class="form-control" placeholder="Document name, e.g. Birth Certificate"></div>
            <div class="col-md-7"><input type="file" name="documents[{{ $i }}][file]" class="form-control" accept=".pdf,image/*"></div>
        @endforeach
    </div>
</div>

@unless($public)
    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-percent me-1"></i> Fee Concession & Remarks</div>
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label">Fee Concession (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="concession_percent" class="form-control" value="{{ $v('concession_percent') ?? 0 }}">
                <div class="form-text">Applied to fee categories marked "concession applicable" when fees are assigned.</div>
            </div>
            <div class="col-md-9"><label class="form-label">Remarks</label><textarea name="remarks" rows="2" class="form-control">{{ $v('remarks') }}</textarea></div>
        </div>
    </div>
@endunless
