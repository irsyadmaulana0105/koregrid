<?php

use Tests\Support\SafeRefreshDatabase;
use Tests\TestCase;

// Unit dan Architecture sengaja tanpa framework: cepat dan murni.
// SafeRefreshDatabase menolak berjalan di database selain *_testing.
pest()->extend(TestCase::class)->use(SafeRefreshDatabase::class)->in('Feature');
