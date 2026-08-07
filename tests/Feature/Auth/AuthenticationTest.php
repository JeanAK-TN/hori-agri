<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using their phone number', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'phone' => $user->phone,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('phone numbers typed with spaces or dashes are normalized', function () {
    $user = User::factory()->create(['phone' => '90112233']);

    $response = $this->post(route('login.store'), [
        'phone' => '90 11-22.33',
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'phone' => $user->phone,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can not authenticate with an unknown phone number', function () {
    User::factory()->create(['phone' => '90112233']);

    $this->post(route('login.store'), [
        'phone' => '99887766',
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->phone, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'phone' => $user->phone,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
