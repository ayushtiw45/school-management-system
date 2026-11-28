<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use App\Models\Transport\RouteChange;
use App\Models\Transport\StaffAttendance;
use App\Models\Transport\StaffLeave;
use App\Models\Transport\TransportRoute;
use App\Models\Transport\TransportStaff;
use App\Support\Tenant;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Daily attendance of drivers & conductors, leave and substitute assignment. */
class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->date('date') ?? now();
        $staff = TransportStaff::with('vehicle')->where('is_active', true)->orderBy('type')->orderBy('name')->get();
        $marks = StaffAttendance::whereDate('date', $date)->get()->keyBy('transport_staff_id');
        $onLeave = StaffLeave::where('status', 'approved')->whereDate('from_date', '<=', $date)->whereDate('to_date', '>=', $date)->pluck('transport_staff_id')->all();

        return view('transport.attendance.index', compact('date', 'staff', 'marks', 'onLeave'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'att' => ['required', 'array'],
            'att.*.status' => ['required', Rule::in(array_keys(StaffAttendance::STATUSES))],
            'att.*.in_time' => ['nullable', 'date_format:H:i'], 'att.*.out_time' => ['nullable', 'date_format:H:i'],
            'att.*.note' => ['nullable', 'string', 'max:200'],
        ]);
        $ids = TransportStaff::whereIn('id', array_keys($data['att']))->pluck('id')->all();
        foreach ($data['att'] as $id => $a) {
            if (in_array((int) $id, $ids, true)) {
                StaffAttendance::updateOrCreate(['transport_staff_id' => $id, 'date' => \Carbon\Carbon::parse($data['date'])->startOfDay()], $a);
            }
        }

        return back()->with('success', 'Attendance saved for '.\Carbon\Carbon::parse($data['date'])->format('d M Y').'.');
    }

    /** Month sheet: P/A/H/L per day. */
    public function monthly(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $start = \Carbon\Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $days = CarbonPeriod::create($start, $start->copy()->endOfMonth())->toArray();
        $staff = TransportStaff::where('is_active', true)->orderBy('type')->orderBy('name')->get();
        $marks = StaffAttendance::whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString().' 23:59:59'])->get()
            ->groupBy('transport_staff_id')->map(fn ($g) => $g->keyBy(fn ($a) => $a->date->format('Y-m-d')));

        return view('transport.attendance.monthly', compact('month', 'start', 'days', 'staff', 'marks'));
    }

    // ---- leave

    public function leaves(Request $request)
    {
        return view('transport.attendance.leaves', [
            'leaves' => StaffLeave::with(['staff', 'substitute'])->latest('from_date')->paginate(30),
            'staff' => TransportStaff::where('is_active', true)->orderBy('type')->orderBy('name')->get(),
        ]);
    }

    public function storeLeave(Request $request)
    {
        $data = $request->validate([
            'transport_staff_id' => ['required', Tenant::exists('transport_staff')],
            'from_date' => ['required', 'date'], 'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['nullable', 'string', 'max:300'], 'status' => ['required', Rule::in(array_keys(StaffLeave::STATUSES))],
            'substitute_id' => ['nullable', 'different:transport_staff_id', Tenant::exists('transport_staff')],
        ], ['substitute_id.different' => 'The substitute must be another person.']);
        $leave = StaffLeave::create($data);
        $this->applyLeave($leave, $request->user()->id);

        return back()->with('success', 'Leave saved.'.($leave->substitute_id && $leave->status === 'approved' ? ' The substitute has been put on their routes for those days.' : ''));
    }

    public function updateLeave(Request $request, StaffLeave $leave)
    {
        $leave->update($request->validate(['status' => ['required', Rule::in(array_keys(StaffLeave::STATUSES))]]));
        $this->applyLeave($leave, $request->user()->id);

        return back()->with('success', 'Leave '.strtolower(StaffLeave::STATUSES[$leave->status]).'.');
    }

    public function destroyLeave(StaffLeave $leave)
    {
        $leave->delete();

        return back()->with('success', 'Leave deleted.');
    }

    /** Approved leave: mark attendance "leave" for past/today days, and put the substitute on the person's routes. */
    private function applyLeave(StaffLeave $leave, int $userId): void
    {
        if ($leave->status !== 'approved') {
            return;
        }
        foreach (CarbonPeriod::create($leave->from_date, $leave->to_date) as $d) {
            if ($d->lte(now())) {
                StaffAttendance::firstOrCreate(['transport_staff_id' => $leave->transport_staff_id, 'date' => $d->copy()->startOfDay()], ['status' => 'leave']);
            }
        }
        if ($leave->substitute_id) {
            $col = $leave->staff->type === 'conductor' ? 'conductor_id' : 'driver_id';
            foreach (TransportRoute::where($col, $leave->transport_staff_id)->get() as $route) {
                RouteChange::firstOrCreate(['transport_route_id' => $route->id, 'from_date' => $leave->from_date, 'to_date' => $leave->to_date, $col => $leave->substitute_id],
                    ['reason' => 'Substitute for '.$leave->staff->name.' (leave)', 'created_by' => $userId]);
            }
        }
    }
}
