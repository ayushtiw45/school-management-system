<?php

namespace Tests\Feature;

use App\Models\Hr\Employee;
use App\Models\Student;
use App\Models\StudentFee;
use App\Reports\XlsxWriter;
use App\Support\SpreadsheetReader;
use Illuminate\Http\UploadedFile;

class DataExchangeTest extends SchoolTestCase
{
    private function xlsx(array $headers, array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        file_put_contents($path, XlsxWriter::table($headers, $rows));

        return new UploadedFile($path, 'students.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function upload(string $type, UploadedFile $file): string
    {
        $res = $this->post(route('data.upload', $type), ['file' => $file])->assertRedirect();

        return basename(parse_url($res->headers->get('Location'), PHP_URL_PATH));
    }

    public function test_exports_templates_and_reader_round_trip(): void
    {
        $student = $this->admit();
        $this->actingAs($this->user('admin'));
        foreach (array_keys(\App\Services\DataExchange::EXPORTS) as $type) {
            $this->get(route('data.export', $type))->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }
        $res = $this->get(route('data.export', 'students'))->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($file, $res->getContent());
        $rows = SpreadsheetReader::read($file, 'xlsx');
        $this->assertSame('Student ID', $rows[0][0]);
        $this->assertSame($student->student_code, $rows[1][0]);
        $this->assertSame('Aarav', $rows[1][2]);

        $zip = $this->get(route('data.export.all'))->assertOk()->assertHeader('Content-Type', 'application/zip');
        file_put_contents($file, $zip->getContent());
        $z = new \ZipArchive();
        $z->open($file);
        $this->assertSame(count(\App\Services\DataExchange::EXPORTS), $z->numFiles);

        foreach (['students', 'staff', 'dues'] as $t) {
            $this->get(route('data.template', $t))->assertOk();
        }
        $this->get(route('data.index'))->assertOk()->assertSee('Download everything')->assertSee('Import students');
    }

    public function test_student_import_checks_rows_then_admits_with_ids_and_fees(): void
    {
        $this->actingAs($this->user('admin'));
        $file = $this->xlsx(['First Name *', 'Last Name', 'Gender *', 'Date of Birth *', 'Class *', 'Section', 'Father Name *', 'Father Mobile *', 'Admission No', 'Unknown col'], [
            ['riya', 'gupta', 'Girl', '05-06-2017', 'Class 2', 'A', 'amit gupta', '+91 98111 22233', 'OLD-1', 'x'],
            ['Kabir', '', 'M', 43595, '3', '', 'Raj', '9811122234', '', ''],                 // Excel date number, "3" = Class 3
            ['Bad', 'Row', 'X', '31-02-2019', 'Class 99', 'Z', '', '123', 'OLD-1', ''],       // many problems + repeated adm no
        ]);
        $token = $this->upload('students', $file);
        $this->get(route('data.preview', $token))->assertOk()->assertSee('Ready to import (2)')->assertSee('Class "Class 99" is not in the software')
            ->assertSee('Unknown col')->assertSee('Riya');

        $before = Student::count();
        $this->post(route('data.confirm', $token), ['status' => 'active', 'fees' => 1])->assertRedirect(route('students.index'))->assertSessionHas('success');
        $this->assertSame($before + 2, Student::count());
        $riya = Student::where('first_name', 'Riya')->firstOrFail();
        $this->assertSame('Gupta', $riya->last_name);
        $this->assertSame('female', $riya->gender);
        $this->assertSame('9811122233', $riya->father_phone);
        $this->assertSame('OLD-1', $riya->admission_no);
        $this->assertSame('active', $riya->status);
        $this->assertNotNull($riya->student_code);
        $this->assertSame('A', $riya->section->name);
        $this->assertGreaterThan(0, $riya->fees()->count());
        $this->assertSame(0, $riya->fees()->where('title', 'Admission Fee')->count(), 'one-time fees only when ticked');
        $kabir = Student::where('first_name', 'Kabir')->firstOrFail();
        $this->assertSame('2019-05-10', $kabir->dob->toDateString());
        $this->assertSame('Class 3', $kabir->schoolClass->name);
        $this->get(route('data.preview', $token))->assertNotFound();

        // the same admission no cannot come in twice
        $token = $this->upload('students', $this->xlsx(['First Name', 'Gender', 'DOB', 'Class', 'Father Name', 'Mobile', 'Adm No'], [['A', 'Boy', '01-01-2018', 'Class 1', 'B', '9811111111', 'OLD-1']]));
        $this->get(route('data.preview', $token))->assertSee('Admission No OLD-1 already exists');
    }

    public function test_csv_staff_and_old_dues_import_and_permissions(): void
    {
        $student = $this->admit();
        $this->actingAs($this->user('admin'));
        $csv = tempnam(sys_get_temp_dir(), 'c').'.csv';
        file_put_contents($csv, "\xEF\xBB\xBFName,Type,Designation,Mobile,Joining Date,Basic,HRA,PF\nPriya Verma,Teaching,TGT,9876500001,01/04/2022,\"25,000\",3000,1800\nRamu,Non teaching,Peon,9876500002,,8000,,\n,,,,,,,\n");
        $token = $this->upload('staff', new UploadedFile($csv, 'staff.csv', 'text/csv', null, true));
        $this->post(route('data.confirm', $token))->assertSessionHas('success');
        $priya = Employee::where('name', 'Priya Verma')->firstOrFail();
        $this->assertSame('TCH-001', $priya->employee_id);
        $this->assertEquals(25000, $priya->basic);
        $this->assertSame('2022-04-01', $priya->joining_date->toDateString());
        $this->assertSame('non_teaching', Employee::where('name', 'Ramu')->value('type'));

        $token = $this->upload('dues', $this->xlsx(['Student ID or Admission No', 'Fee', 'Amount', 'Due Date'], [
            [$student->student_code, 'Previous year balance', '2,500', '10-04-2026'], [$student->admission_no, 'Old van fee', 600, ''], ['NOPE', 'X', 100, ''],
        ]));
        $this->get(route('data.preview', $token))->assertSee('Ready to import (2)')->assertSee('No student with');
        $this->post(route('data.confirm', $token))->assertRedirect(route('fees.pending'));
        $this->assertEquals(2500, StudentFee::where('student_id', $student->id)->where('title', 'Previous year balance')->value('amount'));

        // missing required column → clear message
        $this->post(route('data.upload', 'students'), ['file' => $this->xlsx(['Name', 'Class'], [['A', 'Class 1']])])->assertSessionHas('error');

        // principal downloads but cannot import; accounts cannot open
        $this->actingAs($this->user('principal'));
        $this->get(route('data.export', 'students'))->assertOk();
        $this->post(route('data.upload', 'students'), ['file' => $this->xlsx(['A'], [['b']])])->assertForbidden();
        $this->actingAs($this->user('accountant'))->get(route('data.index'))->assertForbidden();
    }
}
