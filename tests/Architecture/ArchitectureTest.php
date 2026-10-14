<?php

arch('domain layer does not depend on the HTTP layer')
    ->expect('App\Domain')
    ->not->toUse(['App\Http', 'Illuminate\Http']);

arch('domain layer does not depend on application or infrastructure')
    ->expect('App\Domain')
    ->not->toUse(['App\Application', 'App\Infrastructure']);

arch('application layer does not depend on the HTTP layer')
    ->expect('App\Application')
    ->not->toUse(['App\Http', 'Illuminate\Http']);

arch('domain enums are real enums')
    ->expect(['App\Domain\Identity\Enums', 'App\Domain\Ordering\Enums'])
    ->toBeEnums();

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();

arch('no dangerous functions')
    ->expect(['eval', 'exec', 'shell_exec', 'system', 'passthru', 'popen', 'proc_open'])
    ->not->toBeUsed();
