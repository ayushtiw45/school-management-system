@extends('layouts.print')
@section('title', 'Admission Form')
@push('styles')
<style>
    .line { border-bottom:1px solid #555; min-height:1.6rem; flex:1; }
    .f { display:flex; gap:.5rem; align-items:flex-end; margin-bottom:.55rem; }
    .f label { white-space:nowrap; font-weight:500; }
    .sec { background:#17191C; color:#fff; padding:.2rem .6rem; font-weight:600; font-size:.8rem; letter-spacing:.06em; text-transform:uppercase; margin:1rem 0 .6rem; }
    .box { display:inline-block; width:.85rem; height:.85rem; border:1px solid #333; vertical-align:middle; margin-right:.25rem; }
    .photo { width:110px; height:130px; border:1px dashed #555; display:flex; align-items:center; justify-content:center; text-align:center; font-size:.7rem; color:#777; }
</style>
@endpush
@section('content')
<div class="sheet">
    @include('partials.school-head', ['docTitle' => 'Admission Form'.($session ? ' · Session '.$session->name : '')])
    <div class="d-flex gap-3">
        <div class="flex-grow-1">
            <div class="f"><label>Form No.</label><div class="line"></div><label>Date</label><div class="line"></div></div>
            <div class="f"><label>Class applied for</label><div class="line"></div></div>
            <div class="small">@foreach($classes as $c)<span class="me-2 text-nowrap"><span class="box"></span>{{ $c->name }}</span>@endforeach</div>
        </div>
        <div class="photo">Paste passport<br>size photo</div>
    </div>

    <div class="sec">Student details</div>
    <div class="f"><label>Student's full name</label><div class="line"></div></div>
    <div class="f"><label>Date of birth</label><div class="line"></div><label>Gender</label><span class="text-nowrap"><span class="box"></span>Boy <span class="box ms-2"></span>Girl</span></div>
    <div class="f"><label>Blood group</label><div class="line"></div><label>Religion</label><div class="line"></div><label>Category</label><div class="line"></div></div>
    <div class="f"><label>Aadhaar no. (optional)</label><div class="line"></div></div>
    <div class="f"><label>Address</label><div class="line"></div></div>
    <div class="f"><div class="line"></div><label>PIN</label><div class="line" style="max-width:120px"></div></div>

    <div class="sec">Parents / guardian</div>
    <div class="f"><label>Father's name</label><div class="line"></div><label>Mobile</label><div class="line"></div></div>
    <div class="f"><label>Occupation</label><div class="line"></div></div>
    <div class="f"><label>Mother's name</label><div class="line"></div><label>Mobile</label><div class="line"></div></div>
    <div class="f"><label>Guardian (if any)</label><div class="line"></div><label>Relation</label><div class="line"></div></div>
    <div class="f"><label>Email</label><div class="line"></div></div>

    <div class="sec">Previous school</div>
    <div class="f"><label>School name</label><div class="line"></div><label>Class passed</label><div class="line" style="max-width:120px"></div></div>
    <div class="f"><label>TC number</label><div class="line"></div></div>

    <div class="sec">Documents attached</div>
    <div class="small">
        @foreach(['Birth certificate', 'Aadhaar card (child)', 'Aadhaar card (parent)', 'Transfer certificate', 'Previous marksheet', 'Passport size photos (2)'] as $d)
            <span class="me-3 text-nowrap"><span class="box"></span>{{ $d }}</span>
        @endforeach
    </div>

    <div class="sec">Declaration</div>
    <p class="small mb-4">I declare that the information given above is true. I agree to follow the rules of the school and to pay the fees on time.</p>
    <div class="d-flex justify-content-between small mt-5">
        <div class="text-center border-top pt-1" style="width:200px">Signature of parent / guardian</div>
        <div class="text-center border-top pt-1" style="width:200px">Signature of principal</div>
    </div>

    <div class="border rounded p-2 mt-4 small">
        <strong>For office use:</strong> Admission No. ______________ &nbsp; Student ID ______________ &nbsp; Section ______ &nbsp; Admission fee received ₹ ______________ Receipt No. ______________
    </div>
    @isset($school)
        <p class="small text-center text-muted mt-3 mb-0">You can also fill this form online: {{ route('apply', $school) }}</p>
    @endisset
</div>
@endsection
