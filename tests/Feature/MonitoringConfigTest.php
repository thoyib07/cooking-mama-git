<?php

it('runs without a monitoring DSN configured', function () {
    config()->set('services.sentry.dsn', null);
    $this->get('/')->assertStatus(200);
});
