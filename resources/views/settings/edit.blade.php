@extends('layouts.app')
@section('title', 'School Settings')
@section('content')
@php($logo = school_image('logo'))
@php($sign = school_image('signature'))
<div class="row g-3">
<div class="col-xl-8">
<form method="POST" action="{{ route('settings.update') }}" class="card">
    @csrf @method('PUT')
    <div class="card-header">School details <span class="text-muted small fw-normal">(printed on receipts, forms, fee slips and ID cards)</span></div>
    <div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label required">Brand name (school name)</label><input name="school_name" value="{{ old('school_name', $settings['school_name']) }}" class="form-control" required><div class="form-text">Shown everywhere, e.g. "Sunrise Public School"</div></div>
        <div class="col-md-6"><label class="form-label">Legal name</label><input name="school_legal_name" value="{{ old('school_legal_name', $settings['school_legal_name']) }}" class="form-control"><div class="form-text">Trust / society name, e.g. "Sunrise Educational Trust"</div></div>
        <div class="col-md-8"><label class="form-label">Address</label><input name="school_address" value="{{ old('school_address', $settings['school_address']) }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">State</label>
            <select name="school_state" class="form-select"><option value="">—</option>@foreach($states as $st)<option @selected(old('school_state', $settings['school_state']) === $st)>{{ $st }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Phone</label><input name="school_phone" value="{{ old('school_phone', $settings['school_phone']) }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="school_email" value="{{ old('school_email', $settings['school_email']) }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">Website</label><input name="school_website" value="{{ old('school_website', $settings['school_website']) }}" class="form-control" placeholder="www.myschool.in"></div>
    </div>

    <div class="card-header border-top">Accounts signatory <span class="text-muted small fw-normal">(signs receipts and fee slips)</span></div>
    <div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label">Name</label><input name="signatory_name" value="{{ old('signatory_name', $settings['signatory_name']) }}" class="form-control" placeholder="e.g. R. K. Sharma"></div>
        <div class="col-md-6"><label class="form-label">Designation</label><input name="signatory_title" value="{{ old('signatory_title', $settings['signatory_title']) }}" class="form-control" placeholder="Accounts / Cashier"></div>
    </div>

    <div class="card-header border-top">Bank & UPI <span class="text-muted small fw-normal">(printed on fee slips so parents can pay online)</span></div>
    <div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label">Account holder name</label><input name="bank_account_name" value="{{ old('bank_account_name', $settings['bank_account_name']) }}" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Account number</label><input name="bank_account_no" value="{{ old('bank_account_no', $settings['bank_account_no']) }}" class="form-control" inputmode="numeric"></div>
        <div class="col-md-4"><label class="form-label">IFSC</label><input name="bank_ifsc" value="{{ old('bank_ifsc', $settings['bank_ifsc']) }}" class="form-control text-uppercase" maxlength="11"></div>
        <div class="col-md-4"><label class="form-label">Bank & branch</label><input name="bank_name" value="{{ old('bank_name', $settings['bank_name']) }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">UPI ID</label><input name="upi_id" value="{{ old('upi_id', $settings['upi_id']) }}" class="form-control" placeholder="school@okaxis"><div class="form-text">A UPI QR code is printed on fee slips</div></div>
    </div>

    <div class="card-header border-top">Receipt & fee slip text</div>
    <div class="card-body row g-3">
        <div class="col-12"><label class="form-label">Note on receipt</label><input name="receipt_note" value="{{ old('receipt_note', $settings['receipt_note']) }}" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Terms & conditions — receipt</label><textarea name="receipt_terms" rows="4" class="form-control" placeholder="One point per line">{{ old('receipt_terms', $settings['receipt_terms']) }}</textarea></div>
        <div class="col-md-6"><label class="form-label">Terms & conditions — fee slip / quotation</label><textarea name="slip_terms" rows="4" class="form-control" placeholder="One point per line">{{ old('slip_terms', $settings['slip_terms']) }}</textarea></div>
    </div>

    <div class="card-header border-top">Numbers & fees</div>
    <div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label required">Student ID prefix</label><input name="student_code_prefix" value="{{ old('student_code_prefix', $settings['student_code_prefix']) }}" class="form-control text-uppercase" required><div class="form-text">e.g. {{ $settings['student_code_prefix'] }}{{ now()->format('y') }}-0001 — given when admission is confirmed</div></div>
        <div class="col-md-4"><label class="form-label required">Admission No. prefix</label><input name="admission_prefix" value="{{ old('admission_prefix', $settings['admission_prefix']) }}" class="form-control" required><div class="form-text">e.g. ADM2026-0001</div></div>
        <div class="col-md-4"><label class="form-label required">Receipt No. prefix</label><input name="receipt_prefix" value="{{ old('receipt_prefix', $settings['receipt_prefix']) }}" class="form-control" required><div class="form-text">e.g. RCPT-2026-000001</div></div>
        <div class="col-md-4"><label class="form-label required">Fee due day</label><input type="number" min="1" max="28" name="fee_due_day" value="{{ old('fee_due_day', $settings['fee_due_day']) }}" class="form-control" required><div class="form-text">Day of month fees fall due</div></div>
        <div class="col-md-4"><label class="form-label required">Late fine (₹)</label><input type="number" step="0.01" min="0" name="late_fine_amount" value="{{ old('late_fine_amount', $settings['late_fine_amount']) }}" class="form-control" required><div class="form-text">Suggested per overdue fee</div></div>
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary">Save settings</button></div>
</form>
</div>
<div class="col-xl-4">
    @foreach([['logo', 'School logo', $logo, route('settings.logo'), 'Square PNG/JPG, under 1 MB. Shown on the admission form, receipts, ID cards and (after payment) in the menu.'],
              ['signature', 'Accounts signature', $sign, route('settings.signature'), 'Sign on white paper, take a photo, crop it. Printed above the signatory name on receipts.']] as [$k, $label, $img, $action, $help])
    <div class="card mb-3">
        <div class="card-header">{{ $label }}</div>
        <div class="card-body">
            <div class="border rounded-3 d-flex align-items-center justify-content-center mb-2 bg-light" style="height:{{ $k === 'logo' ? 140 : 90 }}px">
                @if($img)<img src="{{ $img }}" alt="{{ $label }}" style="max-height:100%;max-width:100%;object-fit:contain">@else<span class="text-muted small">Not uploaded</span>@endif
            </div>
            <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="d-flex gap-2">
                @csrf<input type="file" name="image" accept="image/png,image/jpeg,image/webp" class="form-control form-control-sm" required><button class="btn btn-sm btn-primary">Upload</button>
            </form>
            @if($img)<form method="POST" action="{{ $action }}" class="mt-2">@csrf<input type="hidden" name="remove" value="1"><button class="btn btn-link btn-sm text-danger p-0">Remove</button></form>@endif
            <div class="form-text">{{ $help }}</div>
        </div>
    </div>
    @endforeach
    <div class="card"><div class="card-body small text-muted">
        <i class="bi bi-stars"></i> Once your plan is active, the software shows your school's name and logo instead of The Digital Strokes.
    </div></div>
</div>
</div>
@endsection
