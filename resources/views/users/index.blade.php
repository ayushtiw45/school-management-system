@extends('layouts.app')
@section('title', 'Users & Roles')
@section('content')
<div class="d-flex mb-3"><a href="{{ route('users.create') }}" class="btn btn-primary ms-auto"><i class="bi bi-plus"></i> Add User</a></div>
<div class="card">
    <table class="table mb-0 align-middle">
        <thead><tr><th>Name</th><th>Email</th><th>Mobile</th><th>Role</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach($users as $usr)
            <tr>
                <td class="fw-semibold">{{ $usr->name }} @if($usr->is(auth()->user()))<span class="badge text-bg-light border">You</span>@endif</td>
                <td>{{ $usr->email }}</td>
                <td>{{ $usr->phone }}</td>
                <td>{{ $usr->roleLabel() }}</td>
                <td>{!! $usr->is_active ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Disabled</span>' !!}</td>
                <td class="text-end text-nowrap">
                    <a href="{{ route('users.edit', $usr) }}" class="btn btn-sm btn-link"><i class="bi bi-pencil"></i></a>
                    @unless($usr->is(auth()->user()))
                        <form method="POST" action="{{ route('users.destroy', $usr) }}" class="d-inline" data-confirm="Delete {{ $usr->name }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger"><i class="bi bi-trash"></i></button></form>
                    @endunless
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="card mt-3"><div class="card-body small">
    <strong>What each role can do</strong>
    <ul class="mb-0 mt-1">
        @foreach(\App\Models\User::ROLES as $k => $label)
            <li><strong>{{ $label }}</strong>: {{ \App\Models\User::ROLE_HELP[$k] }}</li>
        @endforeach
    </ul>
</div></div>
@endsection
