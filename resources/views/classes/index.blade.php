@extends('layouts.app')
@section('title', 'Classes & Sections')
@section('content')
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Add Class</div>
            <div class="card-body">
                <form method="POST" action="{{ route('classes.store') }}" class="row g-2">
                    @csrf
                    <div class="col-8"><input name="name" class="form-control" placeholder="e.g. Class 1" required></div>
                    <div class="col-4"><input type="number" name="sort_order" class="form-control" placeholder="Order"></div>
                    <div class="col-12"><button class="btn btn-primary w-100">Add Class</button></div>
                </form>
                <p class="small text-muted mt-3 mb-0">The order number decides the sequence (Nursery → Class 12) used in lists and when promoting students.</p>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header">Add sections to many classes</div>
            <div class="card-body">
                <form method="POST" action="{{ route('sections.bulk') }}">
                    @csrf
                    <input name="names" class="form-control mb-2" placeholder="e.g. C, D  or  A-F" required>
                    <div class="border rounded p-2 mb-2" style="max-height:180px;overflow:auto">
                        <div class="form-check"><input type="checkbox" class="form-check-input" id="allCls" onclick="document.querySelectorAll('.bulk-cls').forEach(c => c.checked = this.checked)"><label for="allCls" class="form-check-label fw-semibold">All classes</label></div>
                        @foreach($classes as $c)
                            <div class="form-check"><input type="checkbox" name="class_ids[]" value="{{ $c->id }}" class="form-check-input bulk-cls" id="bc{{ $c->id }}"><label for="bc{{ $c->id }}" class="form-check-label">{{ $c->name }} <span class="text-muted small">({{ $c->sections->pluck('name')->implode(', ') ?: 'none' }})</span></label></div>
                        @endforeach
                    </div>
                    <button class="btn btn-outline-primary w-100">Add sections</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th style="width:70px">Order</th><th>Class</th><th>Sections (students)</th><th class="text-end">Students</th><th></th></tr></thead>
                    <tbody>
                    @forelse($classes as $c)
                        <tr>
                            <td>{{ $c->sort_order }}</td>
                            <td class="fw-semibold">{{ $c->name }}</td>
                            <td>
                                @foreach($c->sections as $sec)
                                    <span class="badge text-bg-light border me-1 mb-1">
                                        <a href="{{ route('students.index', ['class_id' => $c->id, 'section_id' => $sec->id]) }}" class="text-decoration-none">{{ $sec->name }} ({{ $sec->students_count }})</a>
                                        <form method="POST" action="{{ route('sections.destroy', $sec) }}" class="d-inline" data-confirm="Delete section {{ $sec->name }}?">@csrf @method('DELETE')<button class="btn btn-link btn-sm p-0 text-danger" style="font-size:.7rem"><i class="bi bi-x"></i></button></form>
                                    </span>
                                @endforeach
                                <form method="POST" action="{{ route('sections.store', $c) }}" class="d-inline-flex gap-1 align-middle">
                                    @csrf
                                    <input name="name" class="form-control form-control-sm" style="width:90px" placeholder="{{ chr(65 + min(25, $c->sections->count())) }} or C-F" value="{{ chr(65 + min(25, $c->sections->count())) }}" required title="One section (C), several (C,D) or a range (C-F)">
                                    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-plus"></i></button>
                                </form>
                            </td>
                            <td class="text-end"><a href="{{ route('students.index', ['class_id' => $c->id]) }}">{{ $c->students_count }}</a></td>
                            <td class="text-end text-nowrap">
                                <button class="btn btn-sm btn-link" data-bs-toggle="modal" data-bs-target="#ec{{ $c->id }}"><i class="bi bi-pencil"></i></button>
                                <form method="POST" action="{{ route('classes.destroy', $c) }}" class="d-inline" data-confirm="Delete {{ $c->name }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger"><i class="bi bi-trash"></i></button></form>
                                <div class="modal fade" id="ec{{ $c->id }}" tabindex="-1"><div class="modal-dialog modal-sm"><form method="POST" action="{{ route('classes.update', $c) }}" class="modal-content text-start">
                                    @csrf @method('PUT')
                                    <div class="modal-header"><h6 class="modal-title">Edit Class</h6></div>
                                    <div class="modal-body"><label class="form-label">Name</label><input name="name" value="{{ $c->name }}" class="form-control mb-2" required><label class="form-label">Order</label><input type="number" name="sort_order" value="{{ $c->sort_order }}" class="form-control" required></div>
                                    <div class="modal-footer"><button class="btn btn-primary btn-sm">Save</button></div>
                                </form></div></div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No classes yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
