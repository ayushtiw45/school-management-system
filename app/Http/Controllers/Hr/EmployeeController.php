<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeAttendance;
use App\Models\Hr\EmployeeDocument;
use App\Models\Hr\Period;
use App\Models\Hr\TimetableEntry;
use App\Services\StaffService;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'active');
        $employees = Employee::withCount('timetable')
            ->when($status !== 'all', fn ($q) => $status === 'active' ? $q->where('status', 'active') : $q->where('status', '!=', 'active'))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->department, fn ($q, $d) => $q->where('department', $d))
            ->when($request->q, fn ($q, $t) => $q->where(fn ($q) => $q->where('name', 'like', "%$t%")->orWhere('employee_id', 'like', "%$t%")
                ->orWhere('phone', 'like', "%$t%")->orWhere('designation', 'like', "%$t%")))
            ->orderBy('type')->orderBy('name')->get();

        return view('staff.employees.index', ['employees' => $employees, 'status' => $status,
            'departments' => Employee::whereNotNull('department')->distinct()->orderBy('department')->pluck('department')]);
    }

    public function create(Request $request)
    {
        return view('staff.employees.form', ['employee' => new Employee(['type' => $request->input('type', 'teaching'), 'employment_type' => 'permanent', 'joining_date' => now()]),
            'departments' => $this->departments()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['employee_id'] = ($data['employee_id'] ?? null) ?: StaffService::nextEmployeeId($data['type']);
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('staff/'.Tenant::id().'/photos');
        }
        $employee = Employee::create($data + ['status' => 'active']);

        return redirect()->route('staff.employees.show', $employee)->with('success', 'Staff member added. Employee ID: '.$employee->employee_id);
    }

    public function show(Request $request, Employee $employee, StaffService $staff)
    {
        $employee->load(['documents', 'subjects.subject', 'subjects.schoolClass', 'subjects.section', 'classTeacherOf.schoolClass']);
        $month = now()->startOfMonth();

        return view('staff.employees.show', [
            'e' => $employee,
            'balances' => $staff->balances($employee),
            'leaves' => $employee->leaves()->with('type')->limit(10)->get(),
            'attendance' => EmployeeAttendance::where('employee_id', $employee->id)->whereDate('date', '>=', $month)->orderBy('date')->get(),
            'timetable' => TimetableEntry::with(['section.schoolClass', 'subject'])->where('employee_id', $employee->id)->get()->groupBy(fn ($t) => $t->day.'-'.$t->period_id),
            'periods' => Period::ordered()->get(),
            'payrolls' => $request->user()->canSeeAccounts() ? $employee->payrolls()->limit(12)->get() : collect(),
            'advances' => $request->user()->canSeeAccounts() ? $employee->advances()->get() : collect(),
        ]);
    }

    public function edit(Employee $employee)
    {
        return view('staff.employees.form', ['employee' => $employee, 'departments' => $this->departments()]);
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $this->validated($request, $employee);
        $data['employee_id'] = ($data['employee_id'] ?? null) ?: $employee->employee_id;
        if ($request->hasFile('photo')) {
            if ($employee->photo) {
                Storage::delete($employee->photo);
            }
            $data['photo'] = $request->file('photo')->store('staff/'.Tenant::id().'/photos');
        }
        $employee->update($data);

        return redirect()->route('staff.employees.show', $employee)->with('success', 'Saved.');
    }

    /** Resigned / terminated / retired — or back to working. */
    public function exit(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Employee::STATUSES))],
            'leaving_date' => [Rule::requiredIf($request->status !== 'active'), 'nullable', 'date'],
            'leaving_reason' => ['nullable', 'string', 'max:300'],
        ]);
        if ($data['status'] === 'active') {
            $data['leaving_date'] = null;
            $data['leaving_reason'] = null;
        } else {
            // no more class-teacher duty or timetable periods after leaving
            \App\Models\Section::where('class_teacher_id', $employee->id)->update(['class_teacher_id' => null]);
            TimetableEntry::where('employee_id', $employee->id)->update(['employee_id' => null]);
        }
        $employee->update($data);

        return back()->with('success', $data['status'] === 'active' ? 'Marked as working again.' : 'Marked as '.strtolower(Employee::STATUSES[$data['status']]).'. Their class-teacher duty and timetable periods were freed.');
    }

    public function photo(Employee $employee)
    {
        abort_unless($employee->photo && Storage::exists($employee->photo), 404);

        return Storage::response($employee->photo);
    }

    public function storeDocument(Request $request, Employee $employee)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:100'], 'valid_till' => ['nullable', 'date'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:4096']], ['file.max' => 'The file must be under 4 MB.']);
        $employee->documents()->create(['title' => $data['title'], 'valid_till' => $data['valid_till'] ?? null,
            'path' => $request->file('file')->store('staff/'.Tenant::id().'/documents')]);

        return back()->with('success', 'Document uploaded.');
    }

    public function showDocument(EmployeeDocument $document)
    {
        abort_unless(Storage::exists($document->path), 404);

        return Storage::response($document->path);
    }

    public function destroyDocument(EmployeeDocument $document)
    {
        Storage::delete($document->path);
        $document->delete();

        return back()->with('success', 'Document deleted.');
    }

    public function idCards(Request $request)
    {
        $employees = $request->employee ? Employee::whereKey($request->employee)->get() : Employee::active()->orderBy('type')->orderBy('name')
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))->get();

        return view('staff.employees.idcards', compact('employees'));
    }

    /** Joining letter, experience certificate, relieving letter. */
    public function letter(Request $request, Employee $employee, string $type)
    {
        abort_unless(in_array($type, ['joining', 'experience', 'relieving'], true), 404);

        return view('staff.employees.letter', ['e' => $employee, 'type' => $type, 'date' => $request->date('date') ?? now()]);
    }

    /** Salary structure only (admin & accounts). */
    public function salary(Request $request, Employee $employee)
    {
        $rules = [];
        foreach ([...array_keys(Employee::EARNINGS), ...array_keys(Employee::DEDUCTIONS)] as $k) {
            $rules[$k] = ['nullable', 'numeric', 'min:0', 'max:9999999'];
        }
        $employee->update(array_map(fn ($v) => $v ?? 0, $request->validate($rules)));

        return back()->with('success', 'Salary structure saved. Gross '.inr($employee->gross()).', net '.inr($employee->gross() - $employee->deductions()).' per month.');
    }

    private function departments()
    {
        return Employee::whereNotNull('department')->distinct()->pluck('department')->merge(['Pre-primary', 'Primary', 'Middle', 'Secondary', 'Senior Secondary', 'Administration', 'Accounts', 'Support staff'])->unique()->sort()->values();
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(Employee::TYPES))],
            'name' => ['required', 'string', 'max:100'],
            'employee_id' => ['nullable', 'string', 'max:30', Tenant::unique('employees', 'employee_id')->ignore($employee?->id)],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])], 'dob' => ['nullable', 'date', 'before:today'],
            'blood_group' => ['nullable', 'string', 'max:5'], 'phone' => ['nullable', 'regex:/^[0-9+\-\s]{10,15}$/'], 'alt_phone' => ['nullable', 'regex:/^[0-9+\-\s]{10,15}$/'],
            'email' => ['nullable', 'email', 'max:150'], 'address' => ['nullable', 'string', 'max:300'],
            'designation' => ['nullable', 'string', 'max:60'], 'department' => ['nullable', 'string', 'max:60'],
            'qualification' => ['nullable', 'string', 'max:150'], 'specialization' => ['nullable', 'string', 'max:150'],
            'experience_years' => ['nullable', 'numeric', 'min:0', 'max:60'], 'joining_date' => ['nullable', 'date'],
            'employment_type' => ['required', Rule::in(array_keys(Employee::EMPLOYMENT))], 'contract_end' => ['nullable', 'date'],
            'aadhaar_no' => ['nullable', 'regex:/^[0-9 ]{12,14}$/'], 'pan_no' => ['nullable', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/'],
            'bank_name' => ['nullable', 'string', 'max:100'], 'bank_account' => ['nullable', 'regex:/^[0-9A-Za-z ]{6,30}$/'],
            'bank_ifsc' => ['nullable', 'regex:/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/'], 'uan_no' => ['nullable', 'string', 'max:20'], 'esi_no' => ['nullable', 'string', 'max:20'],
            'emergency_name' => ['nullable', 'string', 'max:100'], 'emergency_phone' => ['nullable', 'regex:/^[0-9+\-\s]{10,15}$/'],
            'notes' => ['nullable', 'string', 'max:2000'], 'photo' => ['nullable', 'image', 'max:2048'],
        ], ['pan_no.regex' => 'PAN looks wrong (e.g. ABCDE1234F).', 'bank_ifsc.regex' => 'IFSC looks wrong (e.g. SBIN0001234).', 'aadhaar_no.regex' => 'Aadhaar must be 12 digits.']);
        unset($data['photo']);
        foreach (['pan_no', 'bank_ifsc'] as $k) {
            if (! empty($data[$k])) {
                $data[$k] = strtoupper($data[$k]);
            }
        }

        return $data;
    }
}
