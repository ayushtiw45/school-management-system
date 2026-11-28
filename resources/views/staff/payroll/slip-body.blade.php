@php($E = \App\Models\Hr\Employee::class)
@php($e = $p->employee)
<div class="sheet" style="max-width:760px;page-break-after:always">
    @include('partials.school-head', ['docTitle' => 'Salary Slip · '.$p->monthLabel()])
    <table class="table table-sm table-bordered small mb-3">
        <tr><th>Name</th><td>{{ $e->name }}</td><th>Employee ID</th><td>{{ $e->employee_id }}</td></tr>
        <tr><th>Designation</th><td>{{ $e->designation }}</td><th>Department</th><td>{{ $e->department }}</td></tr>
        <tr><th>Bank a/c</th><td>{{ $e->bank_account }} {{ $e->bank_ifsc }}</td><th>PAN / UAN</th><td>{{ $e->pan_no }} {{ $e->uan_no ? '/ '.$e->uan_no : '' }}</td></tr>
        <tr><th>Working days</th><td>{{ $p->working_days + 0 }}</td><th>Paid days</th><td>{{ $p->paid_days + 0 }}{{ (float) $p->lop_days ? ' (LOP '.($p->lop_days + 0).')' : '' }}</td></tr>
    </table>
    <div class="row g-0">
        <div class="col-6"><table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th>Earnings</th><th class="text-end">₹</th></tr></thead><tbody>
            @foreach($E::EARNINGS as $k => $l)@if((float) $p->$k)<tr><td>{{ $l }}</td><td class="text-end">{{ inr($p->$k, false) }}</td></tr>@endif @endforeach
            @if((float) $p->bonus)<tr><td>Bonus / arrears</td><td class="text-end">{{ inr($p->bonus, false) }}</td></tr>@endif
            <tr class="fw-bold"><td>Gross</td><td class="text-end">{{ inr($p->gross, false) }}</td></tr></tbody></table></div>
        <div class="col-6"><table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th>Deductions</th><th class="text-end">₹</th></tr></thead><tbody>
            @foreach($E::DEDUCTIONS as $k => $l)@if((float) $p->$k)<tr><td>{{ $l }}</td><td class="text-end">{{ inr($p->$k, false) }}</td></tr>@endif @endforeach
            @if((float) $p->lop_deduction)<tr><td>Loss of pay</td><td class="text-end">{{ inr($p->lop_deduction, false) }}</td></tr>@endif
            @if((float) $p->advance_deduction)<tr><td>Advance recovery</td><td class="text-end">{{ inr($p->advance_deduction, false) }}</td></tr>@endif
            <tr class="fw-bold"><td>Total</td><td class="text-end">{{ inr($p->total_deduction, false) }}</td></tr></tbody></table></div>
    </div>
    <div class="border p-2 mt-3 d-flex"><strong>Net pay</strong><strong class="ms-auto">{{ inr($p->net) }}</strong></div>
    <p class="small mt-1 mb-1"><strong>In words:</strong> {{ amount_in_words($p->net) }}</p>
    <p class="small text-muted">{{ $p->status === 'paid' ? 'Paid on '.$p->paid_on->format('d M Y').' by '.(\App\Models\Payment::METHODS[$p->method] ?? $p->method).($p->reference_no ? ' ('.$p->reference_no.')' : '') : 'Not paid yet' }}{{ $p->note ? ' · '.$p->note : '' }}</p>
    <div class="d-flex justify-content-between align-items-end mt-4"><div class="text-center border-top pt-1" style="width:180px">Employee signature</div>@include('partials.signatory')</div>
</div>
