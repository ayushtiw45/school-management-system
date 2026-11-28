<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') · @endif{{ \App\Models\PlatformSetting::get('company_name') }}</title>
    <meta name="description" content="@yield('description', __('School management software for Nursery to Class 12: admissions, fees, receipts, reports and parent reminders. Works online on any computer or phone.'))">
    <link rel="icon" href="{{ asset('favicon.png') }}">
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Inter:wght@400;500;600;700&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* The Digital Strokes brand */
        :root { --b:#FF4B32; --bd:#E23A22; --bs:#FFEEEB; --k:#17191C; --k2:#2B2F35; --g:#929DA7; --gl:#C4CAD0; --ln:#E3E6EA; --sf:#F4F5F7; --mu:#6E7882;
                --hf:'Bricolage Grotesque','Inter',system-ui,sans-serif; --bf:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;
                /* names used by page styles */
                --navy:var(--k); --navy-2:var(--k2); --amber:var(--b); --ink:var(--k2); --muted:var(--mu); --line:var(--ln); --bg:var(--sf); }
        body { font-family:var(--bf); color:var(--k2); background:#fff; }
        html[lang=hi] body { font-family:'Noto Sans Devanagari',var(--bf); }
        h1, h2, h3, h4, h5, .h1, .h2, .h3, .h4, .h5 { font-family:var(--hf); color:var(--k); letter-spacing:-.025em; font-weight:800; }
        html[lang=hi] h1, html[lang=hi] h2, html[lang=hi] h3, html[lang=hi] h4 { font-family:'Noto Sans Devanagari',var(--hf); letter-spacing:0; }
        a { color:var(--b); } a:hover { color:var(--bd); }
        .site-nav { background:rgb(255 255 255 / .92); -webkit-backdrop-filter:saturate(180%) blur(14px); backdrop-filter:saturate(180%) blur(14px); border-bottom:1px solid var(--ln); position:sticky; top:0; z-index:1030; }
        .site-nav .navbar-brand { color:var(--k); font-family:var(--hf); font-weight:800; display:flex; align-items:center; gap:10px; letter-spacing:-.02em; }
        .site-nav .navbar-brand img { width:40px; height:40px; border-radius:12px; object-fit:contain; background:#fff; box-shadow:0 0 0 1px var(--ln); }
        .site-nav .navbar-brand b { color:var(--b); font-weight:800; }
        .site-nav .navbar-brand small { display:block; color:var(--mu); font-family:var(--bf); font-size:.62rem; letter-spacing:.12em; font-weight:600; line-height:1; text-transform:uppercase; }
        .site-nav .nav-link { color:var(--k2); font-weight:600; padding:8px 14px !important; border-radius:999px; }
        .site-nav .nav-link:hover, .site-nav .nav-link.active { color:var(--b); background:var(--bs); }
        .btn-amber { background:var(--b); color:#fff; font-family:var(--hf); font-weight:700; border:0; border-radius:999px; box-shadow:0 10px 24px -12px rgb(255 75 50 / .7); }
        .btn-amber:hover, .btn-amber:focus { background:var(--bd); color:#fff; transform:translateY(-1px); }
        .btn-navy { background:var(--k); color:#fff; font-family:var(--hf); font-weight:700; border-radius:999px; }
        .btn-navy:hover { background:var(--k2); color:#fff; }
        .btn-outline-light, .btn-outline-dark, .btn-outline-secondary { border-radius:999px; }
        .btn-lg { padding:.75rem 1.6rem; }
        .lang-switch a { color:var(--mu); text-decoration:none; font-size:.85rem; font-weight:600; padding:4px 10px; border-radius:999px; border:1px solid var(--ln); }
        .lang-switch a.on { color:#fff; background:var(--k); border-color:var(--k); }
        .eyebrow { color:var(--b); text-transform:uppercase; letter-spacing:.14em; font-size:.78rem; font-weight:700; }
        .card { border:1px solid var(--ln); border-radius:18px; }
        .form-control, .form-select { border-radius:12px; border-color:var(--ln); padding:.6rem .85rem; }
        .form-control:focus, .form-select:focus { border-color:var(--b); box-shadow:0 0 0 .2rem rgb(255 75 50 / .15); }
        .form-label { font-weight:600; font-size:.88rem; margin-bottom:.3rem; color:var(--k2); }
        .site-footer { background:var(--k); color:var(--gl); font-size:.9rem; }
        .site-footer a { color:var(--gl); text-decoration:none; }
        .site-footer a:hover { color:#fff; }
    </style>
    @stack('styles')
</head>
<body>
@php($wa = preg_replace('/\D/', '', (string) \App\Models\PlatformSetting::get('contact_whatsapp')))
<nav class="navbar navbar-expand-lg site-nav">
    <div class="container">
        <a class="navbar-brand" href="{{ route('home') }}"><img src="{{ asset('assets/tds-mark.png') }}" alt="The Digital Strokes"><span><small>The Digital Strokes</small>TDS <b>School</b></span></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-label="Menu"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#features">{{ __('Features') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#screens">{{ __('Screenshots') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#pricing">{{ __('Pricing') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#faq">{{ __('FAQ') }}</a></li>
                <li class="nav-item lang-switch d-flex gap-1 mx-lg-2 my-2 my-lg-0">
                    @foreach(\App\Http\Middleware\SetLocale::LOCALES as $code => $label)
                        <a href="{{ route('locale', $code) }}" class="{{ app()->getLocale() === $code ? 'on' : '' }}">{{ $label }}</a>
                    @endforeach
                </li>
                @auth
                    <li class="nav-item"><a class="btn btn-amber btn-sm px-3" href="{{ auth()->user()->is_super_admin && ! auth()->user()->school_id ? route('super.dashboard') : route('dashboard') }}"><i class="bi bi-speedometer2"></i> {{ __('Open software') }}</a></li>
                    <li class="nav-item"><form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-link nav-link">{{ __('Logout') }}</button></form></li>
                @else
                    <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a></li>
                    <li class="nav-item"><a class="btn btn-amber btn-sm px-3" href="{{ route('register') }}">{{ __('Start for ₹1') }}</a></li>
                @endauth
            </ul>
        </div>
    </div>
</nav>

@yield('content')

<footer class="site-footer py-4 mt-auto">
    <div class="container d-flex flex-wrap gap-3 justify-content-between align-items-center">
        <span class="d-flex align-items-center gap-2"><img src="{{ asset('assets/tds-mark.png') }}" alt="" style="width:28px;height:28px;border-radius:8px;background:#fff">© {{ now()->year }} {{ \App\Models\PlatformSetting::get('company_name') }} · {{ __('A product of') }} <a href="https://thedigitalstrokes.in" target="_blank" rel="noopener">The Digital Strokes</a></span>
        <span class="d-flex flex-wrap gap-3">
            @if($wa)<a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp</a>@endif
            @if(\App\Models\PlatformSetting::get('contact_phone'))<a href="tel:{{ preg_replace('/[^\d+]/', '', \App\Models\PlatformSetting::get('contact_phone')) }}"><i class="bi bi-telephone"></i> {{ \App\Models\PlatformSetting::get('contact_phone') }}</a>@endif
            @if(\App\Models\PlatformSetting::get('contact_email'))<a href="mailto:{{ \App\Models\PlatformSetting::get('contact_email') }}"><i class="bi bi-envelope"></i> {{ \App\Models\PlatformSetting::get('contact_email') }}</a>@endif
            <a href="{{ route('contact') }}">{{ __('Contact us') }}</a>
            <a href="{{ route('privacy') }}">{{ __('Privacy Policy') }}</a>
            <a href="{{ route('terms') }}">{{ __('Terms') }}</a>
            <a href="{{ route('refund') }}">{{ __('Refund Policy') }}</a>
        </span>
    </div>
</footer>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
@stack('scripts')
</body>
</html>
