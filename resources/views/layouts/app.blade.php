<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ school('name') }}</title>
    <link href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --navy:#14213d; --amber:#e9a23b; }
        body { font-family: 'Poppins', system-ui, sans-serif; background:#f4f6fa; font-size:.92rem; }
        .sidebar { width:250px; background:var(--navy); min-height:100vh; position:fixed; top:0; left:0; z-index:1030; overflow-y:auto; transition:transform .2s; }
        .sidebar .brand { color:#fff; font-weight:700; padding:1.1rem 1.25rem; border-bottom:1px solid rgba(255,255,255,.08); }
        .sidebar .brand small { color:var(--amber); display:block; font-weight:500; font-size:.7rem; letter-spacing:.08em; text-transform:uppercase; }
        .sidebar .nav-head { color:rgba(255,255,255,.4); font-size:.68rem; text-transform:uppercase; letter-spacing:.1em; padding:1rem 1.25rem .35rem; }
        .sidebar a.nav-link { color:rgba(255,255,255,.75); padding:.5rem 1.25rem; border-left:3px solid transparent; }
        .sidebar a.nav-link:hover { color:#fff; background:rgba(255,255,255,.05); }
        .sidebar a.nav-link.active { color:#fff; background:rgba(233,162,59,.12); border-left-color:var(--amber); }
        .sidebar a.nav-link i { width:1.5rem; display:inline-block; }
        .main { margin-left:250px; }
        .topbar { background:#fff; border-bottom:1px solid #e5e7eb; }
        .card { border:1px solid #e7eaf0; border-radius:.75rem; box-shadow:0 1px 2px rgba(16,24,40,.04); }
        .card-header { background:#fff; border-bottom:1px solid #eef0f4; font-weight:600; }
        .stat .icon { width:44px; height:44px; border-radius:.6rem; display:flex; align-items:center; justify-content:center; font-size:1.3rem; }
        .stat .value { font-size:1.4rem; font-weight:700; color:var(--navy); }
        .btn-primary { --bs-btn-bg:#14213d; --bs-btn-border-color:#14213d; --bs-btn-hover-bg:#0d1730; --bs-btn-hover-border-color:#0d1730; --bs-btn-active-bg:#0d1730; --bs-btn-active-border-color:#0d1730; --bs-btn-disabled-bg:#14213d; --bs-btn-disabled-border-color:#14213d; }
        .btn-outline-primary { --bs-btn-color:#14213d; --bs-btn-border-color:#14213d; --bs-btn-hover-bg:#14213d; --bs-btn-hover-border-color:#14213d; --bs-btn-active-bg:#14213d; --bs-btn-active-border-color:#14213d; }
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
    </style>
    @stack('styles')
</head>
<body>
@php($u = auth()->user())
<nav class="sidebar" id="sidebar">
    <div class="brand"><small>School Management</small>{{ school('name') }}</div>
    <div class="nav flex-column py-2">
        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2"></i>Dashboard</a>

        @if($u->hasRole('admin', 'admission_staff'))
            <div class="nav-head">Admissions</div>
            <a class="nav-link {{ request()->routeIs('admissions.create') ? 'active' : '' }}" href="{{ route('admissions.create') }}"><i class="bi bi-person-plus"></i>New Admission</a>
            <a class="nav-link {{ request()->routeIs('admissions.index') ? 'active' : '' }}" href="{{ route('admissions.index') }}"><i class="bi bi-inboxes"></i>Admission List</a>
        @endif

        <div class="nav-head">Students</div>
        <a class="nav-link {{ request()->routeIs('students.index', 'students.show', 'students.edit', 'students.leave') ? 'active' : '' }}" href="{{ route('students.index') }}"><i class="bi bi-people"></i>All Students</a>
        @if($u->hasRole('admin', 'admission_staff'))
            <a class="nav-link {{ request()->routeIs('students.promote') ? 'active' : '' }}" href="{{ route('students.promote') }}"><i class="bi bi-arrow-up-circle"></i>Promote Students</a>
        @endif

        @if($u->hasRole('admin', 'accountant'))
            <div class="nav-head">Fees & Payments</div>
            <a class="nav-link {{ request()->routeIs('payments.create') ? 'active' : '' }}" href="{{ route('payments.create') }}"><i class="bi bi-cash-coin"></i>Collect Fee</a>
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
            @if($u->isAdmin())
                <a class="nav-link {{ request()->routeIs('notifications.announce') ? 'active' : '' }}" href="{{ route('notifications.announce') }}"><i class="bi bi-megaphone"></i>Announcements</a>
                <a class="nav-link {{ request()->routeIs('notifications.settings') ? 'active' : '' }}" href="{{ route('notifications.settings') }}"><i class="bi bi-bell"></i>Message Settings</a>
            @endif
        @endif

        @if($u->isAdmin())
            <div class="nav-head">Setup</div>
            <a class="nav-link {{ request()->routeIs('classes.*') ? 'active' : '' }}" href="{{ route('classes.index') }}"><i class="bi bi-diagram-3"></i>Classes & Sections</a>
            <a class="nav-link {{ request()->routeIs('sessions.*') ? 'active' : '' }}" href="{{ route('sessions.index') }}"><i class="bi bi-calendar3"></i>Academic Sessions</a>
            <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}"><i class="bi bi-shield-lock"></i>Users & Roles</a>
            <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.edit') }}"><i class="bi bi-gear"></i>Settings</a>
        @endif
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

    <main class="p-3 p-md-4">
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
