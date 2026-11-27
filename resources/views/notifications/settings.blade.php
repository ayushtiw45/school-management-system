@extends('layouts.app')
@section('title', 'Message Settings')
@section('content')
@include('notifications.gateway-alert')
<form method="POST" action="{{ route('notifications.settings.update') }}" class="card" style="max-width:860px">
    @csrf @method('PUT')
    <div class="card-header">When to send</div>
    <div class="card-body row g-3">
        <div class="col-md-4">
            <label class="form-label">Default channel</label>
            <select name="notify_channel" class="form-select">
                @foreach(['sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'both' => 'SMS + WhatsApp'] as $k => $l)<option value="{{ $k }}" @selected($settings['notify_channel'] === $k)>{{ $l }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-8 d-flex flex-column gap-1 justify-content-end">
            <div class="form-check"><input type="checkbox" class="form-check-input" name="notify_payment" value="1" id="np" @checked($settings['notify_payment'] === '1')><label class="form-check-label" for="np">Send payment confirmation after every fee receipt</label></div>
            <div class="form-check"><input type="checkbox" class="form-check-input" name="notify_admission" value="1" id="na" @checked($settings['notify_admission'] === '1')><label class="form-check-label" for="na">Send admission confirmation when an admission is confirmed</label></div>
        </div>
        <div class="col-md-8">
            <div class="form-check"><input type="checkbox" class="form-check-input" name="notify_fee_due_auto" value="1" id="nf" @checked($settings['notify_fee_due_auto'] === '1')><label class="form-check-label" for="nf">Send fee-due reminders automatically every day at 10 AM (at most once a week per student)</label></div>
        </div>
        <div class="col-md-4">
            <label class="form-label">Remind after due date by (days)</label>
            <input type="number" min="0" max="60" name="reminder_days_after_due" value="{{ $settings['reminder_days_after_due'] }}" class="form-control">
        </div>
    </div>
    <div class="card-header border-top">Message templates</div>
    <div class="card-body row g-3">
        <div class="col-12 small text-muted">Placeholders: {student} {class} {admission_no} {father} {school} · Fee due: {amount} {due_date} · Payment: {amount} {receipt} {date} {balance}</div>
        <div class="col-12"><label class="form-label">Fee due reminder</label><textarea name="tpl_fee_due" rows="2" class="form-control">{{ $settings['tpl_fee_due'] }}</textarea></div>
        <div class="col-12"><label class="form-label">Payment confirmation</label><textarea name="tpl_payment" rows="2" class="form-control">{{ $settings['tpl_payment'] }}</textarea></div>
        <div class="col-12"><label class="form-label">Admission confirmation</label><textarea name="tpl_admission" rows="2" class="form-control">{{ $settings['tpl_admission'] }}</textarea></div>
        <div class="col-12 small text-muted">Note: Indian SMS providers need these templates registered on DLT, and WhatsApp business providers need approved templates. Keep the wording here the same as the approved template.</div>
    </div>
    <div class="card-footer text-end"><button class="btn btn-primary">Save</button></div>
</form>
@endsection
