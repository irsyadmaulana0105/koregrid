<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Unit dan Architecture sengaja tanpa framework: cepat dan murni.
// Pengaman database testing ada di tests/TestCase.php.
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
