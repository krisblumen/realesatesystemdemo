<?php

namespace Tests;

use Database\Seeders\ServiceTypeSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('service_types')) {
            $this->seed(ServiceTypeSeeder::class);
        }
    }
}
