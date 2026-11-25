@php($map = ['pending' => 'warning', 'active' => 'success', 'inactive' => 'secondary', 'left' => 'dark', 'paid' => 'success', 'partial' => 'info', 'unpaid' => 'danger'])
<span class="badge text-bg-{{ $map[$status] ?? 'light' }}">{{ \App\Models\Student::STATUSES[$status] ?? ucfirst($status) }}</span>
