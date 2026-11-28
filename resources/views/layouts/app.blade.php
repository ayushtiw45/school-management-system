<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ school('name') }}</title>
    <link rel="icon" href="{{ (\App\Support\Tenant::school()?->isWhiteLabel() && \App\Support\Tenant::school()->logoUrl()) ? \App\Support\Tenant::school()->logoUrl() : asset('favicon.png') }}">
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --navy:#17191C; --amber:#FF4B32; }
        body { font-family: 'Inter', system-ui, sans-serif; background:#f4f6fa; font-size:.92rem; }
        .sidebar { width:250px; background:var(--navy); height:100vh; height:100dvh; position:fixed; top:0; bottom:0; left:0; overscroll-behavior:contain; z-index:1030; overflow-y:auto; transition:transform .2s; }
        .sidebar .brand { color:#fff; font-weight:700; padding:1.1rem 1.25rem; border-bottom:1px solid rgba(255,255,255,.08); }
        .sidebar .brand small { color:var(--amber); display:block; font-weight:500; font-size:.7rem; letter-spacing:.08em; text-transform:uppercase; }
        .sidebar .nav-head { color:rgba(255,255,255,.4); font-size:.68rem; text-transform:uppercase; letter-spacing:.1em; padding:1rem 1.25rem .35rem; }
        .sidebar a.nav-link { color:rgba(255,255,255,.75); padding:.5rem 1.25rem; border-left:3px solid transparent; }
        .sidebar a.nav-link:hover { color:#fff; background:rgba(255,255,255,.05); }
        .sidebar a.nav-link.active { color:#fff; background:rgba(255,75,50,.12); border-left-color:var(--amber); }
        .sidebar a.nav-link i { width:1.5rem; display:inline-block; }
        .sidebar.locked a.nav-link:not(.billing-link) { opacity:.55; }
        .sidebar.locked a.nav-link:not(.billing-link)::after { content:'\F47B'; font-family:'bootstrap-icons'; float:right; color:var(--amber); }
        .main { margin-left:250px; }
        .topbar { background:#fff; border-bottom:1px solid #e5e7eb; }
        .card { border:1px solid #e7eaf0; border-radius:.75rem; box-shadow:0 1px 2px rgba(16,24,40,.04); }
        .card-header { background:#fff; border-bottom:1px solid #eef0f4; font-weight:600; }
        .stat .icon { width:44px; height:44px; border-radius:.6rem; display:flex; align-items:center; justify-content:center; font-size:1.3rem; }
        .stat .value { font-size:1.4rem; font-weight:700; color:var(--navy); }
        .table > :not(caption) > * > * { padding:.55rem .65rem; }
        .table thead th { font-size:.75rem; text-transform:uppercase; letter-spacing:.04em; color:#6b7280; font-weight:600; background:#f9fafb; }
        .avatar { width:36px; height:36px; border-radius:50%; object-fit:cover; background:#e5e7eb; display:inline-flex; align-items:center; justify-content:center; font-weight:600; color:var(--navy); }
        .form-label { font-weight:500; font-size:.83rem; margin-bottom:.25rem; }
        .required::after { content:' *'; color:#dc3545; }
        @media (max-width: 991.98px) {
            .sidebar { transform:translateX(-100%); }
            .sidebar.show { transform:none; }
            .main { margin-left:0; }
        }
        @media print { .sidebar, .topbar, .no-print { display:none !important; } .main { margin:0; } }
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
    @stack('styles')
</head>
<body>
@php($u = auth()->user())
<nav class="sidebar {{ ($access['state'] ?? null) === 'locked' ? 'locked' : '' }}" id="sidebar">
    <div class="brand d-flex align-items-center gap-2">
        @if($brandSchool && $brandSchool->isWhiteLabel())
            @if($brandSchool->logoUrl())<img src="{{ $brandSchool->logoUrl() }}" alt="" style="width:38px;height:38px;border-radius:10px;background:#fff;object-fit:contain">@endif
            <span><small>School Management</small>{{ school('name') }}</span>
        @else
            <img src="{{ asset('assets/tds-mark.png') }}" alt="" style="width:34px;height:34px;border-radius:10px;background:#fff"><span><small>TDS School Management</small>{{ school('name') }}</span>
        @endif
    </div>
    <div class="nav flex-column py-2">
        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2"></i>Dashboard</a>

        @if($u->canAdmit())
            <div class="nav-head">Reception</div>
            <a class="nav-link {{ request()->routeIs('enquiries.create') ? 'active' : '' }}" href="{{ route('enquiries.create') }}"><i class="bi bi-chat-square-text"></i>New Enquiry</a>
            <a class="nav-link {{ request()->routeIs('enquiries.index', 'enquiries.show') ? 'active' : '' }}" href="{{ route('enquiries.index') }}"><i class="bi bi-telephone-forward"></i>Enquiries & Follow-ups</a>
            <a class="nav-link {{ request()->routeIs('admissions.create') ? 'active' : '' }}" href="{{ route('admissions.create') }}"><i class="bi bi-person-plus"></i>New Admission</a>
            <a class="nav-link {{ request()->routeIs('admissions.index') ? 'active' : '' }}" href="{{ route('admissions.index') }}"><i class="bi bi-inboxes"></i>Admission List</a>
            <a class="nav-link {{ request()->routeIs('enquiries.report') ? 'active' : '' }}" href="{{ route('enquiries.report') }}"><i class="bi bi-calendar-day"></i>Daily Report</a>
        @endif

        <div class="nav-head">Students</div>
        <a class="nav-link {{ request()->routeIs('students.index', 'students.show', 'students.edit', 'students.leave') ? 'active' : '' }}" href="{{ route('students.index') }}"><i class="bi bi-people"></i>All Students</a>
        @if($u->hasRole('admin', 'principal'))
            <a class="nav-link {{ request()->routeIs('students.promote') ? 'active' : '' }}" href="{{ route('students.promote') }}"><i class="bi bi-arrow-up-circle"></i>Promote Students</a>
        @endif

        @if($u->canSeeAccounts())
            <div class="nav-head">Fees & Payments @if(! $u->canManageFees())<span class="badge text-bg-secondary ms-1" style="font-size:.6rem">view only</span>@endif</div>
            <a class="nav-link {{ request()->routeIs('payments.create') ? 'active' : '' }}" href="{{ route('payments.create') }}"><i class="bi bi-cash-coin"></i>{{ $u->canManageFees() ? 'Collect Fee' : 'Fees by Class' }}</a>
            <a class="nav-link {{ request()->routeIs('payments.index', 'payments.show') ? 'active' : '' }}" href="{{ route('payments.index') }}"><i class="bi bi-receipt"></i>Payments</a>
            <a class="nav-link {{ request()->routeIs('fees.pending') ? 'active' : '' }}" href="{{ route('fees.pending') }}"><i class="bi bi-hourglass-split"></i>Pending Fees</a>
            <a class="nav-link {{ request()->routeIs('fee-structure.*') ? 'active' : '' }}" href="{{ route('fee-structure.index') }}"><i class="bi bi-grid-3x3"></i>Fee Structure</a>
            <a class="nav-link {{ request()->routeIs('fee-heads.*') ? 'active' : '' }}" href="{{ route('fee-heads.index') }}"><i class="bi bi-tags"></i>Fee Categories</a>

            <div class="nav-head">Reports & Accounts</div>
            <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}"><i class="bi bi-bar-chart-line"></i>Reports</a>
            <a class="nav-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}" href="{{ route('expenses.index') }}"><i class="bi bi-wallet"></i>Expenses</a>
            <a class="nav-link {{ request()->routeIs('expense-categories.*') ? 'active' : '' }}" href="{{ route('expense-categories.index') }}"><i class="bi bi-bookmarks"></i>Expense Categories</a>

            <div class="nav-head">Notifications</div>
            <a class="nav-link {{ request()->routeIs('notifications.index') ? 'active' : '' }}" href="{{ route('notifications.index') }}"><i class="bi bi-chat-dots"></i>Message Log</a>
        @endif
        @if($u->hasRole('admin', 'principal'))
            @if(! $u->canSeeAccounts())<div class="nav-head">Notifications</div>@endif
            <a class="nav-link {{ request()->routeIs('notifications.announce') ? 'active' : '' }}" href="{{ route('notifications.announce') }}"><i class="bi bi-megaphone"></i>Announcements</a>
        @endif
        @if($u->isAdmin())
            <a class="nav-link {{ request()->routeIs('notifications.settings') ? 'active' : '' }}" href="{{ route('notifications.settings') }}"><i class="bi bi-bell"></i>Message Settings</a>
        @endif

        @if($u->hasRole('admin', 'principal'))
            <div class="nav-head">Setup</div>
            <a class="nav-link {{ request()->routeIs('classes.*') ? 'active' : '' }}" href="{{ route('classes.index') }}"><i class="bi bi-diagram-3"></i>Classes & Sections</a>
            <a class="nav-link {{ request()->routeIs('sessions.*') ? 'active' : '' }}" href="{{ route('sessions.index') }}"><i class="bi bi-calendar3"></i>Academic Sessions</a>
        @endif
        @if($u->isAdmin())
            <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}"><i class="bi bi-shield-lock"></i>Users & Roles</a>
            <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.edit') }}"><i class="bi bi-building-gear"></i>School Settings</a>
        @endif
        <div class="nav-head">Plan</div>
        <a class="nav-link billing-link {{ request()->routeIs('billing') ? 'active' : '' }}" href="{{ route('billing') }}"><i class="bi bi-credit-card"></i>Plan & Billing</a>
    </div>
</nav>

<div class="main">
    <header class="topbar px-3 py-2 d-flex align-items-center gap-2 sticky-top">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" onclick="document.getElementById('sidebar').classList.toggle('show')"><i class="bi bi-list"></i></button>
        <h1 class="h6 mb-0 fw-semibold text-truncate">@yield('title', 'Dashboard')</h1>
        <div class="ms-auto dropdown">
            <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-person-circle"></i> <span class="d-none d-sm-inline">{{ $u->name }}</span>
                <span class="badge text-bg-secondary ms-1">{{ $u->roleLabel() }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('password.edit') }}"><i class="bi bi-key me-2"></i>Change Password</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Logout</button></form></li>
            </ul>
        </div>
    </header>

    @isset($access)
        @if($access['state'] === 'locked')
            <div class="px-3 py-2 small d-flex align-items-center gap-2 text-white no-print" style="background:#17191C">
                <i class="bi bi-lock-fill"></i> All features are locked. Start your 7-day trial for just ₹1 to unlock everything.
                <a href="{{ route('billing') }}" class="ms-auto btn btn-sm fw-semibold" style="background:#FF4B32;color:#fff">Start trial for ₹1</a>
            </div>
        @elseif($access['state'] === 'expired')
            <div class="px-3 py-2 small d-flex align-items-center gap-2 text-white bg-danger no-print">
                <i class="bi bi-lock"></i> Your plan has ended. Read-only mode: you can view and print, but new entries are disabled.
                <a href="{{ route('billing') }}" class="ms-auto text-white fw-semibold">Renew</a>
            </div>
        @elseif($access['days'] !== null && $access['days'] <= 3)
            <div class="px-3 py-2 small d-flex align-items-center gap-2 no-print" style="background:#fff7e6;border-bottom:1px solid #f5d9a6">
                <i class="bi bi-hourglass-split"></i> Your plan renews or ends in <strong>{{ $access['days'] }} day(s)</strong>.
                <a href="{{ route('billing') }}" class="ms-auto">Plan & Billing</a>
            </div>
        @endif
    @endisset

    <main class="p-3 p-md-4">
        @php($accountsPage = request()->routeIs('payments.*', 'fees.*', 'fee-structure.*', 'fee-heads.*', 'student-fees.*', 'expenses.*', 'expense-categories.*', 'notifications.index'))
        @if($u->role === 'principal' && $accountsPage)
            <div class="alert alert-secondary py-2 small no-print"><i class="bi bi-eye"></i> View only — accounts are managed by the Accounts team.</div>
            @push('scripts')
            <script>document.querySelectorAll('main form').forEach(f => { if ((f.getAttribute('method') || 'get').toLowerCase() !== 'get') { f.querySelectorAll('button, input[type=submit]').forEach(b => { b.disabled = true; b.title = 'View only'; }); } });</script>
            @endpush
        @endif
        @include('partials.flash')
        @yield('content')
    </main>
</div>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script>
    // Class → Section dropdowns: any <select data-sections-for="#classSelect">
    document.querySelectorAll('select[data-sections-for]').forEach(function (sec) {
        const cls = document.querySelector(sec.dataset.sectionsFor);
        if (!cls) return;
        cls.addEventListener('change', function () {
            const keep = sec.dataset.allLabel || 'Select section';
            sec.innerHTML = '<option value="">' + keep + '</option>';
            if (!cls.value) return;
            fetch('{{ url('classes') }}/' + cls.value + '/sections.json', {headers: {'Accept': 'application/json'}})
                .then(r => r.json())
                .then(list => list.forEach(s => sec.add(new Option(s.name, s.id))));
        });
    });
    document.querySelectorAll('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
</script>
@stack('scripts')
</body>
</html>
