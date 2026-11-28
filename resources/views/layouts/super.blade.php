<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
    <title>@yield('title') · TDS Owner</title>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="{{ asset('favicon.png') }}">
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <style>
        body { background:#f4f6fa; font-size:.92rem; }
        .admin-nav { background:#17191C; } .admin-nav .nav-link { color:rgba(255,255,255,.75); } .admin-nav .nav-link.active, .admin-nav .nav-link:hover { color:#fff; }
        .admin-nav .navbar-brand { color:#fff; font-weight:700; } .admin-nav .navbar-toggler-icon { filter:invert(1); }
        .card { border:1px solid #e7eaf0; border-radius:12px; } .stat .v { font-size:1.5rem; font-weight:700; color:#17191C; }
        .table thead th { font-size:.74rem; text-transform:uppercase; color:#6b7280; background:#f9fafb; }
        .btn-navy { background:#17191C; color:#fff; } .btn-navy:hover { background:#2B2F35; color:#fff; }
        /* The Digital Strokes brand */
        h1, h2, h3, h4, h5, .h1, .h2, .h3, .h4, .h5, .brand, .navbar-brand, .stat .value, .stat .v { font-family:'Bricolage Grotesque','Inter',system-ui,sans-serif; letter-spacing:-.02em; }
        a { color:#FF4B32; } a:hover { color:#E23A22; }
        .btn-primary { --bs-btn-bg:#FF4B32; --bs-btn-border-color:#FF4B32; --bs-btn-hover-bg:#E23A22; --bs-btn-hover-border-color:#E23A22; --bs-btn-active-bg:#E23A22; --bs-btn-active-border-color:#E23A22; --bs-btn-disabled-bg:#FF4B32; --bs-btn-disabled-border-color:#FF4B32; --bs-btn-color:#fff; --bs-btn-hover-color:#fff; }
        .btn-outline-primary { --bs-btn-color:#FF4B32; --bs-btn-border-color:#FF4B32; --bs-btn-hover-bg:#FF4B32; --bs-btn-hover-border-color:#FF4B32; --bs-btn-active-bg:#E23A22; --bs-btn-active-border-color:#E23A22; --bs-btn-hover-color:#fff; }
        .btn-check:checked + .btn-outline-primary { background:#FF4B32; border-color:#FF4B32; color:#fff; }
        .form-control:focus, .form-select:focus { border-color:#FF4B32; box-shadow:0 0 0 .2rem rgb(255 75 50 / .15); }
        .form-check-input:checked { background-color:#FF4B32; border-color:#FF4B32; }
        .nav-tabs .nav-link.active { color:#FF4B32; }
        .page-link { color:#FF4B32; } .active > .page-link { background:#FF4B32; border-color:#FF4B32; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg admin-nav">
    <div class="container-fluid px-3">
        <a class="navbar-brand" href="{{ route('super.dashboard') }}"><img src="{{ asset('assets/tds-mark.png') }}" width="30" class="rounded me-2 bg-white" alt="">TDS Owner</a>
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
