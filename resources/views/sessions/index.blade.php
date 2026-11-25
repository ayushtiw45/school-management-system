@extends('layouts.app')
@section('title', 'Academic Sessions')
@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">New Session</div>
            <div class="card-body">
                <form method="POST" action="{{ route('sessions.store') }}" class="row g-2">
                    @csrf
                    <div class="col-12"><label class="form-label required">Name</label><input name="name" class="form-control" placeholder="2026-27" required></div>
                    <div class="col-6"><label class="form-label required">Start</label><input type="date" name="start_date" class="form-control" value="{{ now()->month >= 4 ? now()->year : now()->year - 1 }}-04-01" required></div>
                    <div class="col-6"><label class="form-label required">End</label><input type="date" name="end_date" class="form-control" value="{{ now()->month >= 4 ? now()->year + 1 : now()->year }}-03-31" required></div>
                    <div class="col-12 form-check ms-2"><input type="checkbox" name="is_current" value="1" class="form-check-input" id="cur"><label class="form-check-label" for="cur">Make this the current session</label></div>
                    <div class="col-12"><button class="btn btn-primary w-100">Create Session</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <table class="table mb-0 align-middle">
                <thead><tr><th>Session</th><th>Start</th><th>End</th><th class="text-end">Students</th><th></th></tr></thead>
                <tbody>
                @forelse($sessions as $ses)
                    <tr>
                        <td class="fw-semibold">{{ $ses->name }} @if($ses->is_current)<span class="badge text-bg-success">Current</span>@endif</td>
                        <td>{{ $ses->start_date->format('d M Y') }}</td>
                        <td>{{ $ses->end_date->format('d M Y') }}</td>
                        <td class="text-end">{{ $ses->students_count }}</td>
                        <td class="text-end text-nowrap">
                            @unless($ses->is_current)
                                <form method="POST" action="{{ route('sessions.current', $ses) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-success">Set Current</button></form>
                                <form method="POST" action="{{ route('sessions.destroy', $ses) }}" class="d-inline" data-confirm="Delete session {{ $ses->name }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger"><i class="bi bi-trash"></i></button></form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No sessions yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
