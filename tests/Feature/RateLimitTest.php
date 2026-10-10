<?php

namespace Tests\Feature;

use Tests\TestCase;

class RateLimitTest extends TestCase
{
    // SKPL §5.4.1: 120 permintaan/menit -> 429 RATE_LIMIT_EXCEEDED (envelope).
    public function test_lewat_batas_429_envelope(): void
    {
        $res = null;

        for ($i = 0; $i < 121; $i++) {
            $res = $this->getJson('/api/v1/ping');
        }

        $res->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'RATE_LIMIT_EXCEEDED');
    }

    public function test_dalam_batas_lolos(): void
    {
        $this->getJson('/api/v1/ping')->assertOk()->assertJsonPath('success', true);
    }
}
