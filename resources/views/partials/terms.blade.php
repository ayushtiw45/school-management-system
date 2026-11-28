@php($lines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) \App\Models\Setting::get($key))))))
@if($lines)
    <div class="small mt-3"><strong>Terms & conditions</strong>
        <ol class="mb-0 ps-3">@foreach($lines as $l)<li>{{ $l }}</li>@endforeach</ol>
    </div>
@endif
