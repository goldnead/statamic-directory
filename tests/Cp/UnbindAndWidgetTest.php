<?php

namespace Goldnead\Directory\Tests\Cp;

use Goldnead\Directory\Widgets\DirectoryWidget;
use Illuminate\Support\Facades\Log;
use Statamic\Widgets\VueComponent;

class UnbindAndWidgetTest extends CpTestCase
{
    public function test_manager_unbinds_the_team_and_keeps_the_profile(): void
    {
        $profile = $this->profile(['team_id' => 7, 'title' => 'Bound']);
        $other = $this->profile(['team_id' => 8]);
        Log::spy();
        $this->actingAs($this->manager());

        $this->post('/cp/directory/profiles/'.$profile->id.'/unbind')->assertRedirect();

        $this->assertNull($profile->fresh()->team_id);
        $this->assertSame('Bound', $profile->fresh()->title);
        $this->assertSame(8, $other->fresh()->team_id);
        Log::shouldHaveReceived('info')->withArgs(fn ($message, $context) => str_contains($message, 'unbound') && $context['team_id'] === 7)->once();
    }

    public function test_unbinding_an_unbound_profile_changes_nothing(): void
    {
        $profile = $this->profile();
        $this->actingAs($this->manager());

        $this->post('/cp/directory/profiles/'.$profile->id.'/unbind')->assertRedirect();

        $this->assertNull($profile->fresh()->team_id);
    }

    public function test_unbind_url_is_only_offered_for_bound_profiles(): void
    {
        $this->profile(['title' => 'A', 'team_id' => 1]);
        $this->profile(['title' => 'B']);
        $this->actingAs($this->manager());

        $rows = collect($this->getJson('/cp/directory?sort=title&order=asc')->json('data'))->keyBy('title');

        $this->assertNotNull($rows['A']['unbind_url']);
        $this->assertNull($rows['B']['unbind_url']);
        $this->assertNotNull($rows['B']['rebuild_url']);
    }

    public function test_unknown_profile_is_a_404(): void
    {
        $this->actingAs($this->manager());

        $this->post('/cp/directory/profiles/9999/unbind')->assertNotFound();
    }

    public function test_widget_counts_per_type(): void
    {
        config(['directory.collections' => ['choirs' => [], 'leaders' => []]]);
        $this->profile(['collection' => 'choirs']);
        $this->profile(['collection' => 'choirs', 'visibility' => 'private', 'lat' => null]);
        $this->profile(['collection' => 'choirs', 'lng' => null]);
        $this->profile(['collection' => 'leaders']);
        $this->actingAs($this->viewer());

        $component = (new DirectoryWidget)->component();

        $this->assertInstanceOf(VueComponent::class, $component);
        $props = $component->toArray()['props'];
        $this->assertSame('directory-widget', $component->toArray()['name']);

        $types = collect($props['types'])->keyBy('handle');
        $this->assertSame(3, $types['choirs']['total']);
        $this->assertSame(2, $types['choirs']['public']);
        $this->assertSame(1, $types['choirs']['private']);
        $this->assertSame(2, $types['choirs']['no_coordinates']);
        $this->assertSame(1, $types['leaders']['total']);
        $this->assertSame(0, $types['leaders']['no_coordinates']);
    }

    public function test_widget_is_hidden_without_view_permission(): void
    {
        $this->actingAs($this->userWith([]));

        $this->assertNull((new DirectoryWidget)->component());
    }

    public function test_widget_is_registered_with_statamic(): void
    {
        $this->assertSame('directory', DirectoryWidget::handle());
        $this->assertSame(DirectoryWidget::class, app('statamic.widgets')->get('directory'));
    }
}
