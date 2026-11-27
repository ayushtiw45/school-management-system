<?php

namespace Tests\Unit;

use App\Services\LicenseIssuer;
use PHPUnit\Framework\TestCase;

class LicenseFormatTest extends TestCase
{
    public function test_installation_id_pattern(): void
    {
        $this->assertMatchesRegularExpression(LicenseIssuer::ID_PATTERN, 'TDS-7K2P-QX9M-4HDA');
        $this->assertDoesNotMatchRegularExpression(LicenseIssuer::ID_PATTERN, 'TDS-0K2P-QX9M-4HDA'); // 0 is not used
        $this->assertDoesNotMatchRegularExpression(LicenseIssuer::ID_PATTERN, 'TDS-7K2P-QX9M');
        $this->assertSame('TDS-7K2P-QX9M-4HDA', LicenseIssuer::normalizeId('  tds-7k2p-qx9m-4hda '));
    }

    public function test_base64url_has_no_padding_or_unsafe_chars(): void
    {
        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', LicenseIssuer::b64e(random_bytes(64)));
    }
}
