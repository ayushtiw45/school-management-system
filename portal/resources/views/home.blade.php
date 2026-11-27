@extends('layouts.site')
@push('styles')
<style>
    .hero { background:radial-gradient(1200px 500px at 80% -10%, #2a3f6e 0%, transparent 60%), var(--navy); color:#fff; padding:64px 0 0; overflow:hidden; }
    .hero h1 { color:#fff; font-size:clamp(2rem, 4.6vw, 3.2rem); font-weight:700; }
    .hero h1 span { color:var(--amber); }
    .hero .lead { color:rgba(255,255,255,.82); max-width:640px; }
    .hero .tagline { color:var(--amber); font-weight:500; }
    .hero .meta { color:rgba(255,255,255,.6); font-size:.85rem; }
    .frame { margin-top:48px; background:#0d1730; border-radius:14px 14px 0 0; padding:10px 10px 0; box-shadow:0 -10px 60px rgba(0,0,0,.35); }
    .frame .dots { display:flex; gap:6px; padding:2px 6px 10px; } .frame .dots i { width:10px; height:10px; border-radius:50%; background:#3b4a6b; }
    .frame img { border-radius:8px 8px 0 0; width:100%; }
    section { padding:72px 0; }
    section[id] { scroll-margin-top:70px; }
    .pillar .ic, .feat .tag { width:46px; height:46px; border-radius:12px; display:grid; place-items:center; background:#fff4e2; color:#b87309; font-size:1.3rem; }
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
    .note { background:rgba(233,162,59,.12); border:1px solid rgba(233,162,59,.4); border-radius:12px; padding:16px 18px; color:rgba(255,255,255,.85); }
    .note b { color:var(--amber); }
    .plan { border:1px solid var(--line); border-radius:16px; padding:28px; height:100%; display:flex; flex-direction:column; }
    .plan.best { border:2px solid var(--amber); box-shadow:0 20px 40px rgba(233,162,59,.15); }
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
@php($wa = preg_replace('/\D/', '', (string) \App\Models\Setting::get('contact_whatsapp')))

<header class="hero">
    <div class="container">
        <div class="eyebrow">{{ __('For Nursery to Class 8') }}</div>
        <h1 class="my-3">TDS School <span>Management</span> Software</h1>
        <p class="lead fs-5">{{ __('Admissions, student records, fee collection, receipts, reports and parent reminders — in one simple program that runs on your own Windows laptop.') }}</p>
        <p class="tagline">{{ __('Your data stays on your laptop. No internet needed.') }}</p>
        <div class="d-flex flex-wrap gap-3 my-4">
            <a href="{{ route('install') }}" class="btn btn-amber btn-lg px-4"><i class="bi bi-windows me-2"></i>{{ __('Install for Windows') }} · {{ __(':days days free', ['days' => $trialDays]) }}</a>
            <a href="#screens" class="btn btn-outline-light btn-lg px-4">{{ __('See screenshots') }}</a>
        </div>
        <div class="meta">{{ __('Windows 10 / 11 (64-bit) · Free account · Setup takes 2 minutes') }}</div>
        <div class="frame"><div class="dots"><i></i><i></i><i></i></div><img src="{{ asset('assets/shot-dashboard.jpg') }}" alt="{{ __('Dashboard') }}"></div>
    </div>
</header>

<section>
    <div class="container">
        <div class="row g-4">
            @foreach([
                ['bi-laptop', __('Data stays on your laptop'), __('Student and fee records are saved only on your computer. Nothing is uploaded to anyone\'s server.')],
                ['bi-wifi-off', __('Works without internet'), __('Collect fees and print receipts even when the internet is down. Internet is only needed for SMS/WhatsApp.')],
                ['bi-clock-history', __('Try free for :days days', ['days' => $trialDays]), __('Use every feature during the trial. After that, your data is never locked: you can always view and print it.')],
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
            <h2 class="fw-bold mt-2">{{ __('10 modules, one simple program') }}</h2>
            <p class="text-muted">{{ __('From the admission form to the year-end fee summary.') }}</p>
        </div>
        <div class="row g-3">
            @foreach([
                ['A', __('Admin Dashboard'), [__('Total students & admissions'), __('Today\'s and session fee collection'), __('Pending fees, class-wise strength')]],
                ['B', __('Admissions'), [__('Office and online admission form'), __('Auto admission number, photo & documents'), __('Print admission form & receipt')]],
                ['C', __('Student Records'), [__('Search, filters, full profile'), __('ID cards for a student or whole class'), __('Promotion and TC / leaving records')]],
                ['D', __('Classes & Sections'), [__('Nursery to Class 8 ready'), __('Sections A, B, C…'), __('Academic sessions (April–March)')]],
                ['E', __('Fee Management'), [__('Class-wise fee structure'), __('Monthly / quarterly / yearly fees, concessions'), __('Dues created automatically')]],
                ['F', __('Payments & Receipts'), [__('Cash, UPI, bank, cheque'), __('Partial payments, late fine, discount'), __('Printable receipts with unique numbers')]],
                ['G', __('Fee Reports'), [__('Daily, monthly, class-wise, student-wise'), __('Pending, discount, collection summary'), __('Download in Excel or PDF')]],
                ['H', __('Expenses'), [__('Staff salary, electricity, maintenance'), __('Bill upload'), __('Monthly income vs expense')]],
                ['I', __('Users & Roles'), [__('Admin, Accountant, Admission Staff'), __('Each sees only their screens'), __('Secure login')]],
                ['J', __('SMS & WhatsApp'), [__('Fee due reminders, payment messages'), __('Announcements to parents'), __('Free one-click WhatsApp reminders')]],
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
            <div class="eyebrow">{{ __('Installation') }}</div>
            <h2 class="fw-bold mt-2">{{ __('Ready in 2 minutes') }}</h2>
            <p class="text-white-50">{{ __('No technical knowledge needed. Everything required is included in one file.') }}</p>
        </div>
        <div class="row g-3">
            @foreach([
                [__('Create a free account'), __('Click Install for Windows and sign up with your school name and mobile number.')],
                [__('Download & install'), __('The setup file downloads. Double-click it and press Next → Install.')],
                [__('Set up your school'), __('Enter your school name and create your admin login. Your :days-day trial starts.', ['days' => $trialDays])],
                [__('Start working'), __('Open it any time from the desktop icon. Set your fees and start admissions.')],
            ] as $i => [$t, $p])
                <div class="col-sm-6 col-lg-3"><div class="step"><div class="num">{{ $i + 1 }}</div><h3>{{ $t }}</h3><p>{{ $p }}</p></div></div>
            @endforeach
        </div>
        <div class="note mt-4"><b>{{ __('"Windows protected your PC" message?') }}</b> {{ __('This appears for new software from small publishers. Click More info → Run anyway. The program is safe and installs only for your Windows user.') }}</div>
        <div class="text-center mt-4"><a href="{{ route('install') }}" class="btn btn-amber btn-lg px-4"><i class="bi bi-windows me-2"></i>{{ __('Install for Windows') }}</a></div>
    </div>
</section>

<section id="pricing">
    <div class="container">
        <div class="text-center mb-5">
            <div class="eyebrow">{{ __('Pricing') }}</div>
            <h2 class="fw-bold mt-2">{{ __('Try first, pay later') }}</h2>
            <p class="text-muted">{{ __('Start with the free trial. Buy a license online when you are happy with it.') }}</p>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-md-6 col-lg-4"><div class="plan">
                <h3 class="h5">{{ __('Free Trial') }}</h3>
                <div class="price">{{ __(':days days', ['days' => $trialDays]) }} <small class="fs-6 text-muted fw-normal">{{ __('free') }}</small></div>
                <ul><li><i class="bi bi-check-circle-fill"></i>{{ __('All features unlocked') }}</li><li><i class="bi bi-check-circle-fill"></i>{{ __('No payment or card needed') }}</li><li><i class="bi bi-check-circle-fill"></i>{{ __('Keep your data when you buy') }}</li></ul>
                <a href="{{ route('install') }}" class="btn btn-navy">{{ __('Download & start trial') }}</a>
            </div></div>
            @forelse($plans as $plan => $price)
                <div class="col-md-6 col-lg-4"><div class="plan {{ $loop->first ? 'best' : '' }}">
                    <h3 class="h5">{{ __(\App\Services\LicenseIssuer::PLANS[$plan]['label']) }}</h3>
                    <div class="price">₹{{ number_format($price) }} <small class="fs-6 text-muted fw-normal">{{ $plan === 'life' ? __('one time') : __('per computer') }}</small></div>
                    <ul><li><i class="bi bi-check-circle-fill"></i>{{ __('All 10 modules') }}</li><li><i class="bi bi-check-circle-fill"></i>{{ __('Free updates') }}</li><li><i class="bi bi-check-circle-fill"></i>{{ __('Pay online with UPI, card or net banking') }}</li><li><i class="bi bi-check-circle-fill"></i>{{ __('License key instantly in your account') }}</li></ul>
                    <a href="{{ auth()->check() ? route('account').'#buy' : route('register') }}" class="btn btn-amber">{{ __('Buy online') }}</a>
                </div></div>
            @empty
                <div class="col-md-6 col-lg-4"><div class="plan best">
                    <h3 class="h5">{{ __('School License') }}</h3>
                    <div class="price fs-4">{{ __('Contact us for price') }}</div>
                    <ul><li><i class="bi bi-check-circle-fill"></i>{{ __('All 10 modules') }}</li><li><i class="bi bi-check-circle-fill"></i>{{ __('Free updates') }}</li><li><i class="bi bi-check-circle-fill"></i>{{ __('Help on phone and WhatsApp') }}</li></ul>
                    @if($wa)<a href="https://wa.me/{{ $wa }}" target="_blank" rel="noopener" class="btn btn-amber">{{ __('Ask on WhatsApp') }}</a>@endif
                </div></div>
            @endforelse
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
            [__('Where is my school\'s data saved?'), __('Only on your laptop, in the folder AppData\Roaming\TDS School Data. We cannot see your data, and nothing is uploaded anywhere. Your website account only stores your contact details and license.')],
            [__('Why do I need an account to download?'), __('Your account keeps your license keys and payment receipts safe, and lets us help you quickly. Your school\'s student data is never stored in it.')],
            [__('How do I back up my data?'), __('Copy the TDS School Data folder to a pen drive or Google Drive once a week. To restore it on a new laptop, install the software, then put the folder back in the same place before opening it.')],
            [__('What happens when the trial or license ends?'), __('Your data is never deleted or locked. The software becomes read-only: you can still view students, print receipts and download reports. To add new admissions or payments, activate a license.')],
            [__('How do I buy a license?'), __('In the software, open License and click Buy online (or copy the Installation ID). Log in here, choose a plan and pay with UPI, card or net banking. The key appears in your account immediately: paste it in the software and click Activate.')],
            [__('How do I update to a new version?'), __('Download the latest setup from your account and install it over the old one. All your data, settings and license stay as they are.')],
            [__('Can I use it on more than one computer?'), __('Each license is for one computer. The data of each installation is separate.')],
        ] as [$q, $a])
            <details><summary>{{ $q }}</summary><p>{{ $a }}</p></details>
        @endforeach
    </div>
</section>

<section class="text-center">
    <div class="container">
        <h2 class="fw-bold">{{ __('Start your free trial today') }}</h2>
        <p class="text-muted">{{ __('Join schools that manage admissions and fees the easy way.') }}</p>
        <div class="d-flex flex-wrap justify-content-center gap-3 mt-3">
            <a href="{{ route('install') }}" class="btn btn-amber btn-lg px-4"><i class="bi bi-windows me-2"></i>{{ __('Install for Windows') }}</a>
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
