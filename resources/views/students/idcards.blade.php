@extends('layouts.print')
@section('title', 'ID Cards')
@push('styles')
<style>
    .cards { display:flex; flex-wrap:wrap; gap:12px; justify-content:center; max-width:820px; margin:0 auto 2rem; }
    .idc { width:54mm; height:86mm; background:#fff; border-radius:8px; overflow:hidden; border:1px solid #d1d5db; display:flex; flex-direction:column; page-break-inside:avoid; font-size:9px; }
    .idc .top { background:#17191C; color:#fff; text-align:center; padding:6px 4px 26px; }
    .idc .top b { font-size:11px; display:block; line-height:1.2; }
    .idc .top span { color:#FF4B32; font-size:7.5px; letter-spacing:.08em; text-transform:uppercase; }
    .idc .ph { width:22mm; height:26mm; margin:-22px auto 4px; border:3px solid #fff; border-radius:6px; overflow:hidden; background:#e5e7eb; display:flex; align-items:center; justify-content:center; font-size:22px; font-weight:700; color:#17191C; }
    .idc .ph img { width:100%; height:100%; object-fit:cover; }
    .idc .nm { text-align:center; font-weight:700; font-size:11px; color:#17191C; }
    .idc dl { margin:4px 8px 0; display:grid; grid-template-columns:auto 1fr; gap:1px 6px; }
    .idc dt { color:#6b7280; font-weight:500; }
    .idc dd { margin:0; font-weight:500; }
    .idc .ft { margin-top:auto; background:#FF4B32; height:6px; }
    @media print { .cards { max-width:none; } }
</style>
@endpush
@section('content')
<div class="cards">
    @forelse($students as $s)
        <div class="idc">
            <div class="top"><span>Student Identity Card</span><b>{{ school('name') }}</b></div>
            <div class="ph">@if($s->photo)<img src="{{ route('students.photo', $s) }}" alt="">@else{{ strtoupper(substr($s->first_name, 0, 1)) }}@endif</div>
            <div class="nm">{{ $s->full_name }}</div>
            <dl>
                <dt>Adm. No</dt><dd>{{ $s->admission_no }}</dd>
                <dt>Class</dt><dd>{{ $s->class_label }}</dd>
                @if($s->roll_no)<dt>Roll No</dt><dd>{{ $s->roll_no }}</dd>@endif
                <dt>DOB</dt><dd>{{ $s->dob?->format('d-m-Y') }}</dd>
                <dt>Father</dt><dd>{{ $s->father_name }}</dd>
                <dt>Mobile</dt><dd>{{ $s->parent_phone }}</dd>
                @if($s->blood_group)<dt>Blood</dt><dd>{{ $s->blood_group }}</dd>@endif
                <dt>Session</dt><dd>{{ $s->academicSession->name }}</dd>
            </dl>
            <div class="ft"></div>
        </div>
    @empty
        <p class="text-center text-muted">No active students in this class.</p>
    @endforelse
</div>
@endsection
