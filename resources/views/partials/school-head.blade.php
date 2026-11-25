<div class="school-head">
    <h2>{{ school('name') }}</h2>
    @if(school('address'))<div>{{ school('address') }}</div>@endif
    <div class="small text-muted">
        @if(school('phone'))Phone: {{ school('phone') }}@endif
        @if(school('email')) · {{ school('email') }}@endif
    </div>
    @isset($docTitle)<div class="mt-2 fw-semibold text-uppercase" style="letter-spacing:.08em">{{ $docTitle }}</div>@endisset
</div>
