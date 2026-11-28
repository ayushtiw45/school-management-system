{{-- WhatsApp / social preview in the school's name + small credit. Needs $school. --}}
@push('head')
    @php($desc = 'Online admission form — '.$school->displayName().(school('address') ? ', '.school('address') : ''))
    <meta name="description" content="{{ $desc }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $school->displayName() }}">
    <meta property="og:title" content="Admission Form · {{ $school->displayName() }}">
    <meta property="og:description" content="{{ $desc }}">
    <meta property="og:url" content="{{ route('apply', $school) }}">
    @if($school->logoUrl())
        <meta property="og:image" content="{{ $school->logoUrl() }}">
        <link rel="icon" href="{{ $school->logoUrl() }}">
    @endif
@endpush
@push('footer')
    <div class="text-center pb-4" style="font-size:.72rem;color:#8a929c">Powered by <a href="https://thedigitalstrokes.in" target="_blank" rel="noopener" style="color:#b9c0c8">The Digital Strokes</a></div>
@endpush
