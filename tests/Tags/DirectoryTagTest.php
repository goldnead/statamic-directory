<?php

namespace Goldnead\Directory\Tests\Tags;

use Goldnead\Directory\Tags\Directory;
use Goldnead\Directory\Tests\TestCase;
use Illuminate\Support\Facades\Log;
use Mockery;

class DirectoryTagTest extends TestCase
{
    public function test_invalid_tag_parameters_are_logged_and_render_no_results(): void
    {
        Log::spy();

        $tag = new Directory;
        $tag->setContext([]);
        $tag->setParameters(['per_page' => '0']);
        $result = $tag->search();

        $this->assertTrue($result['no_results']);
        $this->assertSame([], $result['profiles']);
        Log::shouldHaveReceived('warning')->with(
            'directory tag: invalid parameters',
            Mockery::on(fn (array $context) => array_key_exists('per_page', $context['errors'])),
        )->once();
    }
}
