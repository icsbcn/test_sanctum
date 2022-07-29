<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;

abstract class TestCase extends BaseTestCase {
    use CreatesApplication;
    use DatabaseTransactions;

    protected function setUp(): void {
        parent::setUp();
    }
}
