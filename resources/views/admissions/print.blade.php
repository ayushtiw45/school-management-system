@extends('layouts.print')
@section('title', 'Admission Form '.$student->admission_no)
@section('content')
@php($s = $student)
<div class="sheet">
    @include('partials.school-head', ['docTitle' => 'Admission Form & Receipt'])
    <div class="d-flex justify-content-between mb-3">
        <div>
            <div><strong>Admission No:</strong> {{ $s->admission_no }}</div>
            <div><strong>Admission Date:</strong> {{ $s->admission_date->format('d M Y') }}</div>
            <div><strong>Session:</strong> {{ $s->academicSession->name }}</div>
            <div><strong>Class:</strong> {{ $s->class_label }} @if($s->roll_no)· Roll {{ $s->roll_no }}@endif</div>
        </div>
        <div class="border" style="width:110px;height:130px;overflow:hidden">
            @if($s->photo)<img src="{{ route('students.photo', $s) }}" style="width:100%;height:100%;object-fit:cover">@else<div class="h-100 d-flex align-items-center justify-content-center text-muted small">Photo</div>@endif
        </div>
    </div>
    <table class="table table-bordered table-sm">
        <tr><th colspan="4" class="table-light">Student Details</th></tr>
        <tr><th style="width:22%">Name</th><td>{{ $s->full_name }}</td><th style="width:18%">Gender</th><td>{{ ucfirst($s->gender) }}</td></tr>
        <tr><th>Date of Birth</th><td>{{ $s->dob?->format('d M Y') }}</td><th>Blood Group</th><td>{{ $s->blood_group }}</td></tr>
        <tr><th>Religion</th><td>{{ $s->religion }}</td><th>Category</th><td>{{ $s->category }}</td></tr>
        <tr><th>Address</th><td colspan="3">{{ collect([$s->address, $s->city, $s->state, $s->pincode])->filter()->join(', ') }}</td></tr>
        <tr><th colspan="4" class="table-light">Parent / Guardian</th></tr>
        <tr><th>Father</th><td>{{ $s->father_name }}</td><th>Mobile</th><td>{{ $s->father_phone }}</td></tr>
        <tr><th>Occupation</th><td>{{ $s->father_occupation }}</td><th>Email</th><td>{{ $s->email }}</td></tr>
        <tr><th>Mother</th><td>{{ $s->mother_name }}</td><th>Mobile</th><td>{{ $s->mother_phone }}</td></tr>
        @if($s->guardian_name)<tr><th>Guardian</th><td>{{ $s->guardian_name }} ({{ $s->guardian_relation }})</td><th>Mobile</th><td>{{ $s->guardian_phone }}</td></tr>@endif
        <tr><th colspan="4" class="table-light">Previous School</th></tr>
        <tr><th>School</th><td>{{ $s->previous_school ?: '—' }}</td><th>Class / TC</th><td>{{ $s->previous_class }} {{ $s->tc_number ? '/ '.$s->tc_number : '' }}</td></tr>
    </table>

    @php($admissionPayments = $s->payments->sortBy('id')->take(1))
    @if($admissionPayments->count())
        <table class="table table-bordered table-sm">
            <tr><th colspan="4" class="table-light">Admission Fee Receipt</th></tr>
            @foreach($admissionPayments as $p)
                <tr><th>Receipt No</th><td>{{ $p->receipt_no }}</td><th>Date</th><td>{{ $p->payment_date->format('d M Y') }}</td></tr>
                <tr><th>Amount Paid</th><td>{{ inr($p->amount) }}</td><th>Mode</th><td>{{ $p->methodLabel() }}</td></tr>
            @endforeach
        </table>
    @endif

    <p class="small text-muted mt-3">I hereby declare that the information given above is correct.</p>
    <div class="d-flex justify-content-between mt-5 pt-3">
        <div class="text-center border-top pt-1" style="width:200px">Parent / Guardian Signature</div>
        <div class="text-center border-top pt-1" style="width:200px">Principal / Authorised Signatory</div>
    </div>
</div>
@endsection
