<?php

use App\Models\User;

test('dashboard screen can be rendered for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Dashboard'));
});

test('unauthenticated users cannot access dashboard', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/login');
});
