@foreach (['success' => 'success', 'error' => 'danger'] as $key => $type)
    @if(session($key))<div class="alert alert-{{ $type }}">{{ session($key) }}</div>@endif
@endforeach
@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
