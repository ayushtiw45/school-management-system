@extends('layouts.site')
@push('styles')
<style>
    .hero { background:radial-gradient(1200px 500px at 80% -10%, #4a2a24 0%, transparent 60%), var(--navy); color:#fff; padding:64px 0 0; overflow:hidden; }
    .hero h1 { color:#fff; font-size:clamp(2rem, 4.6vw, 3.2rem); font-weight:700; }
    .hero h1 span { color:var(--amber); }
    .hero .lead { color:rgba(255,255,255,.82); max-width:640px; }
    .hero .tagline { color:var(--amber); font-weight:500; }
    .hero .meta { color:rgba(255,255,255,.6); font-size:.85rem; }
    .frame { margin-top:48px; background:#2B2F35; border-radius:14px 14px 0 0; padding:10px 10px 0; box-shadow:0 -10px 60px rgba(0,0,0,.35); }
    .frame .dots { display:flex; gap:6px; padding:2px 6px 10px; } .frame .dots i { width:10px; height:10px; border-radius:50%; background:#3b4a6b; }
    .frame img { border-radius:8px 8px 0 0; width:100%; }
    section { padding:72px 0; }
    section[id] { scroll-margin-top:70px; }
    .pillar .ic, .feat .tag { width:46px; height:46px; border-radius:12px; display:grid; place-items:center; background:#FFEEEB; color:#E23A22; font-size:1.3rem; }
    .feat .tag { width:32px; height:32px; background:var(--navy); color:var(--amber); font-weight:700; font-size:.9rem; border-radius:8px; }
    .feat ul { padding-left:18px; color:var(--muted); font-size:.92rem; margin:0; }
    .bg-soft { background:var(--bg); }
    .shot { border:1px solid var(--line); border-radius:14px; overflow:hidden; box-shadow:0 20px 50px rgba(20,33,61,.12); max-width:1040px; margin:0 auto; }
    .shot img { width:100%; }
    .tabs .btn { border-radius:999px; }
    .install-sec { background:var(--navy); color:#fff; }
    .install-sec h2 { color:#fff; }
    .step { background:var(--navy-2); border-radius:14px; padding:22px; height:100%; }
    .step .num { width:34px; height:34px; border-radius:50%; background:var(--amber); color:var(--navy); font-weight:700; display:grid; place-items:center; margin-bottom:12px; }
    .step h3 { color:#fff; font-size:1rem; } .step p { color:rgba(255,255,255,.72); font-size:.92rem; margin:0; }
    .note { background:rgba(255,75,50,.12); border:1px solid rgba(255,75,50,.4); border-radius:12px; padding:16px 18px; color:rgba(255,255,255,.85); }
    .note b { color:var(--amber); }
    .plan { border:1px solid var(--line); border-radius:16px; padding:28px; height:100%; display:flex; flex-direction:column; }
    .plan.best { border:2px solid var(--amber); box-shadow:0 20px 40px rgba(255,75,50,.15); }
    .plan .price { font-size:2rem; font-weight:700; color:var(--navy); }
    .plan ul { list-style:none; padding:0; margin:16px 0 22px; }
    .plan li { padding:5px 0; } .plan li i { color:#16a34a; margin-right:6px; }
    .plan .btn { margin-top:auto; }
    .faq details { background:#fff; border:1px solid var(--line); border-radius:12px; padding:16px 20px; margin-bottom:10px; }
    .faq summary { cursor:pointer; font-weight:600; color:var(--navy); }
    .faq details p { margin:10px 0 0; color:var(--muted); }
</style>
@endpush
@section('content')
@php($wa = preg_replace('/\D/', '', (string) \App\Models\PlatformSetting::get('contact_whatsapp')))

<header class="hero">
    <div class="container">
        @php($latest = \App\Support\Changelog::latest())
        @if($latest)
            <a href="{{ route('whats-new') }}" class="d-inline-flex align-items-center gap-2 mb-3 px-3 py-1 rounded-pill text-decoration-none small" style="background:#FFEEEB;color:#E23A22">
                <span class="badge rounded-pill" style="background:#FF4B32">{{ __('New') }} v{{ $latest['version'] }}</span>
                <span class="text-truncate" style="max-width:420px">{{ \App\Support\Changelog::text($latest['title']) }}</span><i class="bi bi-arrow-right"></i>
            </a><br>
        @endif
        <div class="eyebrow">{{ __('For Nursery to Class 12') }}</div>
        <h1 class="my-3">TDS School <span>Management</span> Software</h1>
        <p class="lead fs-5">{{ __('Admissions, student records, fee collection, receipts, reports and parent reminders — online, in one simple software. Open it in any browser on laptop, tablet or phone.') }}</p>
        <p class="tagline">{{ __('No installation. Your school\'s data is private and safe.') }}</p>
        <div class="d-flex flex-wrap gap-3 my-4">
            <a href="{{ route('register') }}" class="btn btn-amber btn-lg px-4"><i class="bi bi-rocket-takeoff me-2"></i>{{ __('Start for ₹:trial', ['trial' => $trialPrice]) }}</a>
            <a href="{{ route('login') }}" class="btn btn-outline-light btn-lg px-4">{{ __('Log in') }}</a>
        </div>
        <div class="meta">{{ __(':days-day trial for ₹:trial · then ₹:monthly/month · cancel any time', ['days' => $trialDays, 'trial' => $trialPrice, 'monthly' => $monthlyPrice]) }}</div>
        <div class="frame"><div class="dots"><i></i><i></i><i></i></div><img src="{{ asset('assets/shot-dashboard.jpg') }}" alt="{{ __('Dashboard') }}"></div>
    </div>
</header>

<section>
    <div class="container">
        <div class="row g-4">
            @foreach([
                ['bi-shield-lock', __('Private data for every school'), __('Each school has its own account. Only your logins can see your students, fees and payments.')],
                ['bi-globe2', __('Works from anywhere'), __('Nothing to install. Principal, accountant and office staff log in from any computer or phone.')],
                ['bi-clock-history', __(':days days for just ₹:trial', ['days' => $trialDays, 'trial' => $trialPrice]), __('Every feature unlocks after the ₹:trial payment. Then ₹:monthly per month by Autopay — cancel any time.', ['trial' => $trialPrice, 'monthly' => $monthlyPrice])],
            ] as [$icon, $title, $text])
                <div class="col-md-4"><div class="card p-4 h-100 pillar">
                    <div class="ic mb-3"><i class="bi {{ $icon }}"></i></div>
                    <h3 class="h5">{{ $title }}</h3><p class="text-muted mb-0">{{ $text }}</p>
                </div></div>
            @endforeach
        </div>
    </div>
</section>

<section id="features" class="bg-soft">
    <div class="container">
        <div class="text-center mb-5">
            <div class="eyebrow">{{ __('Everything a school office needs') }}</div>
            <h2 class="fw-bold mt-2">{{ __('12 modules, one simple program') }}</h2>
            <p class="text-muted">{{ __('From the admission form to the year-end fee summary.') }}</p>
        </div>
        <div class="row g-3">
            @foreach([
                ['A', __('Admin Dashboard'), [__('Total students & admissions'), __('Today\'s and session fee collection'), __('Pending fees, class-wise strength')]],
                ['B', __('Admissions'), [__('Office and online admission form'), __('Auto admission number, photo & documents'), __('Print admission form & receipt')]],
                ['C', __('Student Records'), [__('Search, filters, full profile'), __('ID cards for a student or whole class'), __('Promotion and TC / leaving records')]],
                ['D', __('Classes & Sections'), [__('Nursery to Class 12 ready'), __('Sections A, B, C…'), __('Academic sessions (April–March)')]],
                ['E', __('Fee Management'), [__('Class-wise fee structure'), __('Monthly / quarterly / yearly fees, concessions'), __('Dues created automatically')]],
                ['F', __('Payments & Receipts'), [__('Cash, UPI, bank, cheque'), __('Partial payments, late fine, discount'), __('Printable receipts with unique numbers')]],
                ['G', __('Fee Reports'), [__('Daily, monthly, class-wise, student-wise'), __('Pending, discount, collection summary'), __('Download in Excel or PDF')]],
                ['H', __('Expenses'), [__('Staff salary, electricity, maintenance'), __('Bill upload'), __('Monthly income vs expense')]],
                ['I', __('Users & Roles'), [__('Admin, Principal, Reception, Accounts, Transport'), __('Each sees only their screens'), __('Secure login')]],
                ['J', __('SMS & WhatsApp'), [__('Fee due reminders, payment messages'), __('Announcements to parents'), __('Free one-click WhatsApp reminders')]],
                ['K', __('Transport Management'), [__('Buses, routes, stops, drivers & conductors'), __('Fuel, service, repairs, insurance & PUC reminders'), __('Bus-wise and route-wise profit & loss')]],
                ['L', __('Reception & Enquiries'), [__('Visitor enquiries and follow-ups'), __('Admission pending until fee is paid'), __('Unique Student ID')]],
            ] as [$tag, $title, $items])
                <div class="col-sm-6 col-lg-4 col-xl-3"><div class="card p-3 h-100 feat">
                    <span class="tag mb-2">{{ $tag }}</span>
                    <h3 class="h6 fw-semibold">{{ $title }}</h3>
                    <ul>@foreach($items as $it)<li>{{ $it }}</li>@endforeach</ul>
                </div></div>
            @endforeach
        </div>
    </div>
</section>

<section id="screens">
    <div class="container text-center">
        <div class="eyebrow">{{ __('Screenshots') }}</div>
        <h2 class="fw-bold mt-2">{{ __('Simple screens your staff will understand') }}</h2>
        @php($shots = [
            ['shot-dashboard', __('Dashboard'), __('See students, today\'s collection and pending fees at a glance.')],
            ['shot-collect', __('Collect Fee'), __('Tick the fees, choose Cash / UPI / Cheque and print the receipt.')],
            ['shot-receipt', __('Receipt'), __('Clean printable receipt with a unique number and balance due.')],
            ['shot-pending', __('Pending Fees'), __('Who has not paid, how much, and one-click WhatsApp reminders.')],
            ['shot-reports', __('Reports'), __('Class-wise and other reports, downloadable in Excel and PDF.')],
            ['shot-structure', __('Fee Structure'), __('Set the fees for every class once; dues are created automatically.')],
        ])
        <div class="tabs d-flex flex-wrap justify-content-center gap-2 my-4" id="tabs">
            @foreach($shots as $i => [$img, $label, $cap])
                <button type="button" class="btn btn-sm {{ $i ? 'btn-outline-secondary' : 'btn-navy' }}" data-img="{{ asset('assets/'.$img.'.jpg') }}" data-cap="{{ $cap }}">{{ $label }}</button>
            @endforeach
        </div>
        <div class="shot"><img id="shotImg" src="{{ asset('assets/shot-dashboard.jpg') }}" alt=""></div>
        <p class="text-muted mt-3" id="shotCap">{{ $shots[0][2] }}</p>
    </div>
</section>

<section id="install" class="install-sec">
    <div class="container">
        <div class="text-center mb-5">
            <div class="eyebrow">{{ __('Getting started') }}</div>
            <h2 class="fw-bold mt-2">{{ __('Ready in 5 minutes') }}</h2>
            <p class="text-white-50">{{ __('No technical knowledge needed.') }}</p>
        </div>
        <div class="row g-3">
            @foreach([
                [__('Register your school'), __('Enter your school name, mobile number and create your login.')],
                [__('Pay ₹:trial', ['trial' => $trialPrice]), __('All features unlock for :days days. Autopay continues at ₹:monthly/month.', ['days' => $trialDays, 'monthly' => $monthlyPrice])],
                [__('Set up classes & fees'), __('Nursery to Class 12 are ready. Enter your fee amounts once.')],
                [__('Add your staff'), __('Create logins for the accountant and admission desk, and start admissions.')],
            ] as $i => [$t, $p])
                <div class="col-sm-6 col-lg-3"><div class="step"><div class="num">{{ $i + 1 }}</div><h3>{{ $t }}</h3><p>{{ $p }}</p></div></div>
            @endforeach
        </div>
        <div class="text-center mt-4"><a href="{{ route('register') }}" class="btn btn-amber btn-lg px-4">{{ __('Register your school') }}</a></div>
    </div>
</section>

<section id="pricing">
    <div class="container">
        <div class="text-center mb-5">
            <div class="eyebrow">{{ __('Pricing') }}</div>
            <h2 class="fw-bold mt-2">{{ __('One simple plan') }}</h2>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-md-8 col-lg-6"><div class="plan best">
                <h3 class="h5">{{ __('School Plan') }}</h3>
                <div class="price">₹{{ $monthlyPrice }} <small class="fs-6 text-muted fw-normal">{{ __('per month') }}</small></div>
                <div class="text-success fw-semibold">{{ __('First :days days for just ₹:trial', ['days' => $trialDays, 'trial' => $trialPrice]) }}</div>
                <ul><li><i class="bi bi-check-circle-fill"></i>{{ __('All 10 modules') }}</li><li><i class="bi bi-check-circle-fill"></i>{{ __('Unlimited students and staff logins') }}</li><li><i class="bi bi-check-circle-fill"></i>{{ __('Automatic monthly payment by UPI Autopay or card') }}</li><li><i class="bi bi-check-circle-fill"></i>{{ __('Cancel any time, no charge if you cancel during the trial') }}</li><li><i class="bi bi-check-circle-fill"></i>{{ __('Free updates and support') }}</li></ul>
                <a href="{{ route('register') }}" class="btn btn-amber btn-lg">{{ __('Start for ₹:trial', ['trial' => $trialPrice]) }}</a>
            </div></div>
        </div>
    </div>
</section>

<section id="faq" class="bg-soft faq">
    <div class="container" style="max-width:860px">
        <div class="text-center mb-4">
            <div class="eyebrow">{{ __('FAQ') }}</div>
            <h2 class="fw-bold mt-2">{{ __('Common questions') }}</h2>
        </div>
        @foreach([
            [__('Do I need to install anything?'), __('No. TDS works in the browser (Chrome, Edge, Safari) on any laptop, tablet or phone. Just log in.')],
            [__('Can other schools see my data?'), __('Never. Every school has its own private account. Only the logins your school creates can see your data.')],
            [__('How does the ₹1 trial work?'), __('After registering, pay ₹1 and approve Autopay. All features unlock immediately for 7 days. After that the monthly fee is paid automatically; cancel any time before that and nothing more is charged.')],
            [__('What happens if I cancel or a payment fails?'), __('Your data is not deleted. Your account becomes read-only: you can still view students, print receipts and download reports. Restart the plan any time to add new records again.')],
            [__('Can my accountant and office staff use it?'), __('Yes. Create separate logins for the accountant and admission staff; each sees only the screens for their work.')],
            [__('Can I download my data?'), __('Yes. Every report can be downloaded in Excel or PDF at any time.')],
        ] as [$q, $a])
            <details><summary>{{ $q }}</summary><p>{{ $a }}</p></details>
        @endforeach
    </div>
</section>

<section class="text-center">
    <div class="container">
        <h2 class="fw-bold">{{ __('Start today for ₹:trial', ['trial' => $trialPrice]) }}</h2>
        <p class="text-muted">{{ __('Join schools that manage admissions and fees the easy way.') }}</p>
        <div class="d-flex flex-wrap justify-content-center gap-3 mt-3">
            <a href="{{ route('register') }}" class="btn btn-amber btn-lg px-4">{{ __('Register your school') }}</a>
            @if($wa)<a href="https://wa.me/{{ $wa }}?text={{ rawurlencode(__('Hello, I want to know about TDS School Management Software.')) }}" target="_blank" rel="noopener" class="btn btn-success btn-lg px-4"><i class="bi bi-whatsapp me-2"></i>{{ __('Talk on WhatsApp') }}</a>@endif
        </div>
    </div>
</section>
@endsection
@push('scripts')
<script>
    document.querySelectorAll('#tabs button').forEach(b => b.addEventListener('click', () => {
        document.querySelectorAll('#tabs button').forEach(x => { x.classList.remove('btn-navy'); x.classList.add('btn-outline-secondary'); });
        b.classList.add('btn-navy'); b.classList.remove('btn-outline-secondary');
        document.getElementById('shotImg').src = b.dataset.img; document.getElementById('shotCap').textContent = b.dataset.cap;
    }));
</script>
@endpush
