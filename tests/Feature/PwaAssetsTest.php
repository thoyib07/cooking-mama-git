<?php

it('serves a valid web manifest', function () {
    $res = $this->get('/manifest.json');
    $res->assertStatus(200);
    $res->assertJsonStructure(['name', 'start_url', 'display', 'icons']);
});

it('serves the service worker at root scope', function () {
    $this->get('/sw.js')->assertStatus(200)
        ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8');
});
