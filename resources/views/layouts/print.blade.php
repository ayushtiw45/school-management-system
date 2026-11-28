<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ school('name') }}</title>
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family:'Inter', sans-serif; background:#eef0f4; font-size:.88rem; color:#111827; }
        .sheet { background:#fff; max-width:800px; margin:1.5rem auto; padding:2rem; box-shadow:0 2px 10px rgba(0,0,0,.08); }
        .school-head { border-bottom:3px solid #17191C; padding-bottom:.75rem; margin-bottom:1rem; text-align:center; }
        .school-head h2 { color:#17191C; font-weight:700; margin:0; font-size:1.4rem; }
        .table td, .table th { padding:.4rem .5rem; }
        @media print { body { background:#fff; } .sheet { box-shadow:none; margin:0; max-width:none; padding:0; } .no-print { display:none !important; } }
        @page { margin:12mm; }
    </style>
    @stack('styles')
</head>
<body>
<div class="text-center my-3 no-print">
    <button class="btn btn-dark btn-sm" onclick="window.print()">Print / Save PDF</button>
    <a class="btn btn-outline-secondary btn-sm" href="{{ url()->previous() }}">Back</a>
</div>
@yield('content')
@unless(\App\Support\Tenant::school()?->isWhiteLabel())
    <div class="text-center small text-muted my-2" style="font-size:.7rem">Made with TDS School Management · thedigitalstrokes.in</div>
@endunless
</body>
</html>
