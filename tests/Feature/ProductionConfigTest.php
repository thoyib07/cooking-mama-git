<?php

it('parses DATABASE_URL into the pgsql connection', function () {
    config()->set('database.connections.pgsql.url', 'postgres://u:p@host:5432/dbname');
    expect(config('database.default'))->toBe('pgsql');
    expect(config('database.connections.pgsql.url'))->toContain('host:5432');
});
