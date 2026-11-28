<?php

namespace Tests\Feature;

use App\Support\Changelog;
use App\Support\Tenant;

class WhatsNewTest extends SchoolTestCase
{
    public function test_every_version_has_release_notes(): void
    {
        $version = trim(file_get_contents(base_path('VERSION')));
        $latest = Changelog::latest();
        $this->assertSame($version, $latest['version'], 'Add an entry for '.$version.' at the top of resources/data/changelog.json');
        foreach (Changelog::releases() as $r) {
            $this->assertNotEmpty($r['changes'], "v{$r['version']} has no changes");
            foreach ($r['changes'] as $c) {
                $this->assertArrayHasKey($c['type'], Changelog::TYPES);
                $this->assertNotEmpty($c['en']);
                $this->assertNotEmpty($c['hi']);
            }
        }
    }

    public function test_public_page_home_banner_and_hindi(): void
    {
        Tenant::set(null);
        $latest = Changelog::latest();
        $this->get(route('whats-new'))->assertOk()->assertSee("What's new")->assertSee('v'.$latest['version'])
            ->assertSee($latest['changes'][0]['en'])->assertSee('v1.2.0');
        $this->get('/')->assertOk()->assertSee('v'.$latest['version'])->assertSee(route('whats-new'));
        $this->get('/lang/hi');
        $this->get(route('whats-new'))->assertOk()->assertSee('नया क्या है')->assertSee($latest['changes'][0]['hi']);
    }
}
