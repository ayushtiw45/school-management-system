@extends('layouts.print')
@section('title', 'Salary slip')
@section('content')
@include('staff.payroll.slip-body', ['p' => $p])
@endsection
