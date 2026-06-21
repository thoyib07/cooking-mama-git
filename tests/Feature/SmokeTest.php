<?php

it('boots the homepage', function () {
    $this->get('/')->assertStatus(200);
});
