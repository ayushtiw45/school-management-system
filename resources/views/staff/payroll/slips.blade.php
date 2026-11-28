@extends('layouts.print')
@section('title', 'Salary slips')
@section('content')
@forelse($payrolls as $p)@include('staff.payroll.slip-body', ['p' => $p])@empty<p class="text-center text-muted">No salary for this month.</p>@endforelse
@endsection
