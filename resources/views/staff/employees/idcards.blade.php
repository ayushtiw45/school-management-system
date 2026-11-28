@extends('layouts.print')
@section('title', 'Staff ID Cards')
@push('styles')
<style>
    .cards { display:flex; flex-wrap:wrap; gap:12px; justify-content:center; max-width:820px; margin:0 auto 2rem; }
    .idc { width:54mm; height:86mm; background:#fff; border-radius:8px; overflow:hidden; border:1px solid #d1d5db; display:flex; flex-direction:column; page-break-inside:avoid; font-size:9px; }
    .idc .top { background:#17191C; color:#fff; text-align:center; padding:6px 4px 26px; }
    .idc .top b { font-size:11px; display:block; line-height:1.2; }
    .idc .top span { color:#FF4B32; font-size:7.5px; letter-spacing:.08em; text-transform:uppercase; }
    .idc .ph { width:22mm; height:26mm; margin:-22px auto 4px; border:3px solid #fff; border-radius:6px; overflow:hidden; background:#e5e7eb; display:flex; align-items:center; justify-content:center; font-size:22px; font-weight:700; }
    .idc .ph img { width:100%; height:100%; object-fit:cover; }
    .idc .nm { text-align:center; font-weight:700; font-size:11px; } .idc .ds { text-align:center; color:#FF4B32; font-weight:600; }
    .idc dl { margin:4px 8px 0; display:grid; grid-template-columns:auto 1fr; gap:1px 6px; } .idc dt { color:#6b7280; font-weight:500; } .idc dd { margin:0; font-weight:500; }
    .idc .ft { margin-top:auto; text-align:center; padding:3px; border-top:1px dashed #ddd; font-size:7.5px; color:#6b7280; }
</style>
@endpush
@section('content')
@php($logo = school_image('logo'))
<div class="cards">
    @forelse($employees as $e)
        <div class="idc">
            <div class="top">@if($logo)<img src="{{ $logo }}" alt="" style="height:20px;max-width:40px;object-fit:contain;background:#fff;border-radius:4px;padding:1px;display:block;margin:0 auto 2px">@endif<span>Staff Identity Card</span><b>{{ school('name') }}</b></div>
            <div class="ph">@if($e->photo)<img src="{{ route('staff.employees.photo', $e) }}" alt="">@else{{ strtoupper(substr($e->name, 0, 1)) }}@endif</div>
            <div class="nm">{{ $e->name }}</div><div class="ds">{{ $e->designation ?: \App\Models\Hr\Employee::TYPES[$e->type] }}</div>
            <dl>
                <dt>Emp. ID</dt><dd>{{ $e->employee_id }}</dd>
                @if($e->department)<dt>Dept.</dt><dd>{{ $e->department }}</dd>@endif
                <dt>Mobile</dt><dd>{{ $e->phone }}</dd>
                @if($e->blood_group)<dt>Blood</dt><dd>{{ $e->blood_group }}</dd>@endif
                @if($e->emergency_phone)<dt>Emergency</dt><dd>{{ $e->emergency_phone }}</dd>@endif
            </dl>
            <div class="ft">{{ school('phone') }}</div>
        </div>
    @empty<p class="text-muted">No staff.</p>@endforelse
</div>
@endsection
