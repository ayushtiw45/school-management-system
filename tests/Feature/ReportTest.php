<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Reports\ReportService;
use Database\Seeders\DemoDataSeeder;

class ReportTest extends SchoolTestCase
{
    public function test_every_report_renders_and_exports(): void
    {
        $this->seed(DemoDataSeeder::class);
        $this->actingAs($this->user('accountant'));

        foreach (array_keys(ReportService::REPORTS) as $type) {
            $this->get(route('reports.show', $type))->assertOk();

            $xlsx = $this->get(route('reports.show', [$type, 'format' => 'xlsx']));
            $xlsx->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $this->assertStringStartsWith('PK', $xlsx->getContent(), "$type xlsx is not a zip");

            $pdf = $this->get(route('reports.show', [$type, 'format' => 'pdf']));
            $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->assertStringStartsWith('%PDF', $pdf->getContent(), "$type pdf invalid");
        }

        $this->get(route('reports.index'))->assertOk();
        $this->get(route('reports.show', 'nope'))->assertNotFound();
    }

    public function test_daily_report_totals_match_payments(): void
    {
        $student = $this->admit();
        $fees = $student->fees()->take(2)->get();
        $user = $this->user('accountant');
        foreach ([['cash', 500], ['upi', 700]] as $i => [$method, $amt]) {
            $this->actingAs($user)->post(route('payments.store', $student), [
                'lines' => [$fees[$i]->id => ['amount' => $amt]],
                'payment_date' => now()->toDateString(), 'method' => $method, 'reference_no' => 'X1',
            ]);
        }

        $report = app(ReportService::class)->build('daily', request());
        $this->assertCount(2, $report->rows);
        $this->assertEquals(1200, $report->totals['amount']);
        $this->assertEquals(Payment::sum('amount'), $report->totals['amount']);
    }

    public function test_admission_staff_cannot_open_reports(): void
    {
        $this->actingAs($this->user('admission_staff'))->get(route('reports.index'))->assertForbidden();
    }

    public function test_xlsx_contains_report_rows(): void
    {
        $student = $this->admit();
        $this->actingAs($this->user())->get(route('reports.show', ['studentwise', 'format' => 'xlsx']))->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($tmp, $this->get(route('reports.show', ['studentwise', 'format' => 'xlsx']))->getContent());
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($tmp);

        $this->assertStringContainsString($student->admission_no, $sheet);
        $this->assertNotFalse(simplexml_load_string($sheet), 'sheet XML must be valid');
    }
}
