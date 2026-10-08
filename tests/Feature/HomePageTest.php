<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the welcome page through Inertia', function () {
    $this->withoutVite();
    // Berkas komponen Vue divalidasi oleh build Vite; test ini fokus pada wiring middleware Inertia.
    config(['inertia.testing.ensure_pages_exist' => false]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('appName', config('app.name'))
            ->has('phpVersion'));
});
