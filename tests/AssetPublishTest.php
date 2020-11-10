<?php

namespace Infab\PageModule\Tests;

use Infab\PageModule\Tests\TestCase;

class AssetPublishTest extends TestCase
{
    /** @test **/
    public function it_can_publish_assets()
    {
        $this->artisan('vendor:publish', [
            '--provider' => 'Infab\PageModule\PageModuleServiceProvider',
        ])
        ->assertExitCode(0);
    }
}
