{{-- Coloured expiry date. Var: $date (Carbon|null) --}}
@if(! $date)<span class="text-muted">—</span>
@elseif($date->isPast())<span class="badge text-bg-danger">Expired {{ $date->format('d M Y') }}</span>
@elseif($date->lte(now()->addDays(30)))<span class="badge text-bg-warning">{{ $date->format('d M Y') }} · {{ (int) now()->startOfDay()->diffInDays($date) }} days</span>
@else<span>{{ $date->format('d M Y') }}</span>@endif
