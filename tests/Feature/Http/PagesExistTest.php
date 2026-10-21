<?php

// Test Inertia memakai ensure_pages_exist=false, jadi berkas komponen Vue diperiksa di sini.
it('has a Vue component for every page the backend renders', function (string $page) {
    expect(file_exists(resource_path("js/Pages/{$page}.vue")))->toBeTrue();
})->with(['Auth/Login', 'Dashboard', 'Catalog']);
