@php($cls = ['new' => 'text-bg-primary', 'follow_up' => 'text-bg-warning', 'converted' => 'text-bg-success', 'not_interested' => 'text-bg-secondary'][$status] ?? 'text-bg-light')
<span class="badge {{ $cls }}">{{ \App\Models\Enquiry::STATUSES[$status] ?? $status }}</span>
