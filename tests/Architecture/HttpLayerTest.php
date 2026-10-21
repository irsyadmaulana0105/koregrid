<?php

arch('the HTTP layer never touches the DB facade')
    ->expect('App\Http')
    ->not->toUse('Illuminate\Support\Facades\DB');
