{{-- Collect fee: class boxes → section boxes → students with dues. Needs $browse. --}}
@php($b = $browse)
@push('styles')
<style>
    .fbox { display:block; border:1px solid #E3E6EA; border-radius:14px; padding:14px; background:#fff; color:#17191C; text-decoration:none; height:100%; transition:.15s; }
    .fbox:hover { border-color:#FF4B32; box-shadow:0 4px 14px rgb(255 75 50 / .12); color:#17191C; }
    .fbox .nm { font-family:'Bricolage Grotesque',sans-serif; font-weight:700; font-size:1.15rem; }
    .fbox .due { color:#E23A22; font-weight:600; }
    .fbox.clear .due { color:#198754; }
    .fbox.active { border-color:#FF4B32; background:#FFEEEB; }
</style>
@endpush
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
    <nav class="small">
        <a href="{{ route('payments.create') }}">All classes</a>
        @if($b['class'])
            <i class="bi bi-chevron-right mx-1"></i><a href="{{ route('payments.create', ['class_id' => $b['class']->id]) }}">{{ $b['class']->name }}</a>
            @if($b['section'] !== null && $b['class']->sections->isNotEmpty())<i class="bi bi-chevron-right mx-1"></i>{{ $b['section'] === 'none' ? 'No section' : ($b['class']->sections->firstWhere('id', (int) $b['section'])?->name ? 'Section '.$b['class']->sections->firstWhere('id', (int) $b['section'])->name : '') }}@endif
        @endif
    </nav>
    <div class="ms-auto small text-muted">School total pending: <strong class="text-danger">{{ inr($b['total']['due']) }}</strong> · overdue {{ inr($b['total']['overdue']) }}</div>
    <a href="{{ route('fees.pending') }}" class="btn btn-sm btn-outline-danger"><i class="bi bi-list-ul"></i> View all pending / overdue</a>
</div>

@if(! $b['class'])
    @if($b['awaitingCount'])
        <div class="card mb-3 border-warning">
            <div class="card-header bg-warning-subtle d-flex"><span><i class="bi bi-hourglass-split"></i> Awaiting admission fee ({{ $b['awaitingCount'] }})</span>
                <a href="{{ route('admissions.index', ['status' => 'pending']) }}" class="ms-auto small">See all</a></div>
            <div class="list-group list-group-flush">
                @foreach($b['awaiting'] as $st)
                    <a href="{{ route('payments.create', ['student_id' => $st->id]) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                        @include('students.avatar', ['s' => $st])
                        <div><strong>{{ $st->full_name }}</strong> <span class="small text-muted">· {{ $st->admission_no }} · {{ $st->class_label }} · {{ $st->admission_type === 'online' ? 'Online form' : 'Office' }}</span></div>
                        <span class="ms-auto fw-semibold">{{ inr($st->due_total) }}</span><span class="btn btn-sm btn-success">Collect</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
    <div class="row g-3">
        @foreach($b['classes'] as $c)
            <div class="col-6 col-md-4 col-xl-3">
                <a href="{{ route('payments.create', ['class_id' => $c['id']]) }}" class="fbox {{ $c['due'] < 0.01 ? 'clear' : '' }}">
                    <div class="nm">{{ $c['name'] }}</div>
                    <div class="small text-muted">{{ $c['students'] }} {{ \Illuminate\Support\Str::plural('student', $c['students']) }} · {{ $c['pending'] }} with dues</div>
                    <div class="due mt-1">{{ $c['due'] < 0.01 ? 'All clear' : inr($c['due']) }}</div>
                    @if($c['overdue'] > 0.01)<div class="small text-danger">Overdue {{ inr($c['overdue']) }}</div>@endif
                </a>
            </div>
        @endforeach
    </div>
@else
    @if($b['class']->sections->isNotEmpty())
        <div class="row g-3 mb-3">
            @foreach($b['sections'] as $sec)
                <div class="col-6 col-md-3 col-xl-2">
                    <a href="{{ route('payments.create', ['class_id' => $b['class']->id, 'section_id' => $sec['id']]) }}" class="fbox {{ $sec['due'] < 0.01 ? 'clear' : '' }} {{ (string) $b['section'] === (string) $sec['id'] ? 'active' : '' }}">
                        <div class="nm">{{ $sec['id'] === 'none' ? $sec['name'] : 'Section '.$sec['name'] }}</div>
                        <div class="small text-muted">{{ $sec['students'] }} {{ \Illuminate\Support\Str::plural('student', $sec['students']) }} · {{ $sec['pending'] }} with dues</div>
                        <div class="due mt-1">{{ $sec['due'] < 0.01 ? 'All clear' : inr($sec['due']) }}</div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif
    @if($b['list'] !== null)
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <span>{{ request()->boolean('everyone') ? 'All students' : 'Students with pending fees' }}</span>
                <a href="{{ request()->fullUrlWithQuery(['everyone' => request()->boolean('everyone') ? null : 1]) }}" class="ms-auto small">{{ request()->boolean('everyone') ? 'Show only with dues' : 'Show all students' }}</a>
            </div>
            <div class="table-responsive"><table class="table table-hover mb-0 align-middle">
                <thead><tr><th></th><th>Student</th><th>Roll</th><th>Father / Mobile</th><th class="text-end">Pending</th><th class="text-end">Overdue</th><th></th></tr></thead>
                <tbody>
                @forelse($b['list'] as $st)
                    <tr>
                        <td style="width:46px">@include('students.avatar', ['s' => $st])</td>
                        <td><strong>{{ $st->full_name }}</strong>@if($st->status === 'pending') <span class="badge text-bg-warning">Admission pending</span>@endif<div class="small text-muted">{{ $st->student_code ?: $st->admission_no }}</div></td>
                        <td>{{ $st->roll_no }}</td>
                        <td>{{ $st->father_name }}<div class="small text-muted">{{ $st->parent_phone }}</div></td>
                        <td class="text-end fw-semibold">{{ inr($st->due_total) }}</td>
                        <td class="text-end {{ $st->overdue_total > 0 ? 'text-danger' : 'text-muted' }}">{{ $st->overdue_total > 0 ? inr($st->overdue_total) : '—' }}</td>
                        <td class="text-end text-nowrap"><a href="{{ route('payments.create', ['student_id' => $st->id]) }}" class="btn btn-sm btn-primary"><i class="bi bi-cash-coin"></i> Collect</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No pending fees here. 🎉</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    @elseif($b['class']->sections->isNotEmpty())
        <p class="text-muted">Choose a section.</p>
    @endif
@endif
