@php($logoImg = school_image('logo'))
<div class="school-head">
    <div class="d-flex align-items-center justify-content-center gap-3">
        @if($logoImg)<img src="{{ $logoImg }}" alt="" style="height:64px;max-width:90px;object-fit:contain">@endif
        <div>
            <h2>{{ school('name') }}</h2>
            @if(school('legal_name'))<div class="small">{{ school('legal_name') }}</div>@endif
            @if(school('address'))<div>{{ school('address') }}@if(school('state')), {{ school('state') }}@endif</div>@endif
            <div class="small text-muted">
                @if(school('phone'))Phone: {{ school('phone') }}@endif
                @if(school('email')) · {{ school('email') }}@endif
                @if(school('website')) · {{ school('website') }}@endif
            </div>
        </div>
    </div>
    @isset($docTitle)<div class="mt-2 fw-semibold text-uppercase" style="letter-spacing:.08em">{{ $docTitle }}</div>@endisset
</div>
