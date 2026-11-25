<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ school('name') }}</title>
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family:'Poppins', sans-serif; background:#14213d; min-height:100vh; font-size:.92rem; }
        .card { border:0; border-radius:1rem; }
        .btn-primary { --bs-btn-bg:#14213d; --bs-btn-border-color:#14213d; --bs-btn-hover-bg:#0d1730; --bs-btn-hover-border-color:#0d1730; --bs-btn-active-bg:#0d1730; --bs-btn-active-border-color:#0d1730; --bs-btn-disabled-bg:#14213d; --bs-btn-disabled-border-color:#14213d; }
        .btn-outline-primary { --bs-btn-color:#14213d; --bs-btn-border-color:#14213d; --bs-btn-hover-bg:#14213d; --bs-btn-hover-border-color:#14213d; --bs-btn-active-bg:#14213d; --bs-btn-active-border-color:#14213d; }
        .accent { color:#e9a23b; }
        .form-label { font-weight:500; font-size:.83rem; margin-bottom:.25rem; }
        .required::after { content:' *'; color:#dc3545; }
    </style>
</head>
<body>
@yield('content')
</body>
</html>
