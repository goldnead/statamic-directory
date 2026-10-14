<?php

namespace Goldnead\Directory\Tests\Cp;

use Illuminate\Support\Facades\Schema;
use Statamic\Facades\Permission;

class PermissionsTest extends CpTestCase
{
    public function test_listing_and_status_need_view_directory(): void
    {
        $this->actingAs($this->userWith([]));

        // Non-JSON requests are redirected to the dashboard with an error, as everywhere in the CP.
        $this->get('/cp/directory')->assertRedirect();
        $this->getJson('/cp/directory')->assertForbidden();
        $this->getJson('/cp/directory/rebuild-status')->assertForbidden();
    }

    public function test_viewer_can_read_but_not_act(): void
    {
        $profile = $this->profile(['team_id' => 1]);
        $this->actingAs($this->viewer());

        $this->getJson('/cp/directory')->assertOk();
        $this->postJson('/cp/directory/rebuild')->assertForbidden();
        $this->postJson('/cp/directory/profiles/'.$profile->id.'/rebuild')->assertForbidden();
        $this->postJson('/cp/directory/profiles/'.$profile->id.'/unbind')->assertForbidden();

        $this->assertSame(1, $profile->fresh()->team_id);
    }

    public function test_viewer_rows_carry_no_action_urls(): void
    {
        $this->profile(['team_id' => 1]);
        $this->actingAs($this->viewer());

        $row = $this->getJson('/cp/directory')->json('data.0');

        $this->assertFalse($row['can_manage']);
        $this->assertNull($row['unbind_url']);
        $this->assertNull($row['rebuild_url']);
    }

    public function test_page_renders_the_inertia_component(): void
    {
        $this->actingAs($this->viewer());

        $this->get('/cp/directory')->assertOk()->assertSee('directory::Index', false);
    }

    public function test_missing_table_gives_an_empty_listing_not_a_500(): void
    {
        Schema::drop('directory_profiles');
        $this->actingAs($this->viewer());

        $this->getJson('/cp/directory')->assertOk()->assertJsonPath('meta.total', 0);
        $this->get('/cp/directory')->assertOk();
    }

    public function test_nav_item_and_permissions_are_registered(): void
    {
        Permission::boot();
        $this->assertNotNull(Permission::get('view directory'));
        $this->assertNotNull(Permission::get('manage directory'));
        $this->assertSame('Directory', __('directory::cp.nav'));
        $this->assertSame('Verzeichnis', trans('directory::cp.nav', [], 'de'));
    }
}
