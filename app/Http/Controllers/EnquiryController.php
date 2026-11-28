<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Reception: walk-in / phone enquiries, follow-ups and conversion to admission. */
class EnquiryController extends Controller
{
    public function index(Request $request)
    {
        $view = $request->input('view', 'open');
        $today = now()->toDateString();

        $enquiries = Enquiry::with(['schoolClass', 'creator'])
            ->when($view === 'open', fn ($q) => $q->whereIn('status', ['new', 'follow_up']))
            ->when($view === 'due', fn ($q) => $q->whereIn('status', ['new', 'follow_up'])->whereDate('next_follow_up', '<=', $today))
            ->when($view === 'today', fn ($q) => $q->whereDate('created_at', $today))
            ->when($view === 'converted', fn ($q) => $q->where('status', 'converted'))
            ->when($view === 'closed', fn ($q) => $q->where('status', 'not_interested'))
            ->when($request->class_id, fn ($q, $id) => $q->where('school_class_id', $id))
            ->when($request->q, fn ($q, $t) => $q->where(fn ($q) => $q->where('child_name', 'like', "%$t%")->orWhere('parent_name', 'like', "%$t%")->orWhere('phone', 'like', "%$t%")))
            ->orderByRaw('CASE WHEN next_follow_up IS NULL THEN 1 ELSE 0 END')->orderBy('next_follow_up')->latest('id')
            ->paginate(30)->withQueryString();

        $counts = [
            'open' => Enquiry::whereIn('status', ['new', 'follow_up'])->count(),
            'due' => Enquiry::whereIn('status', ['new', 'follow_up'])->whereDate('next_follow_up', '<=', $today)->count(),
            'today' => Enquiry::whereDate('created_at', $today)->count(),
            'converted' => Enquiry::where('status', 'converted')->count(),
        ];

        return view('enquiries.index', ['enquiries' => $enquiries, 'view' => $view, 'counts' => $counts, 'classes' => SchoolClass::ordered()->get()]);
    }

    public function create()
    {
        return view('enquiries.form', ['enquiry' => new Enquiry(['source' => 'walk_in', 'next_follow_up' => now()->addDays(2)]), 'classes' => SchoolClass::ordered()->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $enquiry = Enquiry::create($data + ['created_by' => $request->user()->id, 'status' => ! empty($data['next_follow_up']) ? 'follow_up' : 'new']);

        return redirect()->route('enquiries.show', $enquiry)->with('success', 'Enquiry saved.');
    }

    public function show(Enquiry $enquiry)
    {
        $enquiry->load(['schoolClass', 'creator', 'followUps.user', 'student']);

        return view('enquiries.show', ['enquiry' => $enquiry, 'classes' => SchoolClass::ordered()->get()]);
    }

    public function update(Request $request, Enquiry $enquiry)
    {
        $data = $this->validated($request) + $request->validate(['status' => ['required', Rule::in(array_keys(Enquiry::STATUSES))]]);
        $enquiry->update($data);

        return back()->with('success', 'Enquiry updated.');
    }

    public function followUp(Request $request, Enquiry $enquiry)
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:1000'],
            'next_follow_up' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['follow_up', 'not_interested', 'new'])],
        ]);
        $enquiry->followUps()->create(['note' => $data['note'], 'next_follow_up' => $data['next_follow_up'] ?? null, 'user_id' => $request->user()->id]);
        $enquiry->update(['status' => $data['status'], 'next_follow_up' => $data['status'] === 'not_interested' ? null : ($data['next_follow_up'] ?? null)]);

        return back()->with('success', 'Follow-up noted.');
    }

    /** Reception's day: enquiries, follow-ups, admissions. */
    public function report(Request $request)
    {
        $date = $request->date('date') ?? now();
        $mine = $request->boolean('mine');
        $uid = $request->user()->id;

        $enquiries = Enquiry::with(['schoolClass', 'creator'])->whereDate('created_at', $date)
            ->when($mine, fn ($q) => $q->where('created_by', $uid))->latest('id')->get();
        $followUps = \App\Models\EnquiryFollowUp::with(['user'])->whereDate('created_at', $date)
            ->when($mine, fn ($q) => $q->where('user_id', $uid))->latest('id')->get();
        $followEnquiries = Enquiry::whereIn('id', $followUps->pluck('enquiry_id'))->get()->keyBy('id');
        $admissions = Student::with(['schoolClass', 'section'])->whereDate('created_at', $date)->latest('id')->get();
        $due = Enquiry::whereIn('status', ['new', 'follow_up'])->whereDate('next_follow_up', '<=', $date)->count();

        return view('enquiries.report', compact('date', 'mine', 'enquiries', 'followUps', 'followEnquiries', 'admissions', 'due'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'child_name' => ['required', 'string', 'max:100'],
            'parent_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'regex:/^[0-9+\-\s]{10,15}$/'],
            'alt_phone' => ['nullable', 'regex:/^[0-9+\-\s]{10,15}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'school_class_id' => ['nullable', Tenant::exists('school_classes')],
            'dob' => ['nullable', 'date', 'before:today'],
            'previous_school' => ['nullable', 'string', 'max:150'],
            'source' => ['required', Rule::in(array_keys(Enquiry::SOURCES))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'next_follow_up' => ['nullable', 'date'],
        ], ['phone.regex' => 'Enter a valid mobile number.']);
    }
}
