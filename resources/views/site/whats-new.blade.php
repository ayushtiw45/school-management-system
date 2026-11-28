@extends('layouts.site')
@section('title', __("What's new"))
@section('description', __('Latest updates to TDS School Management Software: new features, improvements and fixes in every version.'))
@php($C = \App\Support\Changelog::class)
@php($badge = ['new' => ['#FF4B32', __('New')], 'improved' => ['#2B6CB0', __('Improved')], 'fixed' => ['#198754', __('Fixed')]])
@section('content')
<div class="container py-5" style="max-width:860px">
    <div class="eyebrow mb-2">{{ __('Changelog') }}</div>
    <h1 class="fw-bold mb-2">{{ __("What's new") }}</h1>
    <p class="text-muted mb-4">{{ __('Every update reaches all schools automatically — no download, no reinstall. Here is what changed in each version.') }}</p>

    @foreach($C::releases() as $i => $r)
        <div class="card p-4 mb-3 {{ $i === 0 ? 'border-2' : '' }}" id="v{{ $r['version'] }}" @if($i === 0) style="border-color:#FF4B32" @endif>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <span class="badge rounded-pill" style="background:#17191C">v{{ $r['version'] }}</span>
                @if($i === 0)<span class="badge rounded-pill" style="background:#FF4B32">{{ __('Latest') }}</span>@endif
                <span class="text-muted small">{{ \Illuminate\Support\Carbon::parse($r['date'])->translatedFormat('d M Y') }}</span>
            </div>
            <h2 class="h5 fw-bold mb-3">{{ $C::text($r['title']) }}</h2>
            <ul class="list-unstyled mb-0">
                @foreach($r['changes'] as $c)
                    @php([$color, $label] = $badge[$c['type']] ?? $badge['new'])
                    <li class="d-flex gap-2 mb-2">
                        <span class="badge align-self-start mt-1" style="background:{{ $color }};min-width:76px">{{ $label }}</span>
                        <span>{{ $C::text($c) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</div>
@endsection
