<?php

use App\Models\User;

it('blocks guests from the admin panel', function () {
    $this->get('/admin')->assertRedirect();
});

it('lets an admin view the recipe list', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin)->get('/admin/recipes')->assertStatus(200);
});
