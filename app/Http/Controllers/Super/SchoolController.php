<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Student;
use App\Support\Tenant;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    public function index(Request $request)
    {
        $now = now();
        $schools = School::query()
            ->withCount('users')
            ->with(['users' => fn ($q) => $q->where('role', 'admin')->orderBy('id')])
            ->when($request->q, fn ($q, $t) => $q->where(fn ($q) => $q->where('name', 'like', "%$t%")->orWhere('phone', 'like', "%$t%")
                ->orWhere('email', 'like', "%$t%")->orWhere('city', 'like', "%$t%")))
            ->when($request->status === 'locked', fn ($q) => $q->whereNull('paid_until'))
            ->when($request->status === 'active', fn ($q) => $q->where('paid_until', '>', $now))
            ->when($request->status === 'expired', fn ($q) => $q->where('paid_until', '<=', $now))
            ->latest()->paginate(40)->withQueryString();

        if ($request->query('export') === 'csv') {
            return response()->streamDownload(function () {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Joined', 'School', 'Phone', 'Email', 'City', 'State', 'Status', 'Paid until']);
                School::orderBy('id')->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $s) {
                        fputcsv($out, [$s->created_at->format('Y-m-d'), $s->name, $s->phone, $s->email, $s->city, $s->state, $s->accessState(), $s->paid_until?->format('Y-m-d')]);
                    }
                });
            }, 'tds-schools-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
        }

        return view('super.schools', compact('schools'));
    }

    public function show(School $school)
    {
        $school->load(['users', 'subscriptions']);
        $students = Tenant::run($school, fn () => Student::where('status', 'active')->count());

        return view('super.school', compact('school', 'students'));
    }

    /** Manual access, e.g. a school that paid by UPI / cash, or a goodwill extension. */
    public function extend(Request $request, School $school)
    {
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:3650']]);
        $base = $school->paid_until && $school->paid_until->isFuture() ? $school->paid_until->copy() : now();
        $school->forceFill(['trial_used' => true])->extendUntil($base->addDays((int) $data['days']));

        return back()->with('success', "Access extended to {$school->paid_until->format('d M Y')}.");
    }

    public function toggleBlock(School $school)
    {
        $school->forceFill(['is_blocked' => ! $school->is_blocked])->save();

        return back()->with('success', $school->is_blocked ? 'School blocked.' : 'School unblocked.');
    }
}
