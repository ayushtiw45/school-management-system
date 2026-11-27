<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') · @endif{{ \App\Models\Setting::get('company_name') }}</title>
    <meta name="description" content="@yield('description', __('School management software for Nursery to Class 8: admissions, fees, receipts, reports and parent reminders. Runs on your own Windows laptop.'))">
    <link rel="icon" href="{{ asset('assets/favicon.png') }}">
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --navy:#14213d; --navy-2:#1d2d52; --amber:#e9a23b; --ink:#1f2937; --muted:#6b7280; --line:#e6e9f0; --bg:#f5f7fb; }
        body { font-family:'Poppins','Noto Sans Devanagari',system-ui,sans-serif; color:var(--ink); background:#fff; }
        html[lang=hi] body { font-family:'Noto Sans Devanagari','Poppins',system-ui,sans-serif; }
        .site-nav { background:var(--navy); position:sticky; top:0; z-index:1030; }
        .site-nav .navbar-brand { color:#fff; font-weight:700; display:flex; align-items:center; gap:10px; }
        .site-nav .navbar-brand img { width:36px; height:36px; border-radius:8px; }
        .site-nav .navbar-brand small { display:block; color:var(--amber); font-size:.6rem; letter-spacing:.12em; font-weight:600; line-height:1; text-transform:uppercase; }
        .site-nav .nav-link { color:rgba(255,255,255,.8); }
        .site-nav .nav-link:hover, .site-nav .nav-link.active { color:#fff; }
        .site-nav .navbar-toggler { border-color:rgba(255,255,255,.3); }
        .site-nav .navbar-toggler-icon { filter:invert(1); }
        .btn-amber { background:var(--amber); color:var(--navy); font-weight:600; border:0; }
        .btn-amber:hover { background:#f0b04f; color:var(--navy); }
        .btn-navy { background:var(--navy); color:#fff; font-weight:500; }
        .btn-navy:hover { background:#0d1730; color:#fff; }
        .lang-switch a { color:rgba(255,255,255,.65); text-decoration:none; font-size:.85rem; padding:2px 6px; border-radius:6px; }
        .lang-switch a.on { color:var(--navy); background:#fff; }
        .eyebrow { color:var(--amber); text-transform:uppercase; letter-spacing:.12em; font-size:.78rem; font-weight:600; }
        h1, h2, h3, h4 { color:var(--navy); }
        .card { border:1px solid var(--line); border-radius:14px; }
        .form-label { font-weight:500; font-size:.88rem; margin-bottom:.3rem; }
        .site-footer { background:var(--navy); color:rgba(255,255,255,.65); font-size:.9rem; }
        .site-footer a { color:rgba(255,255,255,.8); text-decoration:none; }
        .site-footer a:hover { color:#fff; }
    </style>
    @stack('styles')
</head>
<body>
@php($wa = preg_replace('/\D/', '', (string) \App\Models\Setting::get('contact_whatsapp')))
<nav class="navbar navbar-expand-lg site-nav">
    <div class="container">
        <a class="navbar-brand" href="{{ route('home') }}"><img src="{{ asset('assets/logo.png') }}" alt=""><span><small>{{ __('School Management') }}</small>TDS Software</span></a>
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
                    @if(auth()->user()->is_admin)
                        <li class="nav-item"><a class="nav-link" href="{{ route('admin.dashboard') }}"><i class="bi bi-speedometer2"></i> Admin</a></li>
                    @endif
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('account') ? 'active' : '' }}" href="{{ route('account') }}"><i class="bi bi-person-circle"></i> {{ __('My Account') }}</a></li>
                    <li class="nav-item"><form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-link nav-link">{{ __('Logout') }}</button></form></li>
                @else
                    <li class="nav-item"><a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a></li>
                    <li class="nav-item"><a class="btn btn-amber btn-sm px-3" href="{{ route('register') }}">{{ __('Sign up free') }}</a></li>
                @endauth
            </ul>
        </div>
    </div>
</nav>

@yield('content')

<footer class="site-footer py-4 mt-auto">
    <div class="container d-flex flex-wrap gap-3 justify-content-between align-items-center">
        <span>© {{ now()->year }} {{ \App\Models\Setting::get('company_name') }}</span>
        <span class="d-flex flex-wrap gap-3">
            @if($wa)<a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp"></i> WhatsApp</a>@endif
            @if(\App\Models\Setting::get('contact_phone'))<a href="tel:{{ preg_replace('/[^\d+]/', '', \App\Models\Setting::get('contact_phone')) }}"><i class="bi bi-telephone"></i> {{ \App\Models\Setting::get('contact_phone') }}</a>@endif
            @if(\App\Models\Setting::get('contact_email'))<a href="mailto:{{ \App\Models\Setting::get('contact_email') }}"><i class="bi bi-envelope"></i> {{ \App\Models\Setting::get('contact_email') }}</a>@endif
            <a href="{{ route('privacy') }}">{{ __('Privacy Policy') }}</a>
            <a href="{{ route('terms') }}">{{ __('Terms') }}</a>
        </span>
    </div>
</footer>
<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
@stack('scripts')
</body>
</html>
