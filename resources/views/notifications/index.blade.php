@extends('layouts.app')
@section('title', 'Message Log')
@section('content')
@include('notifications.gateway-alert')
@php($statusMap = ['queued' => ['secondary', 'Queued'], 'sent' => ['success', 'Sent'], 'failed' => ['danger', 'Failed'], 'logged' => ['warning', 'Not sent (no gateway)']])
<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-center">
            <div class="col-md-3"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Mobile or student"></div>
            <div class="col-6 col-md-2"><select name="type" class="form-select form-select-sm"><option value="">All types</option>@foreach(\App\Models\NotificationLog::TYPES as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><select name="status" class="form-select form-select-sm"><option value="">All statuses</option>@foreach($statusMap as $k => [$c, $l])<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="col-6 col-md-2"><select name="channel" class="form-select form-select-sm"><option value="">SMS + WhatsApp</option><option value="sms" @selected(request('channel') === 'sms')>SMS</option><option value="whatsapp" @selected(request('channel') === 'whatsapp')>WhatsApp</option></select></div>
            <div class="col-auto"><button class="btn btn-sm btn-primary">Filter</button></div>
            @if(auth()->user()->isAdmin())<div class="col-auto ms-auto"><a href="{{ route('notifications.announce') }}" class="btn btn-sm btn-primary"><i class="bi bi-megaphone"></i> New Announcement</a></div>@endif
        </form>
    </div>
    <div class="card-footer small d-flex flex-wrap gap-3">
        <span class="text-muted">This month:</span>
        @foreach($statusMap as $k => [$c, $l])<span><span class="badge text-bg-{{ $c }}">{{ $counts[$k] ?? 0 }}</span> {{ $l }}</span>@endforeach
    </div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Time</th><th>Type</th><th>To</th><th>Student</th><th style="width:40%">Message</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td class="small text-nowrap">{{ $log->created_at->format('d M, h:i A') }}</td>
                    <td class="small">{{ \App\Models\NotificationLog::TYPES[$log->type] ?? $log->type }}<div class="text-muted">{!! $log->channel === 'whatsapp' ? '<i class="bi bi-whatsapp text-success"></i> WhatsApp' : '<i class="bi bi-chat-text"></i> SMS' !!}</div></td>
                    <td class="small">+{{ $log->recipient }}</td>
                    <td class="small">@if($log->student)<a href="{{ route('students.show', $log->student) }}">{{ $log->student->full_name }}</a>@endif</td>
                    <td class="small">{{ $log->message }}@if($log->status === 'failed' && $log->response)<div class="text-danger">{{ \Illuminate\Support\Str::limit($log->response, 120) }}</div>@endif</td>
                    <td><span class="badge text-bg-{{ $statusMap[$log->status][0] ?? 'light' }}">{{ $statusMap[$log->status][1] ?? $log->status }}</span></td>
                    <td class="text-end">
                        @if(in_array($log->status, ['failed', 'logged']))
                            <form method="POST" action="{{ route('notifications.retry', $log) }}">@csrf<button class="btn btn-sm btn-link p-0" title="Send again"><i class="bi bi-arrow-repeat"></i></button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">No messages yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())<div class="card-footer">{{ $logs->links('pagination::bootstrap-5') }}</div>@endif
</div>
@endsection
