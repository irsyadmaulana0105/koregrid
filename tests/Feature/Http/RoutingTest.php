<?php

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('redirects the root path to the dashboard', function () {
    $this->get('/')->assertRedirect('/dashboard');
});

it('sends guests who open the dashboard to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('shows the login page to guests', function () {
    $this->get('/login')->assertOk();
});
