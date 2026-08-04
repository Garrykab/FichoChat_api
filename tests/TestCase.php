<?php

namespace Tests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('roles') && Schema::hasTable('permissions')) {
            $this->seed(RolesAndPermissionsSeeder::class);
        }
    }

    /**
     * JWT garde l’utilisateur en mémoire entre requêtes HTTP du même test.
     * On force le reset à chaque changement de token.
     */
    public function withToken(string $token, string $type = 'Bearer'): static
    {
        $this->flushAuthState();

        return parent::withToken($token, $type);
    }

    public function withoutToken(): static
    {
        $this->flushAuthState();

        return parent::withoutToken();
    }

    protected function flushAuthState(): void
    {
        auth()->forgetGuards();

        try {
            JWTAuth::unsetToken();
        } catch (\Throwable) {
            // ignore si aucun token n’était chargé
        }
    }
}
