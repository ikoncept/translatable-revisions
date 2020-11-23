<?php

namespace Infab\TranslatableRevisions\Tests;

use Infab\TranslatableRevisions\Tests\TestCase;

class AssetPublishTest extends TestCase
{
    /** @test **/
    public function it_can_publish_assets()
    {
        $this->artisan('vendor:publish', [
            '--provider' => 'Infab\TranslatableRevisions\TranslatableRevisionsServiceProvider',
        ])
        ->assertExitCode(0);
    }
}
