<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
    <title>@yield('title') · TDS Owner</title>
    <link rel="icon" href="{{ asset('assets/favicon.png') }}">
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <style>
        body { background:#f4f6fa; font-size:.92rem; }
        .admin-nav { background:#14213d; } .admin-nav .nav-link { color:rgba(255,255,255,.75); } .admin-nav .nav-link.active, .admin-nav .nav-link:hover { color:#fff; }
        .admin-nav .navbar-brand { color:#fff; font-weight:700; } .admin-nav .navbar-toggler-icon { filter:invert(1); }
        .card { border:1px solid #e7eaf0; border-radius:12px; } .stat .v { font-size:1.5rem; font-weight:700; color:#14213d; }
        .table thead th { font-size:.74rem; text-transform:uppercase; color:#6b7280; background:#f9fafb; }
        .btn-navy { background:#14213d; color:#fff; } .btn-navy:hover { background:#0d1730; color:#fff; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg admin-nav">
    <div class="container-fluid px-3">
        <a class="navbar-brand" href="{{ route('super.dashboard') }}"><img src="{{ asset('assets/logo.png') }}" width="28" class="rounded me-2" alt="">TDS Owner</a>
        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#an"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="an">
            <ul class="navbar-nav me-auto">
                @foreach(['super.dashboard' => 'Dashboard', 'super.schools' => 'Schools', 'super.subscriptions' => 'Autopay', 'super.settings' => 'Settings'] as $r => $l)
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs($r.'*') ? 'active' : '' }}" href="{{ route($r) }}">{{ $l }}</a></li>
                @endforeach
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" href="{{ route('home') }}" target="_blank">View website</a></li>
                <li class="nav-item"><form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-link nav-link">Logout</button></form></li>
            </ul>
        </div>
    </div>
</nav>
<main class="container-fluid p-3 p-md-4">
    @include('site.flash')
    @yield('content')
</main>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
