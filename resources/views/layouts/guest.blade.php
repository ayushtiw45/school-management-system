<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ school('name') }}</title>
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family:'Inter', sans-serif; background:#17191C; min-height:100vh; font-size:.92rem; }
        .card { border:0; border-radius:1rem; }
        .accent { color:#FF4B32; }
        .form-label { font-weight:500; font-size:.83rem; margin-bottom:.25rem; }
        .required::after { content:' *'; color:#dc3545; }
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
@yield('content')
</body>
</html>
