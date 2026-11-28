@extends('layouts.app')
@section('title', 'Substitute Teachers')
@section('content')
@php($can = auth()->user()->canManageStaff())
<form class="d-flex gap-2 mb-3 no-print"><input type="date" name="date" value="{{ $date->format('Y-m-d') }}" class="form-control form-control-sm" style="max-width:180px" onchange="this.form.submit()">
    <span class="small text-muted align-self-center">Teachers marked absent or on leave on this day, with their periods. Free teachers with the fewest periods that day are listed first.</span>
    <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary ms-auto"><i class="bi bi-printer"></i> Print arrangement</button></form>
<h2 class="h6 d-none d-print-block">{{ school('name') }} — Arrangement for {{ $date->format('l, d M Y') }}</h2>
<div class="card">
    <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
        <thead><tr><th>Period</th><th>Class</th><th>Subject</th><th>Absent teacher</th><th>Substitute</th></tr></thead>
        <tbody>
        @forelse($needs as $n)
            @php($e = $n['entry'])
            <tr class="{{ $n['substitution']?->substitute_employee_id ? 'table-success' : '' }}">
                <td>{{ $e->period->name }} <span class="small text-muted">{{ $e->period->timeLabel() }}</span></td>
                <td>{{ $e->section->schoolClass->name }} {{ $e->section->name }}</td><td>{{ $e->subject?->name }}</td><td>{{ $e->employee?->name }}</td>
                <td>
                    @if($can)
                    <form method="POST" action="{{ route('staff.substitutions.store') }}" class="d-flex gap-1 no-print">@csrf
                        <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}"><input type="hidden" name="period_id" value="{{ $e->period_id }}"><input type="hidden" name="section_id" value="{{ $e->section_id }}"><input type="hidden" name="absent_employee_id" value="{{ $e->employee_id }}">
                        <select name="substitute_employee_id" class="form-select form-select-sm"><option value="">— choose —</option>
                            @if($n['substitution']?->substitute)<option value="{{ $n['substitution']->substitute_employee_id }}" selected>{{ $n['substitution']->substitute->name }}</option>@endif
                            @foreach($free[$e->period_id] ?? [] as $t)<option value="{{ $t->id }}">{{ $t->name }} ({{ $t->periods_today }} periods today)</option>@endforeach
                        </select><button class="btn btn-sm btn-primary">Save</button>
                    </form>
                    @endif
                    <span class="{{ $can ? 'd-none d-print-inline' : '' }}">{{ $n['substitution']?->substitute?->name ?? '—' }}</span>
                </td>
            </tr>
        @empty<tr><td colspan="5" class="text-muted text-center py-4">No teacher with timetable periods is absent on this day. Mark attendance or leave first.</td></tr>@endforelse
        </tbody>
    </table></div>
</div>
@endsection
