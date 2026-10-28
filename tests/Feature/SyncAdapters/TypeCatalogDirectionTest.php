<?php

namespace Tests\Feature\SyncAdapters;

use App\SyncAdapters\Addigy\AddigyAdapter;
use App\SyncAdapters\Jamf\JamfAdapter;
use App\SyncAdapters\Osctrl\OsctrlAdapter;
use App\SyncAdapters\PushableAdapter;
use App\SyncAdapters\SyncAdapter;
use Tests\TestCase;

class TypeCatalogDirectionTest extends TestCase
{
    public function test_catalog_entry_shape_includes_direction_key(): void
    {
        $catalog = SyncAdapter::typeCatalog();

        $this->assertNotEmpty($catalog);
        foreach ($catalog as $slug => $entry) {
            $this->assertArrayHasKey('label', $entry, "Missing label on {$slug}.");
            $this->assertArrayHasKey('docs_url', $entry, "Missing docs_url on {$slug}.");
            $this->assertArrayHasKey('direction', $entry, "Missing direction on {$slug}.");
            $this->assertContains($entry['direction'], ['pull', 'both'], "Unexpected direction value on {$slug}: {$entry['direction']}");
        }
    }

    public function test_direction_matches_pushable_adapter_interface_implementation(): void
    {
        $catalog = SyncAdapter::typeCatalog();
        $types = SyncAdapter::allTypes();

        foreach ($catalog as $slug => $entry) {
            $class = $types[$slug];
            $expected = is_a($class, PushableAdapter::class, true) ? 'both' : 'pull';
            $this->assertSame(
                $expected,
                $entry['direction'],
                "{$slug} direction derivation drifted from PushableAdapter implementation.",
            );
        }
    }

    public function test_pull_only_adapter_reports_pull(): void
    {
        // osctrl is pull-only. Guards against a regression where every
        // adapter would start reporting 'both' due to a derivation bug.
        $this->assertFalse(is_a(OsctrlAdapter::class, PushableAdapter::class, true));
        $this->assertSame('pull', SyncAdapter::typeCatalog()['osctrl']['direction']);
    }

    public function test_push_capable_adapter_reports_both(): void
    {
        // Jamf implements PushableAdapter. Guards against a regression
        // where push-capable adapters would silently be downgraded to
        // pull-only in the catalog UI.
        $this->assertTrue(is_a(JamfAdapter::class, PushableAdapter::class, true));
        $this->assertSame('both', SyncAdapter::typeCatalog()['jamf']['direction']);
    }

    public function test_addigy_direction_reflects_current_capability(): void
    {
        // Addigy's direction tracks whatever PushableAdapter state the
        // class is in. This test proves the catalog follows the class
        // without an explicit list in the catalog method.
        $expected = is_a(AddigyAdapter::class, PushableAdapter::class, true) ? 'both' : 'pull';
        $this->assertSame($expected, SyncAdapter::typeCatalog()['addigy']['direction']);
    }
}
