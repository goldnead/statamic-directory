<?php

namespace Goldnead\Directory\Tests\Cp;

use Goldnead\Directory\Models\DirectoryProfile;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

class ListingTest extends CpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['directory.collections' => ['choirs' => [], 'leaders' => ['fields' => ['title' => 'name']]]]);
        $this->actingAs($this->viewer());
    }

    public function test_it_answers_the_listing_contract(): void
    {
        $this->profile(['title' => 'Alpha']);

        $response = $this->getJson('/cp/directory')->assertOk();

        $response->assertJsonStructure([
            'data' => [['id', 'title', 'collection', 'city', 'visibility', 'hidden', 'team', 'coordinates', 'updated_at', 'edit_url']],
            'meta' => ['columns', 'activeFilterBadges', 'current_page', 'last_page', 'per_page', 'from', 'to', 'total'],
        ]);
        $this->assertNotEmpty($response->json('meta.columns'));
        $this->assertCount(8, $response->json('meta.columns'));
    }

    public function test_rows_expose_team_and_coordinate_flags(): void
    {
        $this->profile(['title' => 'Bound', 'team_id' => 3, 'lat' => null, 'lng' => null, 'hidden' => true, 'visibility' => 'private']);

        $row = $this->getJson('/cp/directory')->json('data.0');

        $this->assertTrue($row['team']);
        $this->assertFalse($row['coordinates']);
        $this->assertTrue($row['hidden']);
        $this->assertSame('private', $row['visibility']);
    }

    public function test_search_matches_title_and_city(): void
    {
        $this->profile(['title' => 'Kammerchor', 'city' => 'Hamburg']);
        $this->profile(['title' => 'Jazzchor', 'city' => 'Köln']);

        $this->assertSame(['Kammerchor'], $this->titles('?search=kammer'));
        $this->assertSame(['Jazzchor'], $this->titles('?search=k%C3%B6ln'));
        $this->assertSame([], $this->titles('?search=%25'));
    }

    public function test_filter_by_type(): void
    {
        $this->profile(['title' => 'Chor', 'collection' => 'choirs']);
        $this->profile(['title' => 'Leiter', 'collection' => 'leaders']);

        $this->assertSame(['Leiter'], $this->titles($this->filters(['directory_type' => ['collection' => 'leaders']])));
    }

    public function test_filter_by_visibility(): void
    {
        $this->profile(['title' => 'Open']);
        $this->profile(['title' => 'Closed', 'visibility' => 'private']);

        $this->assertSame(['Open'], $this->titles($this->filters(['directory_visibility' => ['visibility' => 'public']])));
        $this->assertSame(['Closed'], $this->titles($this->filters(['directory_visibility' => ['visibility' => 'private']])));
    }

    public function test_filter_by_hidden(): void
    {
        $this->profile(['title' => 'Shown']);
        $this->profile(['title' => 'Hidden', 'hidden' => true]);

        $this->assertSame(['Hidden'], $this->titles($this->filters(['directory_hidden' => ['value' => 'yes']])));
        $this->assertSame(['Shown'], $this->titles($this->filters(['directory_hidden' => ['value' => 'no']])));
    }

    public function test_filter_by_team(): void
    {
        $this->profile(['title' => 'Free']);
        $this->profile(['title' => 'Bound', 'team_id' => 9]);

        $this->assertSame(['Bound'], $this->titles($this->filters(['directory_has_team' => ['value' => 'yes']])));
        $this->assertSame(['Free'], $this->titles($this->filters(['directory_has_team' => ['value' => 'no']])));
    }

    public function test_filter_by_coordinates(): void
    {
        $this->profile(['title' => 'Mapped']);
        $this->profile(['title' => 'Half', 'lng' => null]);
        $this->profile(['title' => 'None', 'lat' => null, 'lng' => null]);

        $this->assertSame(['Mapped'], $this->titles($this->filters(['directory_has_coordinates' => ['value' => 'yes']])));
        $this->assertEqualsCanonicalizing(['Half', 'None'], $this->titles($this->filters(['directory_has_coordinates' => ['value' => 'no']])));
    }

    public function test_filters_combine(): void
    {
        $this->profile(['title' => 'A', 'team_id' => 1]);
        $this->profile(['title' => 'B', 'team_id' => 2, 'hidden' => true]);

        $this->assertSame(['B'], $this->titles($this->filters([
            'directory_has_team' => ['value' => 'yes'],
            'directory_hidden' => ['value' => 'yes'],
        ])));
    }

    public function test_sort_is_whitelisted_and_applied(): void
    {
        $this->profile(['title' => 'B']);
        $this->profile(['title' => 'A']);

        $this->assertSame(['A', 'B'], $this->titles('?sort=title&order=asc'));
        $this->assertSame(['B', 'A'], $this->titles('?sort=title&order=desc'));
        $this->getJson('/cp/directory?sort=id)%20--&order=asc')->assertOk();
    }

    public function test_row_links_to_the_canonical_entry_edit_screen(): void
    {
        $collection = Collection::make('choirs')->save();
        $entry = Entry::make()->id('canon-1')->collection('choirs')->data(['title' => 'Canon']);
        $entry->save();

        $this->assertSame('Canon', DirectoryProfile::query()->first()?->title);

        $row = $this->getJson('/cp/directory')->json('data.0');

        $this->assertSame($entry->in('default')->editUrl(), $row['edit_url']);
        $this->assertStringContainsString('/cp/collections/choirs/entries/canon-1', $row['edit_url']);
    }
}
