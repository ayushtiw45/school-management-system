@extends('layouts.app')
@section('title', 'Fee Categories')
@section('content')
@php($e = $editing)
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">{{ $e ? 'Edit Category' : 'Add Fee Category' }}</div>
            <div class="card-body">
                <form method="POST" action="{{ $e ? route('fee-heads.update', $e) : route('fee-heads.store') }}" class="row g-2">
                    @csrf @if($e) @method('PUT') @endif
                    <div class="col-12"><label class="form-label required">Name</label><input name="name" value="{{ old('name', $e?->name) }}" class="form-control" placeholder="e.g. Tuition Fee" required></div>
                    <div class="col-12">
                        <label class="form-label required">Charged</label>
                        <select name="frequency" class="form-select">
                            @foreach(\App\Models\FeeHead::FREQUENCIES as $k => $l)<option value="{{ $k }}" @selected(old('frequency', $e?->frequency) === $k)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-12 form-check ms-2"><input type="checkbox" name="concession_applicable" value="1" class="form-check-input" id="ca" @checked(old('concession_applicable', $e?->concession_applicable ?? true))><label class="form-check-label" for="ca">Student concession % applies</label></div>
                    <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2">{{ old('description', $e?->description) }}</textarea></div>
                    <div class="col-12 d-flex gap-2">
                        @if($e)<a href="{{ route('fee-heads.index') }}" class="btn btn-outline-secondary">Cancel</a>@endif
                        <button class="btn btn-primary flex-fill">{{ $e ? 'Save' : 'Add Category' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <table class="table mb-0 align-middle">
                <thead><tr><th>Category</th><th>Charged</th><th>Concession</th><th class="text-end">Used in classes</th><th></th></tr></thead>
                <tbody>
                @forelse($heads as $h)
                    <tr>
                        <td class="fw-semibold">{{ $h->name }}<div class="text-muted small fw-normal">{{ $h->description }}</div></td>
                        <td>{{ $h->frequencyLabel() }}</td>
                        <td>{!! $h->concession_applicable ? '<i class="bi bi-check-lg text-success"></i>' : '—' !!}</td>
                        <td class="text-end">{{ $h->structures_count }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('fee-heads.edit', $h) }}" class="btn btn-sm btn-link"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="{{ route('fee-heads.destroy', $h) }}" class="d-inline" data-confirm="Delete {{ $h->name }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger"><i class="bi bi-trash"></i></button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No fee categories yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
