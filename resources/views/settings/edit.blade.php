@extends('layouts.app')
@section('title', 'Settings')
@section('content')
<form method="POST" action="{{ route('settings.update') }}" class="card" style="max-width:760px">
    @csrf @method('PUT')
    <div class="card-header">School Details <span class="text-muted small fw-normal">(printed on receipts, forms and ID cards)</span></div>
    <div class="card-body row g-3">
        <div class="col-12"><label class="form-label required">School Name</label><input name="school_name" value="{{ old('school_name', $settings['school_name']) }}" class="form-control" required></div>
        <div class="col-12"><label class="form-label">Address</label><input name="school_address" value="{{ old('school_address', $settings['school_address']) }}" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input name="school_phone" value="{{ old('school_phone', $settings['school_phone']) }}" class="form-control"></div>
        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="school_email" value="{{ old('school_email', $settings['school_email']) }}" class="form-control"></div>
    </div>
    <div class="card-header border-top">Numbers & Fees</div>
    <div class="card-body row g-3">
        <div class="col-md-3"><label class="form-label required">Admission No. Prefix</label><input name="admission_prefix" value="{{ old('admission_prefix', $settings['admission_prefix']) }}" class="form-control" required><div class="form-text">e.g. ADM2026-0001</div></div>
        <div class="col-md-3"><label class="form-label required">Receipt No. Prefix</label><input name="receipt_prefix" value="{{ old('receipt_prefix', $settings['receipt_prefix']) }}" class="form-control" required><div class="form-text">e.g. RCPT-2026-000001</div></div>
        <div class="col-md-3"><label class="form-label required">Fee Due Day</label><input type="number" min="1" max="28" name="fee_due_day" value="{{ old('fee_due_day', $settings['fee_due_day']) }}" class="form-control" required><div class="form-text">Day of month fees fall due</div></div>
        <div class="col-md-3"><label class="form-label required">Late Fine (₹)</label><input type="number" step="0.01" min="0" name="late_fine_amount" value="{{ old('late_fine_amount', $settings['late_fine_amount']) }}" class="form-control" required><div class="form-text">Suggested per overdue fee</div></div>
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary">Save Settings</button></div>
</form>
@endsection
