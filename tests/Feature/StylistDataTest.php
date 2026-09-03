<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StylistDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_stylist_data_endpoints_return_seeded_data_for_admin(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->assertNotNull($admin, 'admin user should be seeded');
        $this->assertTrue($admin->isAdmin());

        $this->actingAs($admin);

        $data = $this->getJson('/studio/stylist-data/data');
        $data->assertOk();
        $data->assertJsonCount(18, 'types');
        $data->assertJsonCount(8, 'questions');

        $types = $this->getJson('/studio/stylist/types');
        $types->assertOk();
        $types->assertJsonCount(18, 'types');
        $this->assertArrayHasKey('thumb', $types->json('types.0'));
    }
}
