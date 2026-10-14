<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Monolog\Handler\NullHandler;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ninguna prueba sale a la red: una llamada que no esté simulada revienta.
        Http::preventStrayRequests();

        // El log de SIREB no se ensucia con lo que hacen las pruebas.
        config(['logging.channels.sireb' => ['driver' => 'monolog', 'handler' => NullHandler::class]]);
    }
}
