@php($v = fn ($f) => old($f, $coupon->$f instanceof \Carbon\Carbon ? $coupon->$f->format('Y-m-d') : $coupon->$f))
<div class="row g-2">
    <div class="col-md-6"><label class="form-label">Coupon code *</label><input name="code" value="{{ $v('code') }}" class="form-control text-uppercase" placeholder="DIWALI50" required></div>
    <div class="col-md-6"><label class="form-label">Note (only you see it)</label><input name="description" value="{{ $v('description') }}" class="form-control" placeholder="Diwali offer 2026"></div>
    <div class="col-md-6"><label class="form-label">Discount</label><select name="discount_type" class="form-select" id="cType">@foreach(\App\Models\Coupon::TYPES as $k => $l)<option value="{{ $k }}" @selected($v('discount_type') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="col-md-3"><label class="form-label">Value</label><input type="number" step="0.01" min="0" name="discount_value" value="{{ (float) $v('discount_value') ?: '' }}" class="form-control" id="cValue" placeholder="% or ₹"></div>
    <div class="col-md-3"><label class="form-label">Extra free days</label><input type="number" min="0" max="365" name="extra_days" value="{{ $v('extra_days') ?: '' }}" class="form-control" placeholder="0"></div>
    <div class="col-12 small text-muted" id="cPreview"></div>
    <div class="col-md-4"><label class="form-label">Valid from</label><input type="date" name="valid_from" value="{{ $v('valid_from') }}" class="form-control"></div>
    <div class="col-md-4"><label class="form-label">Valid till</label><input type="date" name="valid_until" value="{{ $v('valid_until') }}" class="form-control"></div>
    <div class="col-md-4"><label class="form-label">Max uses</label><input type="number" min="1" name="max_uses" value="{{ $v('max_uses') }}" class="form-control" placeholder="No limit"></div>
    <div class="col-md-8"><label class="form-label">Only for one school</label><select name="school_id" class="form-select"><option value="">Any school</option>@foreach($schools as $s)<option value="{{ $s->id }}" @selected($v('school_id') == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
    <div class="col-md-4 d-flex align-items-end"><div class="form-check"><input type="checkbox" class="form-check-input" name="new_schools_only" value="1" id="cNew" @checked($v('new_schools_only'))><label for="cNew" class="form-check-label">New schools only</label></div></div>
</div>
@push('scripts')
<script>
(function () {
    const t = document.getElementById('cType'), val = document.getElementById('cValue'), out = document.getElementById('cPreview'), m = {{ $monthly / 100 }};
    function show() {
        val.disabled = t.value === 'none';
        const x = parseFloat(val.value || 0); let p = m;
        if (t.value === 'percent') p = Math.floor(m - m * Math.min(100, x) / 100); else if (t.value === 'flat') p = Math.floor(m - x);
        out.textContent = t.value === 'none' ? 'Price stays ₹' + m + ' per month.' : 'Monthly price with this coupon: ₹' + Math.max(1, p).toLocaleString('en-IN') + ' instead of ₹' + m.toLocaleString('en-IN') + ' — for as long as Autopay runs.';
    }
    t.addEventListener('change', show); val.addEventListener('input', show); show();
})();
</script>
@endpush
