@extends('layouts.app')
@section('title', 'Enquiries & Follow-ups')
@section('content')
<div class="row g-3 mb-3">
    @foreach(['due' => ['Follow-ups due', 'bi-alarm', 'danger'], 'open' => ['Open enquiries', 'bi-chat-left-text', 'primary'], 'today' => ['Added today', 'bi-calendar-day', 'secondary'], 'converted' => ['Admission done', 'bi-check2-circle', 'success']] as $k => [$label, $icon, $c])
        <div class="col-6 col-md-3">
            <a href="{{ route('enquiries.index', ['view' => $k]) }}" class="card p-3 text-decoration-none h-100 {{ $view === $k ? 'border-2 border-'.$c : '' }}">
                <div class="d-flex align-items-center gap-2 text-{{ $c }}"><i class="bi {{ $icon }} fs-4"></i><span class="small text-muted">{{ $label }}</span></div>
                <div class="fs-3 fw-bold text-body">{{ $counts[$k] }}</div>
            </a>
        </div>
    @endforeach
</div>
<div class="card">
    <div class="card-header">
        <form class="row g-2 align-items-center">
            <input type="hidden" name="view" value="{{ $view }}">
            <div class="col-auto">
                <div class="btn-group btn-group-sm">
                    @foreach(['open' => 'Open', 'due' => 'Due', 'today' => 'Today', 'converted' => 'Admitted', 'closed' => 'Not interested', 'all' => 'All'] as $k => $l)
                        <a href="{{ route('enquiries.index', ['view' => $k] + request()->except('view', 'page')) }}" class="btn btn-outline-primary {{ $view === $k ? 'active' : '' }}">{{ $l }}</a>
                    @endforeach
                </div>
            </div>
            <div class="col-auto">
                <select name="class_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All classes</option>
                    @foreach($classes as $c)<option value="{{ $c->id }}" @selected(request('class_id') == $c->id)>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="col"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search child, parent or mobile"></div>
            <div class="col-auto"><button class="btn btn-sm btn-primary">Search</button></div>
            <div class="col-auto"><a href="{{ route('enquiries.create') }}" class="btn btn-sm btn-success"><i class="bi bi-plus-lg"></i> New enquiry</a></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr><th>Child</th><th>Class</th><th>Parent / Mobile</th><th>Source</th><th>Next follow-up</th><th>Status</th><th>Added by</th><th></th></tr></thead>
            <tbody>
            @forelse($enquiries as $e)
                <tr>
                    <td><a href="{{ route('enquiries.show', $e) }}" class="fw-semibold">{{ $e->child_name }}</a><div class="small text-muted">{{ $e->created_at->format('d M Y') }}</div></td>
                    <td>{{ $e->schoolClass?->name ?? '—' }}</td>
                    <td>{{ $e->parent_name }}<div class="small"><a href="tel:{{ $e->phone }}">{{ $e->phone }}</a></div></td>
                    <td class="small">{{ \App\Models\Enquiry::SOURCES[$e->source] ?? $e->source }}</td>
                    <td>@if($e->next_follow_up)<span class="{{ $e->followUpDue() ? 'text-danger fw-semibold' : '' }}">{{ $e->next_follow_up->format('d M Y') }}</span>@else — @endif</td>
                    <td>@include('enquiries.status', ['status' => $e->status])</td>
                    <td class="small">{{ $e->creator?->name }}</td>
                    <td class="text-end text-nowrap">
                        <a href="https://wa.me/91{{ substr(preg_replace('/\D/', '', $e->phone), -10) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>
                        <a href="{{ route('enquiries.show', $e) }}" class="btn btn-sm btn-outline-primary">Open</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No enquiries here.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($enquiries->hasPages())<div class="card-footer">{{ $enquiries->links('pagination::bootstrap-5') }}</div>@endif
</div>
@endsection
