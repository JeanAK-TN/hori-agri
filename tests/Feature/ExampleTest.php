<?php

test('home redirects to the dashboard', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('dashboard', absolute: false));
});
