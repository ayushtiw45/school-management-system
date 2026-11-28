<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Enquiry;
use App\Models\Expense;
use App\Models\Hr\Employee;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentFee;
use App\Models\Transport\Vehicle;
use App\Reports\XlsxWriter;
use App\Support\SpreadsheetReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/** Excel download of school data, and bulk upload (students, staff, old dues) with a preview before saving. */
class DataExchange
{
    // ================================================================ EXPORT

    public const EXPORTS = [
        'students' => ['Students', 'All students with class, parents, Student ID, status, bus and fee due.'],
        'staff' => ['Staff & teachers', 'Every staff member with job, contact, bank and salary structure.'],
        'dues' => ['Pending fees', 'Every unpaid / part-paid fee instalment with balance.'],
        'payments' => ['Fee payments', 'All receipts of the current session.'],
        'expenses' => ['Expenses', 'All expenses of the current session.'],
        'enquiries' => ['Enquiries', 'All enquiries with follow-up status.'],
        'vehicles' => ['Vehicles', 'Buses with documents and expiry dates.'],
    ];

    /** @return array{0: string, 1: array<int, string>, 2: iterable} [file name, headers, rows] */
    public function export(string $type): array
    {
        $session = AcademicSession::current();
        [$from, $to] = $session ? [$session->start_date->toDateString(), $session->end_date->toDateString().' 23:59:59'] : ['2000-01-01', '2100-01-01'];
        $d = fn ($v) => $v?->format('d-m-Y');

        return match ($type) {
            'students' => ['students', ['Student ID', 'Admission No', 'First Name', 'Last Name', 'Gender', 'Date of Birth', 'Class', 'Section', 'Roll No', 'Status',
                'Admission Date', 'Father Name', 'Father Mobile', 'Father Occupation', 'Mother Name', 'Mother Mobile', 'Email', 'Address', 'City', 'State', 'Pincode',
                'Category', 'Religion', 'Blood Group', 'Previous School', 'Concession %', 'Bus Route', 'Bus Stop', 'Transport Fee', 'Fee Due'],
                Student::with(['schoolClass', 'section', 'transportRoute', 'routeStop', 'fees'])->orderBy('school_class_id')->orderBy('first_name')->lazy()->map(fn ($s) => [
                    $s->student_code, $s->admission_no, $s->first_name, $s->last_name, ucfirst((string) $s->gender), $d($s->dob), $s->schoolClass?->name, $s->section?->name, $s->roll_no,
                    Student::STATUSES[$s->status] ?? $s->status, $d($s->admission_date), $s->father_name, $s->father_phone, $s->father_occupation, $s->mother_name, $s->mother_phone,
                    $s->email, $s->address, $s->city, $s->state, $s->pincode, $s->category, $s->religion, $s->blood_group, $s->previous_school, (float) $s->concession_percent,
                    $s->transportRoute?->label(), $s->routeStop?->name, $s->transport_fee !== null ? (float) $s->transport_fee : '', round($s->fees->sum(fn ($f) => $f->balance()), 2)])],
            'staff' => ['staff', ['Employee ID', 'Name', 'Type', 'Designation', 'Department', 'Gender', 'Date of Birth', 'Mobile', 'Email', 'Address', 'Qualification',
                'Experience (yrs)', 'Joining Date', 'Employment', 'Status', 'Leaving Date', 'Aadhaar', 'PAN', 'Bank', 'Account No', 'IFSC', 'UAN', 'Basic', 'HRA', 'DA',
                'Conveyance', 'Other Allowance', 'PF', 'ESI', 'TDS', 'Other Deduction'],
                Employee::orderBy('type')->orderBy('name')->lazy()->map(fn ($e) => [$e->employee_id, $e->name, Employee::TYPES[$e->type] ?? $e->type, $e->designation, $e->department,
                    ucfirst((string) $e->gender), $d($e->dob), $e->phone, $e->email, $e->address, $e->qualification, (float) $e->experience_years, $d($e->joining_date),
                    Employee::EMPLOYMENT[$e->employment_type] ?? '', Employee::STATUSES[$e->status] ?? '', $d($e->leaving_date), $e->aadhaar_no, $e->pan_no, $e->bank_name,
                    $e->bank_account, $e->bank_ifsc, $e->uan_no, (float) $e->basic, (float) $e->hra, (float) $e->da, (float) $e->conveyance, (float) $e->other_allowance,
                    (float) $e->pf, (float) $e->esi, (float) $e->tds, (float) $e->other_deduction])],
            'dues' => ['pending-fees', ['Student ID', 'Admission No', 'Student', 'Class', 'Father Mobile', 'Fee', 'Due Date', 'Amount', 'Discount', 'Fine', 'Paid', 'Balance'],
                StudentFee::with(['student.schoolClass', 'student.section'])->where('status', '!=', 'paid')->orderBy('due_date')->lazy()
                    ->filter(fn ($f) => $f->student)->map(fn ($f) => [$f->student->student_code, $f->student->admission_no, $f->student->full_name, $f->student->class_label,
                        $f->student->parent_phone, $f->title, $d($f->due_date), (float) $f->amount, (float) $f->discount, (float) $f->fine, (float) $f->paid, $f->balance()])],
            'payments' => ['payments', ['Receipt No', 'Date', 'Student ID', 'Admission No', 'Student', 'Class', 'Mode', 'Reference', 'Amount', 'Received By', 'Remarks'],
                Payment::with(['student.schoolClass', 'student.section', 'receiver'])->whereBetween('payment_date', [$from, $to])->orderBy('payment_date')->orderBy('id')->lazy()
                    ->map(fn ($p) => [$p->receipt_no, $d($p->payment_date), $p->student?->student_code, $p->student?->admission_no, $p->student?->full_name, $p->student?->class_label,
                        $p->methodLabel(), $p->reference_no, (float) $p->amount, $p->receiver?->name, $p->remarks])],
            'expenses' => ['expenses', ['Date', 'Category', 'Details', 'Paid To', 'Salary Month', 'Mode', 'Reference', 'Amount'],
                Expense::with('category')->whereBetween('expense_date', [$from, $to])->orderBy('expense_date')->lazy()
                    ->map(fn ($e) => [$d($e->expense_date), $e->category?->name, $e->title, $e->paid_to, $e->salary_month, Payment::METHODS[$e->payment_method] ?? $e->payment_method, $e->reference_no, (float) $e->amount])],
            'enquiries' => ['enquiries', ['Date', 'Child', 'Class', 'Parent', 'Mobile', 'Other Mobile', 'Email', 'Source', 'Status', 'Next Follow-up', 'Notes'],
                Enquiry::with('schoolClass')->latest('id')->lazy()->map(fn ($e) => [$d($e->created_at), $e->child_name, $e->schoolClass?->name, $e->parent_name, $e->phone, $e->alt_phone,
                    $e->email, Enquiry::SOURCES[$e->source] ?? $e->source, Enquiry::STATUSES[$e->status] ?? $e->status, $d($e->next_follow_up), $e->notes])],
            'vehicles' => ['vehicles', ['Bus No', 'Registration', 'Company', 'Model', 'Seats', 'Fuel', 'Ownership', 'Insurance Valid Till', 'PUC Valid Till', 'Permit Valid Till', 'Fitness Valid Till', 'Monthly EMI', 'Monthly Rent', 'Active'],
                Vehicle::orderBy('bus_no')->lazy()->map(fn ($v) => [$v->bus_no, $v->registration_no, $v->make, $v->model, (int) $v->seats, Vehicle::FUELS[$v->fuel_type] ?? '', Vehicle::OWNERSHIP[$v->ownership] ?? '',
                    $d($v->insurance_expiry), $d($v->puc_expiry), $d($v->permit_expiry), $d($v->fitness_expiry), (float) $v->monthly_emi, (float) $v->monthly_rent, $v->is_active ? 'Yes' : 'No'])],
        };
    }

    public function exportXlsx(string $type): string
    {
        [, $headers, $rows] = $this->export($type);

        return XlsxWriter::table($headers, $rows);
    }

    /** Every export in one zip file. */
    public function exportAllZip(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'tdsall');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        foreach (array_keys(self::EXPORTS) as $type) {
            [$name] = $this->export($type);
            $zip->addFromString($name.'.xlsx', $this->exportXlsx($type));
        }
        $zip->close();
        $data = file_get_contents($tmp);
        @unlink($tmp);

        return $data;
    }

    // ================================================================ IMPORT

    /** type => [title, description, columns: key => [label, required, aliases]] */
    public static function imports(): array
    {
        return [
            'students' => ['Students', 'Move students from a register or old software. Class must match a class name in the software (e.g. "Class 5", "LKG").', [
                'first_name' => ['First Name', true, ['firstname', 'name', 'studentname', 'student']],
                'last_name' => ['Last Name', false, ['lastname', 'surname']],
                'gender' => ['Gender', true, ['sex']],
                'dob' => ['Date of Birth', true, ['dateofbirth', 'birthdate', 'dob']],
                'class' => ['Class', true, ['classname', 'standard', 'std']],
                'section' => ['Section', false, ['sec', 'division']],
                'roll_no' => ['Roll No', false, ['rollno', 'roll', 'rollnumber']],
                'admission_no' => ['Admission No', false, ['admissionno', 'admno', 'admissionnumber', 'srno', 'scholarno']],
                'admission_date' => ['Admission Date', false, ['admissiondate', 'dateofadmission', 'doa']],
                'father_name' => ['Father Name', true, ['fathername', 'father', 'fathersname', 'parentname', 'guardianname']],
                'father_phone' => ['Father Mobile', true, ['fathermobile', 'fatherphone', 'mobile', 'phone', 'contact', 'mobileno', 'parentmobile']],
                'father_occupation' => ['Father Occupation', false, ['fatheroccupation', 'occupation']],
                'mother_name' => ['Mother Name', false, ['mothername', 'mother', 'mothersname']],
                'mother_phone' => ['Mother Mobile', false, ['mothermobile', 'motherphone']],
                'email' => ['Email', false, ['emailid', 'email']],
                'address' => ['Address', false, ['address', 'residentialaddress']],
                'city' => ['City', false, ['city', 'town']],
                'state' => ['State', false, []],
                'pincode' => ['Pincode', false, ['pin', 'pincode', 'zip']],
                'category' => ['Category', false, ['caste']],
                'religion' => ['Religion', false, []],
                'blood_group' => ['Blood Group', false, ['bloodgroup', 'blood']],
                'previous_school' => ['Previous School', false, ['previousschool', 'lastschool']],
                'concession_percent' => ['Concession %', false, ['concession', 'discount', 'concessionpercent']],
            ]],
            'staff' => ['Staff & teachers', 'Add all teachers and staff at once, with their salary structure if you want.', [
                'name' => ['Name', true, ['fullname', 'staffname', 'teachername', 'employeename']],
                'type' => ['Type', false, ['stafftype', 'teaching']],
                'employee_id' => ['Employee ID', false, ['employeeid', 'empid', 'empcode', 'staffid']],
                'designation' => ['Designation', false, ['post', 'designation']],
                'department' => ['Department', false, ['dept']],
                'gender' => ['Gender', false, ['sex']],
                'dob' => ['Date of Birth', false, ['dateofbirth', 'birthdate']],
                'phone' => ['Mobile', false, ['mobile', 'phone', 'mobileno', 'contact']],
                'email' => ['Email', false, ['emailid']],
                'address' => ['Address', false, []],
                'qualification' => ['Qualification', false, ['education']],
                'joining_date' => ['Joining Date', false, ['joiningdate', 'dateofjoining', 'doj']],
                'employment_type' => ['Employment', false, ['employmenttype', 'jobtype']],
                'aadhaar_no' => ['Aadhaar', false, ['aadhaarno', 'aadhar', 'aadharno']],
                'pan_no' => ['PAN', false, ['panno', 'pancard']],
                'bank_name' => ['Bank', false, ['bankname']],
                'bank_account' => ['Account No', false, ['accountno', 'bankaccount', 'accountnumber']],
                'bank_ifsc' => ['IFSC', false, ['ifsccode']],
                'basic' => ['Basic', false, ['basicpay', 'basicsalary', 'salary']],
                'hra' => ['HRA', false, []],
                'da' => ['DA', false, []],
                'conveyance' => ['Conveyance', false, []],
                'other_allowance' => ['Other Allowance', false, ['otherallowance', 'allowance']],
                'pf' => ['PF', false, ['providentfund']],
                'esi' => ['ESI', false, []],
                'tds' => ['TDS', false, ['tax']],
            ]],
            'dues' => ['Old pending fees', 'Bring in fees students still owe from before (from the old register or software). Each row is one due.', [
                'student' => ['Student ID or Admission No', true, ['studentid', 'admissionno', 'admno', 'studentidoradmissionno', 'student']],
                'title' => ['Fee', true, ['fee', 'feename', 'particulars', 'description', 'title']],
                'amount' => ['Amount', true, ['amount', 'due', 'balance', 'dueamount']],
                'due_date' => ['Due Date', false, ['duedate', 'date']],
            ]],
        ];
    }

    /** Sample rows for the template files. */
    public static function sample(string $type): array
    {
        return match ($type) {
            'students' => [['Aarav', 'Sharma', 'Male', '10-05-2018', 'Class 1', 'A', '12', '', '01-04-2026', 'Rakesh Sharma', '9876543210', 'Business', 'Sunita Sharma', '', '', 'Gomti Nagar', 'Lucknow', 'Uttar Pradesh', '226010', 'General', 'Hindu', 'B+', '', '0']],
            'staff' => [['Priya Verma', 'Teaching', '', 'TGT', 'Secondary', 'Female', '15-08-1990', '9876500001', 'priya@example.com', 'Lucknow', 'M.Sc. B.Ed.', '01-04-2022', 'Permanent', '', '', 'SBI', '12345678901', 'SBIN0001234', '25000', '3000', '0', '0', '0', '1800', '0', '0']],
            'dues' => [['STU26-0001', 'Previous year balance', '2500', '10-04-2026']],
            default => [],
        };
    }

    public function templateXlsx(string $type): string
    {
        $cols = self::imports()[$type][2];

        return XlsxWriter::table(array_map(fn ($c) => $c[0].($c[1] ? ' *' : ''), array_values($cols)), self::sample($type));
    }

    /**
     * Read a file and check every row. Returns rows ready to save and rows with problems.
     *
     * @return array{valid: array<int, array>, errors: array<int, array{row: int, data: array, messages: array}>, unknown: array<int, string>, total: int}
     */
    public function parse(string $type, string $path, string $ext): array
    {
        $cols = self::imports()[$type][2];
        $rows = SpreadsheetReader::read($path, $ext);
        if (count($rows) < 2) {
            throw new \RuntimeException('The file has no data rows. Keep the heading row and add one row per record below it.');
        }
        // match headings to fields
        $norm = fn ($h) => preg_replace('/[^a-z0-9]/', '', strtolower(str_replace('*', '', $h)));
        $map = [];
        $unknown = [];
        foreach ($rows[0] as $i => $h) {
            $n = $norm($h);
            $field = collect($cols)->search(fn ($c, $k) => $norm($c[0]) === $n || $norm($k) === $n || in_array($n, $c[2], true));
            if ($field !== false && ! in_array($field, $map, true)) {
                $map[$i] = $field;
            } elseif ($h !== '') {
                $unknown[] = $h;
            }
        }
        $missing = collect($cols)->filter(fn ($c) => $c[1])->keys()->diff($map)->values();
        if ($missing->isNotEmpty()) {
            throw new \RuntimeException('These columns are missing: '.$missing->map(fn ($k) => $cols[$k][0])->implode(', ').'. Download the template to see the headings.');
        }

        $ctx = $this->context($type);
        $valid = [];
        $errors = [];
        foreach (array_slice($rows, 1) as $n => $row) {
            $data = [];
            foreach ($map as $i => $field) {
                $data[$field] = $row[$i] ?? '';
            }
            [$clean, $messages] = $this->{'check'.Str::studly($type)}($data, $ctx);
            if ($messages) {
                $errors[] = ['row' => $n + 2, 'data' => $data, 'messages' => $messages];
            } else {
                $valid[] = $clean + ['_row' => $n + 2];
            }
        }

        return ['valid' => $valid, 'errors' => $errors, 'unknown' => $unknown, 'total' => count($rows) - 1];
    }

    private function context(string $type): array
    {
        return match ($type) {
            'students' => ['classes' => SchoolClass::with('sections')->get(), 'admNos' => Student::pluck('admission_no')->map(fn ($v) => strtolower((string) $v))->flip()->all(), 'seen' => []],
            'staff' => ['ids' => Employee::pluck('employee_id')->filter()->map(fn ($v) => strtolower($v))->flip()->all(), 'seen' => []],
            'dues' => ['students' => Student::get(['id', 'student_code', 'admission_no', 'first_name', 'last_name', 'academic_session_id'])],
        };
    }

    private static function gender(string $v): ?string
    {
        $v = strtolower(trim($v));

        return match (true) {
            in_array($v, ['m', 'male', 'boy', 'b', 'पुरुष', 'लड़का'], true) => 'male',
            in_array($v, ['f', 'female', 'girl', 'g', 'महिला', 'लड़की'], true) => 'female',
            in_array($v, ['o', 'other', 'others'], true) => 'other',
            default => null,
        };
    }

    private static function phone(string $v): string
    {
        $d = preg_replace('/\D/', '', $v);
        if (strlen($d) === 12 && str_starts_with($d, '91')) {
            $d = substr($d, 2);
        }

        return $d;
    }

    private static function money(string $v): ?float
    {
        $v = str_replace([',', '₹', 'Rs', 'rs', ' '], '', $v);

        return $v === '' ? 0.0 : (is_numeric($v) ? round((float) $v, 2) : null);
    }

    private function checkStudents(array $d, array &$ctx): array
    {
        $e = [];
        $first = $d['first_name'];
        $last = $d['last_name'] ?? '';
        if ($first !== '' && $last === '' && str_contains($first, ' ') && ! isset($d['last_name'])) {
            [$first, $last] = explode(' ', $first, 2); // a single "Name" column
        }
        if ($first === '') {
            $e[] = 'Name is empty.';
        }
        $gender = self::gender($d['gender'] ?? '');
        if (! $gender) {
            $e[] = 'Gender must be Male / Female.';
        }
        $dob = SpreadsheetReader::date($d['dob'] ?? '');
        if (! $dob || $dob >= now()->toDateString()) {
            $e[] = 'Date of birth is missing or wrong (use DD-MM-YYYY).';
        }
        $classText = trim($d['class'] ?? '');
        $class = $ctx['classes']->first(fn ($c) => strcasecmp($c->name, $classText) === 0)
            ?? (ctype_digit($classText) ? $ctx['classes']->first(fn ($c) => strcasecmp($c->name, 'Class '.(int) $classText) === 0) : null)
            ?? $ctx['classes']->first(fn ($c) => strcasecmp(str_replace(['class', ' '], '', strtolower($c->name)), str_replace(['class', ' ', 'std', '.'], '', strtolower($classText))) === 0);
        if (! $class) {
            $e[] = "Class \"{$classText}\" is not in the software.";
        }
        $section = null;
        if ($class && ($d['section'] ?? '') !== '') {
            $section = $class->sections->first(fn ($s) => strcasecmp($s->name, trim($d['section'])) === 0);
            if (! $section) {
                $e[] = "Section \"{$d['section']}\" does not exist in {$class->name} (add it in Classes & Sections first).";
            }
        }
        $fatherPhone = self::phone($d['father_phone'] ?? '');
        if (($d['father_name'] ?? '') === '') {
            $e[] = 'Father name is empty.';
        }
        if (strlen($fatherPhone) < 10 || strlen($fatherPhone) > 12) {
            $e[] = 'Father mobile must have 10 digits.';
        }
        $adm = trim($d['admission_no'] ?? '');
        if ($adm !== '') {
            if (isset($ctx['admNos'][strtolower($adm)])) {
                $e[] = "Admission No {$adm} already exists.";
            } elseif (isset($ctx['seen'][strtolower($adm)])) {
                $e[] = "Admission No {$adm} is repeated in the file.";
            }
            $ctx['seen'][strtolower($adm)] = true;
        }
        $admDate = ($d['admission_date'] ?? '') !== '' ? SpreadsheetReader::date($d['admission_date']) : null;
        if (($d['admission_date'] ?? '') !== '' && ! $admDate) {
            $e[] = 'Admission date is wrong (use DD-MM-YYYY).';
        }
        $concession = self::money((string) ($d['concession_percent'] ?? ''));
        if ($concession === null || $concession > 100) {
            $e[] = 'Concession % must be a number from 0 to 100.';
        }
        if (($d['email'] ?? '') !== '' && ! filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $e[] = 'Email looks wrong.';
        }

        $clean = ['first_name' => Str::title($first), 'last_name' => Str::title($last) ?: null, 'gender' => $gender, 'dob' => $dob, 'school_class_id' => $class?->id,
            'section_id' => $section?->id, 'roll_no' => $d['roll_no'] ?? null ?: null, 'admission_no' => $adm ?: null, 'admission_date' => $admDate,
            'father_name' => Str::title($d['father_name'] ?? ''), 'father_phone' => $fatherPhone, 'father_occupation' => ($d['father_occupation'] ?? '') ?: null,
            'mother_name' => ($d['mother_name'] ?? '') ? Str::title($d['mother_name']) : null, 'mother_phone' => self::phone($d['mother_phone'] ?? '') ?: null,
            'email' => ($d['email'] ?? '') ?: null, 'address' => ($d['address'] ?? '') ?: null, 'city' => ($d['city'] ?? '') ?: null, 'state' => ($d['state'] ?? '') ?: null,
            'pincode' => ($d['pincode'] ?? '') ?: null, 'category' => ($d['category'] ?? '') ?: null, 'religion' => ($d['religion'] ?? '') ?: null,
            'blood_group' => ($d['blood_group'] ?? '') ? strtoupper($d['blood_group']) : null, 'previous_school' => ($d['previous_school'] ?? '') ?: null,
            'concession_percent' => $concession ?? 0, '_class' => $class?->name.($section ? ' '.$section->name : '')];

        return [$clean, $e];
    }

    private function checkStaff(array $d, array &$ctx): array
    {
        $e = [];
        if (trim($d['name'] ?? '') === '') {
            $e[] = 'Name is empty.';
        }
        $t = strtolower(trim($d['type'] ?? ''));
        $type = in_array($t, ['non teaching', 'non-teaching', 'nonteaching', 'non_teaching', 'staff', 'other', 'no'], true) ? 'non_teaching' : 'teaching';
        $id = trim($d['employee_id'] ?? '');
        if ($id !== '') {
            if (isset($ctx['ids'][strtolower($id)])) {
                $e[] = "Employee ID {$id} already exists.";
            } elseif (isset($ctx['seen'][strtolower($id)])) {
                $e[] = "Employee ID {$id} is repeated in the file.";
            }
            $ctx['seen'][strtolower($id)] = true;
        }
        $gender = ($d['gender'] ?? '') !== '' ? self::gender($d['gender']) : null;
        if (($d['gender'] ?? '') !== '' && ! $gender) {
            $e[] = 'Gender must be Male / Female.';
        }
        $dates = [];
        foreach (['dob' => 'Date of birth', 'joining_date' => 'Joining date'] as $k => $l) {
            $dates[$k] = ($d[$k] ?? '') !== '' ? SpreadsheetReader::date($d[$k]) : null;
            if (($d[$k] ?? '') !== '' && ! $dates[$k]) {
                $e[] = "{$l} is wrong (use DD-MM-YYYY).";
            }
        }
        $emp = strtolower(trim($d['employment_type'] ?? ''));
        $employment = collect(Employee::EMPLOYMENT)->search(fn ($l, $k) => $emp !== '' && (str_starts_with(strtolower($l), $emp) || $k === str_replace([' ', '-'], '_', $emp))) ?: 'permanent';
        $money = [];
        foreach ([...array_keys(Employee::EARNINGS), ...array_keys(Employee::DEDUCTIONS)] as $k) {
            if (! array_key_exists($k, $d)) {
                continue;
            }
            $money[$k] = self::money((string) $d[$k]);
            if ($money[$k] === null) {
                $e[] = strtoupper($k).' must be a number.';
            }
        }
        $pan = strtoupper(trim($d['pan_no'] ?? ''));
        if ($pan !== '' && ! preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan)) {
            $e[] = 'PAN looks wrong.';
        }
        if (($d['email'] ?? '') !== '' && ! filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $e[] = 'Email looks wrong.';
        }

        return [['name' => Str::title(trim($d['name'] ?? '')), 'type' => $type, 'employee_id' => $id ?: null, 'designation' => ($d['designation'] ?? '') ?: null,
            'department' => ($d['department'] ?? '') ?: null, 'gender' => $gender, 'dob' => $dates['dob'], 'phone' => self::phone($d['phone'] ?? '') ?: null,
            'email' => ($d['email'] ?? '') ?: null, 'address' => ($d['address'] ?? '') ?: null, 'qualification' => ($d['qualification'] ?? '') ?: null,
            'joining_date' => $dates['joining_date'], 'employment_type' => $employment, 'aadhaar_no' => preg_replace('/\D/', '', $d['aadhaar_no'] ?? '') ?: null,
            'pan_no' => $pan ?: null, 'bank_name' => ($d['bank_name'] ?? '') ?: null, 'bank_account' => preg_replace('/\s/', '', $d['bank_account'] ?? '') ?: null,
            'bank_ifsc' => strtoupper(trim($d['bank_ifsc'] ?? '')) ?: null] + array_map(fn ($v) => $v ?? 0, $money), $e];
    }

    private function checkDues(array $d, array &$ctx): array
    {
        $e = [];
        $key = strtolower(trim($d['student'] ?? ''));
        $student = $key === '' ? null : $ctx['students']->first(fn ($s) => strtolower((string) $s->student_code) === $key || strtolower((string) $s->admission_no) === $key);
        if (! $student) {
            $e[] = 'No student with Student ID / Admission No "'.($d['student'] ?? '').'".';
        }
        if (trim($d['title'] ?? '') === '') {
            $e[] = 'Fee name is empty.';
        }
        $amount = self::money((string) ($d['amount'] ?? ''));
        if (! $amount || $amount <= 0) {
            $e[] = 'Amount must be more than 0.';
        }
        $due = ($d['due_date'] ?? '') !== '' ? SpreadsheetReader::date($d['due_date']) : now()->toDateString();
        if (! $due) {
            $e[] = 'Due date is wrong (use DD-MM-YYYY).';
        }

        return [['student_id' => $student?->id, 'title' => mb_substr(trim($d['title'] ?? ''), 0, 150), 'amount' => $amount, 'due_date' => $due,
            '_student' => $student ? trim($student->first_name.' '.$student->last_name) : ''], $e];
    }

    /** Save the checked rows. Returns how many were saved. */
    public function save(string $type, array $rows, array $options, FeeService $fees): int
    {
        return DB::transaction(function () use ($type, $rows, $options, $fees) {
            $n = 0;
            $session = AcademicSession::current();
            foreach ($rows as $r) {
                $r = array_filter($r, fn ($k) => ! str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY);
                if ($type === 'students') {
                    abort_unless($session, 422, 'Create an academic session first.');
                    $status = $options['status'] ?? 'active';
                    $student = Student::create(array_merge($r, [
                        'admission_no' => $r['admission_no'] ?: NumberGenerator::admissionNo($session),
                        'admission_date' => $r['admission_date'] ?: now()->toDateString(),
                        'admission_type' => 'manual', 'academic_session_id' => $session->id, 'status' => 'pending',
                    ]));
                    if ($status === 'active') {
                        $student->update(['status' => 'active', 'confirmed_at' => now(), 'student_code' => NumberGenerator::studentCode()]);
                        if (! empty($options['fees'])) {
                            $fees->generateForStudent($student->fresh(), null, includeOneTime: ! empty($options['one_time']));
                        }
                    } elseif (! empty($options['fees'])) {
                        $fees->prepareAdmission($student->fresh());
                    }
                } elseif ($type === 'staff') {
                    Employee::create(array_merge($r, ['employee_id' => $r['employee_id'] ?: StaffService::nextEmployeeId($r['type']), 'status' => 'active']));
                } elseif ($type === 'dues') {
                    $student = Student::find($r['student_id']);
                    StudentFee::create(['student_id' => $student->id, 'academic_session_id' => $session?->id ?? $student->academic_session_id, 'fee_head_id' => null,
                        'title' => $r['title'], 'due_date' => $r['due_date'], 'amount' => $r['amount'], 'discount' => 0, 'status' => 'unpaid']);
                }
                $n++;
            }

            return $n;
        });
    }
}
