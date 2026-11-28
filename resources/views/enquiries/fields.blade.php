<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Child's name *</label><input name="child_name" value="{{ old('child_name', $enquiry->child_name) }}" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">Class wanted</label>
        <select name="school_class_id" class="form-select"><option value="">—</option>
            @foreach($classes as $c)<option value="{{ $c->id }}" @selected(old('school_class_id', $enquiry->school_class_id) == $c->id)>{{ $c->name }}</option>@endforeach
        </select></div>
    <div class="col-md-6"><label class="form-label">Parent's name</label><input name="parent_name" value="{{ old('parent_name', $enquiry->parent_name) }}" class="form-control"></div>
    <div class="col-md-3"><label class="form-label">Mobile *</label><input name="phone" value="{{ old('phone', $enquiry->phone) }}" class="form-control" inputmode="tel" required></div>
    <div class="col-md-3"><label class="form-label">Other mobile</label><input name="alt_phone" value="{{ old('alt_phone', $enquiry->alt_phone) }}" class="form-control" inputmode="tel"></div>
    <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" value="{{ old('email', $enquiry->email) }}" class="form-control"></div>
    <div class="col-md-4"><label class="form-label">Child's date of birth</label><input type="date" name="dob" value="{{ old('dob', $enquiry->dob?->format('Y-m-d')) }}" class="form-control"></div>
    <div class="col-md-4"><label class="form-label">Previous school</label><input name="previous_school" value="{{ old('previous_school', $enquiry->previous_school) }}" class="form-control"></div>
    <div class="col-md-4"><label class="form-label">How did they hear about us? *</label>
        <select name="source" class="form-select">@foreach(\App\Models\Enquiry::SOURCES as $k => $l)<option value="{{ $k }}" @selected(old('source', $enquiry->source) === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Next follow-up date</label><input type="date" name="next_follow_up" value="{{ old('next_follow_up', $enquiry->next_follow_up?->format('Y-m-d')) }}" class="form-control"></div>
    @isset($withStatus)
        <div class="col-md-4"><label class="form-label">Status</label>
            <select name="status" class="form-select">@foreach(\App\Models\Enquiry::STATUSES as $k => $l)<option value="{{ $k }}" @selected(old('status', $enquiry->status) === $k)>{{ $l }}</option>@endforeach</select></div>
    @endisset
    <div class="col-12"><label class="form-label">Notes (what they asked, fee discussed, etc.)</label><textarea name="notes" rows="3" class="form-control">{{ old('notes', $enquiry->notes) }}</textarea></div>
</div>
