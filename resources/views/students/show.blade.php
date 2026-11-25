@extends('layouts.app')
@section('title', $student->full_name)
@section('content')
@php
    $s = $student;
    $u = auth()->user();
    $canEdit = $u->hasRole('admin', 'admission_staff');
    $canFees = $u->hasRole('admin', 'accountant');
    $totalFee = $s->fees->sum(fn ($f) => $f->netAmount());
    $totalPaid = $s->fees->sum('paid');
    $balance = $s->fees->sum(fn ($f) => $f->balance());
    $overdue = $s->fees->filter->isOverdue()->sum(fn ($f) => $f->balance());
@endphp

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center">
        @if($s->photo)
            <img src="{{ route('students.photo', $s) }}" style="width:84px;height:84px;object-fit:cover" class="rounded-3" alt="">
        @else
            <div class="avatar rounded-3" style="width:84px;height:84px;font-size:2rem">{{ strtoupper(substr($s->first_name, 0, 1)) }}</div>
        @endif
        <div>
            <h2 class="h5 fw-bold mb-1">{{ $s->full_name }} @include('students.status-badge', ['status' => $s->status])</h2>
            <div class="text-muted small">Adm. No <strong>{{ $s->admission_no }}</strong> · {{ $s->class_label ?: '—' }} @if($s->roll_no)· Roll {{ $s->roll_no }}@endif · Session {{ $s->academicSession->name }}</div>
            <div class="text-muted small">Father: {{ $s->father_name }} · <i class="bi bi-telephone"></i> {{ $s->parent_phone }}</div>
        </div>
        <div class="ms-auto d-flex flex-wrap gap-2">
            @if($canEdit && $s->status === 'pending')
                <form method="POST" action="{{ route('admissions.confirm', $s) }}">@csrf<button class="btn btn-success btn-sm"><i class="bi bi-check2-circle"></i> Confirm Admission</button></form>
            @endif
            @if($canFees && $s->status !== 'pending')
                <a href="{{ route('payments.create', ['student_id' => $s->id]) }}" class="btn btn-primary btn-sm"><i class="bi bi-cash-coin"></i> Collect Fee</a>
            @endif
            @if($canEdit)<a href="{{ route('students.edit', $s) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i> Edit</a>@endif
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">More</button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="{{ route('students.idcard', $s) }}"><i class="bi bi-person-badge me-2"></i>ID Card</a></li>
                    @if($canEdit)
                        <li><a class="dropdown-item" href="{{ route('admissions.print', $s) }}"><i class="bi bi-printer me-2"></i>Admission Form</a></li>
                        @if(in_array($s->status, ['active', 'inactive']))
                            <li><hr class="dropdown-divider"></li>
                            <li><form method="POST" action="{{ route('students.status', $s) }}">@csrf
                                <input type="hidden" name="status" value="{{ $s->status === 'active' ? 'inactive' : 'active' }}">
                                <button class="dropdown-item"><i class="bi bi-toggle-on me-2"></i>Mark {{ $s->status === 'active' ? 'Inactive' : 'Active' }}</button>
                            </form></li>
                            <li><a class="dropdown-item text-danger" href="{{ route('students.leave', $s) }}"><i class="bi bi-box-arrow-right me-2"></i>Transfer / Leaving</a></li>
                        @elseif($s->status === 'left')
                            <li><form method="POST" action="{{ route('students.status', $s) }}">@csrf<input type="hidden" name="status" value="active"><button class="dropdown-item"><i class="bi bi-arrow-counterclockwise me-2"></i>Re-activate</button></form></li>
                        @endif
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    @foreach([['Total Fee', $totalFee, 'primary'], ['Paid', $totalPaid, 'success'], ['Balance', $balance, 'warning'], ['Overdue', $overdue, 'danger']] as [$l, $amt, $c])
        <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2">
            <div class="text-muted small">{{ $l }}</div><div class="fw-bold fs-5 text-{{ $c }}">{{ inr($amt) }}</div>
        </div></div></div>
    @endforeach
</div>

<ul class="nav nav-tabs" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tFees">Fees</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tPayments">Payments ({{ $s->payments->count() }})</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tProfile">Profile</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tDocs">Documents ({{ $s->documents->count() }})</button></li>
</ul>
<div class="tab-content card border-top-0 rounded-top-0">
    {{-- FEES --}}
    <div class="tab-pane fade show active" id="tFees">
        @if($canFees && $s->status === 'active')
            <div class="p-3 border-bottom d-flex flex-wrap gap-2">
                <form method="POST" action="{{ route('students.fees.generate', $s) }}" class="d-flex gap-2">
                    @csrf
                    <select name="session_id" class="form-select form-select-sm" style="width:auto">
                        @foreach($sessions as $ses)<option value="{{ $ses->id }}" @selected($ses->id === $s->academic_session_id)>{{ $ses->name }}</option>@endforeach
                    </select>
                    <button class="btn btn-sm btn-outline-primary text-nowrap"><i class="bi bi-arrow-repeat"></i> Assign from Fee Structure</button>
                </form>
                <button class="btn btn-sm btn-outline-secondary ms-auto" data-bs-toggle="collapse" data-bs-target="#addFee"><i class="bi bi-plus"></i> Add Other Fee</button>
            </div>
            <div class="collapse border-bottom" id="addFee">
                <form method="POST" action="{{ route('students.fees.store', $s) }}" class="row g-2 p-3">
                    @csrf
                    <div class="col-md-3"><select name="fee_head_id" class="form-select form-select-sm"><option value="">Category (optional)</option>@foreach($feeHeads as $h)<option value="{{ $h->id }}">{{ $h->name }}</option>@endforeach</select></div>
                    <div class="col-md-3"><input name="title" class="form-control form-control-sm" placeholder="Title, e.g. Picnic Fee" required></div>
                    <div class="col-md-2"><input type="number" step="0.01" name="amount" class="form-control form-control-sm" placeholder="Amount" required></div>
                    <div class="col-md-1"><input type="number" step="0.01" name="discount" class="form-control form-control-sm" placeholder="Disc."></div>
                    <div class="col-md-2"><input type="date" name="due_date" value="{{ now()->format('Y-m-d') }}" class="form-control form-control-sm" required></div>
                    <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">Add</button></div>
                </form>
            </div>
        @endif
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead><tr><th>Fee</th><th>Due Date</th><th class="text-end">Amount</th><th class="text-end">Discount</th><th class="text-end">Fine</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th>Status</th>@if($canFees)<th></th>@endif</tr></thead>
                <tbody>
                @forelse($s->fees->groupBy(fn ($f) => $f->academicSession?->name ?? '') as $sessionName => $rows)
                    <tr class="table-light"><td colspan="9" class="fw-semibold small">Session {{ $sessionName }}</td></tr>
                    @foreach($rows as $f)
                        <tr class="{{ $f->isOverdue() ? 'table-danger-subtle' : '' }}">
                            <td>{{ $f->title }}</td>
                            <td>{{ $f->due_date->format('d M Y') }} @if($f->isOverdue())<span class="badge text-bg-danger">Overdue</span>@endif</td>
                            <td class="text-end">{{ inr($f->amount, false) }}</td>
                            <td class="text-end">{{ (float) $f->discount ? inr($f->discount, false) : '—' }}</td>
                            <td class="text-end">{{ (float) $f->fine ? inr($f->fine, false) : '—' }}</td>
                            <td class="text-end">{{ inr($f->paid, false) }}</td>
                            <td class="text-end fw-semibold">{{ inr($f->balance(), false) }}</td>
                            <td>@include('students.status-badge', ['status' => $f->status])</td>
                            @if($canFees)
                                <td class="text-end text-nowrap">
                                    <button class="btn btn-sm btn-link p-0" data-bs-toggle="modal" data-bs-target="#editFee{{ $f->id }}"><i class="bi bi-pencil"></i></button>
                                    @if((float) $f->paid == 0)
                                        <form method="POST" action="{{ route('student-fees.destroy', $f) }}" class="d-inline" data-confirm="Remove this fee?">@csrf @method('DELETE')<button class="btn btn-sm btn-link p-0 text-danger ms-1"><i class="bi bi-trash"></i></button></form>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No fees assigned yet. @if($s->status === 'pending') Fees are assigned when the admission is confirmed. @endif</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- PAYMENTS --}}
    <div class="tab-pane fade" id="tPayments">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead><tr><th>Receipt No</th><th>Date</th><th>Mode</th><th>Reference</th><th>Received By</th><th class="text-end">Amount</th><th></th></tr></thead>
                <tbody>
                @forelse($s->payments as $p)
                    <tr>
                        <td>{{ $p->receipt_no }}</td>
                        <td>{{ $p->payment_date->format('d M Y') }}</td>
                        <td>{{ $p->methodLabel() }}</td>
                        <td>{{ $p->reference_no }}</td>
                        <td>{{ $p->receiver?->name }}</td>
                        <td class="text-end fw-semibold">{{ inr($p->amount) }}</td>
                        <td class="text-end">@if($canFees)<a href="{{ route('payments.show', $p) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer"></i> Receipt</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No payments yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- PROFILE --}}
    <div class="tab-pane fade" id="tProfile">
        <div class="row g-0">
            @php($groups = [
                'Personal' => ['Date of Birth' => $s->dob?->format('d M Y'), 'Gender' => ucfirst($s->gender), 'Blood Group' => $s->blood_group, 'Religion' => $s->religion, 'Category' => $s->category, 'Address' => collect([$s->address, $s->city, $s->state, $s->pincode])->filter()->join(', ')],
                'Parents' => ['Father' => $s->father_name, 'Father Mobile' => $s->father_phone, 'Occupation' => $s->father_occupation, 'Mother' => $s->mother_name, 'Mother Mobile' => $s->mother_phone, 'Guardian' => $s->guardian_name ? $s->guardian_name.' ('.$s->guardian_relation.') '.$s->guardian_phone : null, 'Email' => $s->email],
                'Admission' => ['Admission Date' => $s->admission_date->format('d M Y'), 'Admission Type' => ucfirst($s->admission_type), 'Confirmed On' => $s->confirmed_at?->format('d M Y'), 'Fee Concession' => (float) $s->concession_percent ? $s->concession_percent.'%' : null, 'Previous School' => $s->previous_school, 'Previous Class' => $s->previous_class, 'TC Number' => $s->tc_number],
            ])
            @if($s->status === 'left')
                @php($groups['Leaving'] = ['Leaving Date' => $s->leaving_date?->format('d M Y'), 'Reason' => $s->leaving_reason])
            @endif
            @foreach($groups as $title => $rows)
                <div class="col-md-4 p-3 border-end">
                    <h6 class="fw-semibold text-uppercase small text-muted">{{ $title }}</h6>
                    <dl class="row mb-0 small">
                        @foreach($rows as $k => $val)<dt class="col-5 fw-normal text-muted">{{ $k }}</dt><dd class="col-7">{{ $val ?: '—' }}</dd>@endforeach
                    </dl>
                </div>
            @endforeach
            @if($s->remarks)<div class="col-12 p-3 border-top small"><strong>Remarks:</strong> {{ $s->remarks }}</div>@endif
        </div>
    </div>

    {{-- DOCUMENTS --}}
    <div class="tab-pane fade" id="tDocs">
        @if($canEdit)
            <form method="POST" action="{{ route('students.documents.store', $s) }}" enctype="multipart/form-data" class="row g-2 p-3 border-bottom">
                @csrf
                <div class="col-md-4"><input name="title" class="form-control form-control-sm" placeholder="Document name" required></div>
                <div class="col-md-6"><input type="file" name="file" class="form-control form-control-sm" accept=".pdf,image/*" required></div>
                <div class="col-md-2"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-upload"></i> Upload</button></div>
            </form>
        @endif
        <ul class="list-group list-group-flush">
            @forelse($s->documents as $d)
                <li class="list-group-item d-flex align-items-center">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    <a href="{{ route('students.documents.show', [$s, $d]) }}" target="_blank">{{ $d->title }}</a>
                    <span class="text-muted small ms-2">{{ $d->created_at->format('d M Y') }}</span>
                    @if($canEdit)
                        <form method="POST" action="{{ route('students.documents.destroy', [$s, $d]) }}" class="ms-auto" data-confirm="Delete this document?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger"><i class="bi bi-trash"></i></button></form>
                    @endif
                </li>
            @empty
                <li class="list-group-item text-muted text-center py-4">No documents uploaded.</li>
            @endforelse
        </ul>
    </div>
</div>

@if($canFees)
    @foreach($s->fees as $f)
        <div class="modal fade" id="editFee{{ $f->id }}" tabindex="-1">
            <div class="modal-dialog"><form method="POST" action="{{ route('student-fees.update', $f) }}" class="modal-content">
                @csrf @method('PUT')
                <div class="modal-header"><h5 class="modal-title">Edit Fee</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body row g-2">
                    <div class="col-12"><label class="form-label">Title</label><input name="title" value="{{ $f->title }}" class="form-control" required></div>
                    <div class="col-6"><label class="form-label">Amount</label><input type="number" step="0.01" name="amount" value="{{ $f->amount }}" class="form-control" required></div>
                    <div class="col-6"><label class="form-label">Due Date</label><input type="date" name="due_date" value="{{ $f->due_date->format('Y-m-d') }}" class="form-control" required></div>
                    <div class="col-6"><label class="form-label">Discount / Concession</label><input type="number" step="0.01" min="0" name="discount" value="{{ $f->discount }}" class="form-control" required></div>
                    <div class="col-6"><label class="form-label">Late Fine</label><input type="number" step="0.01" min="0" name="fine" value="{{ $f->fine }}" class="form-control" required></div>
                    <div class="col-12 small text-muted">Already paid: {{ inr($f->paid) }}</div>
                </div>
                <div class="modal-footer"><button class="btn btn-primary">Save</button></div>
            </form></div>
        </div>
    @endforeach
@endif
@endsection
