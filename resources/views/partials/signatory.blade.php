@php($signImg = school_image('signature'))
<div class="text-center" style="min-width:190px">
    @if($signImg)<img src="{{ $signImg }}" alt="" style="height:48px;max-width:180px;object-fit:contain">@else<div style="height:48px"></div>@endif
    <div class="border-top pt-1">{{ \App\Models\Setting::get('signatory_name') ?: 'Authorised Signatory' }}</div>
    <div class="small text-muted">{{ \App\Models\Setting::get('signatory_title') ?: 'Accounts' }}</div>
</div>
