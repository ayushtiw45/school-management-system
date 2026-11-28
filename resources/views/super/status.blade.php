@php($st = $school->accessState())
<span class="badge {{ ['active' => 'text-bg-success', 'locked' => 'text-bg-warning', 'expired' => 'text-bg-secondary', 'blocked' => 'text-bg-danger'][$st] }}">{{ ['active' => 'Active till '.$school->paid_until?->format('d M'), 'locked' => 'Locked', 'expired' => 'Ended', 'blocked' => 'Blocked'][$st] }}</span>
