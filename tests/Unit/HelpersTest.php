<?php

namespace Tests\Unit;

use App\Reports\ReportData;
use App\Services\LicenseService;
use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    public function test_indian_currency_format(): void
    {
        $this->assertSame('₹0.00', inr(0));
        $this->assertSame('₹999.50', inr(999.5));
        $this->assertSame('₹1,23,456.00', inr(123456));
        $this->assertSame('₹12,34,56,789.10', inr(123456789.1));
        $this->assertSame('-₹5,000.00', inr(-5000));
        $this->assertSame('1,00,000.00', inr(100000, false));
    }

    public function test_report_sum_and_format(): void
    {
        $r = new ReportData('T', 'S', ['a' => ['label' => 'A', 'type' => 'money'], 'n' => ['label' => 'N', 'type' => 'int']], []);
        $this->assertSame(['a' => 30.5, 'n' => 3.0], ReportData::sum([['a' => 10.25, 'n' => 1], ['a' => 20.25, 'n' => 2]], ['a', 'n']));
        $this->assertSame('1,500.00', $r->format('a', 1500));
        $this->assertSame('', $r->format('a', null));
    }

    public function test_base64url_round_trip(): void
    {
        $bin = random_bytes(64);
        $enc = LicenseService::b64e($bin);
        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $enc);
        $this->assertSame($bin, LicenseService::b64d($enc));
    }
}
