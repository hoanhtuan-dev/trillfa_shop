<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test cho ProjectController (Studio \u2014 qu\u1ea3n l\u00fd d\u1ef1 \u00e1n Designer).
 *
 * Ph\u1ea1m vi: \u0111\u1ea7y \u0111\u1ee7 lu\u1ed3ng HTTP cho b\u1ea3ng "D\u1ef1 \u00e1n thi\u1ebft k\u1ebf":
 *  - CRUD d\u1ef1 \u00e1n (POST/GET/PUT/DELETE)
 *  - Workflow transitions (POST /transition) k\u00e8m reviewer gate
 *  - Attach/detach generation (POST /generations)
 *  - Scoping theo user hi\u1ec7n t\u1ea1i (Designer kh\u00f4ng th\u1ea5y d\u1ef1 \u00e1n c\u1ee7a ng\u01b0\u1eddi kh\u00e1c)
 *  - Auth + admin middleware (route /studio/* y\u00eau c\u1ea7u \u0111\u0103ng nh\u1eadp v\u00e0 l\u00e0 admin)
 */
class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_guest_is_redirected_from_projects_index(): void
    {
        $this->getJson('/studio/projects')->assertStatus(401);
    }

    public function test_customer_cannot_access_projects(): void
    {
        $customer = User::where('email', 'customer@trillfa.com')->first();
        $this->actingAs($customer);
        $this->getJson('/studio/projects')->assertStatus(403);
    }

    public function test_admin_can_list_their_projects(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        // Seed d\u1ef1 \u00e1n thu\u1ed9c admin (StudioProjectSeeder g\u00e1n cho super ho\u1eb7c admin).
        Project::factory()->create(['user_id' => $admin->id, 'name' => 'D\u1ef1 \u00e1n ri\u00eang A']);
        $this->actingAs($admin);

        $res = $this->getJson('/studio/projects');
        $res->assertOk();
        $res->assertJsonStructure(['items', 'statuses', 'archived']);
        $names = array_column($res->json('items'), 'name');
        $this->assertContains('D\u1ef1 \u00e1n ri\u00eang A', $names);
    }

    public function test_index_filters_archived_projects(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        Project::factory()->create(['user_id' => $admin->id, 'name' => 'Active A', 'archived' => false]);
        Project::factory()->create(['user_id' => $admin->id, 'name' => 'Old B', 'archived' => true]);
        $this->actingAs($admin);

        $active = $this->getJson('/studio/projects?archived=0')->assertOk();
        $activeNames = array_column($active->json('items'), 'name');
        $this->assertContains('Active A', $activeNames);
        $this->assertNotContains('Old B', $activeNames);

        $archived = $this->getJson('/studio/projects?archived=1')->assertOk();
        $archivedNames = array_column($archived->json('items'), 'name');
        $this->assertContains('Old B', $archivedNames);
        $this->assertNotContains('Active A', $archivedNames);
    }

    public function test_user_cannot_see_other_users_projects(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $super = User::where('email', 'tuan.ho.designer@gmail.com')->first();
        Project::factory()->create(['user_id' => $super->id, 'name' => 'Secret super']);
        $this->actingAs($admin);

        $res = $this->getJson('/studio/projects');
        $names = array_column($res->json('items'), 'name');
        $this->assertNotContains('Secret super', $names);
    }

    public function test_store_creates_project_in_draft_status(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $res = $this->postJson('/studio/projects/new', [
            'name' => 'BST M\u00f9a L\u1ea1nh 2026',
            'base_concept' => 'Phong c\u00e1ch old money, tone n\u00e2u + xanh r\u00eau.',
            'brief' => 'Ch\u1ee5p 4 g\u00f3c tr\u00ean n\u1ec1n be.',
            'deadline' => now()->addDays(10)->toDateString(),
            'tags' => ['bst', 'old-money'],
            'color' => '#559b78',
        ])->assertStatus(201);

        $res->assertJsonPath('status', Project::STATUS_DRAFT);
        $this->assertDatabaseHas('projects', ['name' => 'BST M\u00f9a L\u1ea1nh 2026', 'user_id' => $admin->id]);
    }

    public function test_store_requires_name(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $this->actingAs($admin);

        $this->postJson('/studio/projects/new', ['name' => ''])->assertStatus(422);
    }

    public function test_show_returns_project_with_generations_metadata(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $project = Project::factory()->create([
            'user_id' => $admin->id,
            'name' => 'Show A',
            'brief' => 'Y\u00eau c\u1ea7u ch\u1ee5p.',
        ]);
        $this->actingAs($admin);

        $res = $this->getJson('/studio/projects/'.$project->id);
        $res->assertOk();
        $res->assertJsonPath('id', $project->id);
        $res->assertJsonPath('name', 'Show A');
        $res->assertJsonStructure(['transitions', 'status_label', 'generations', 'assets']);
    }

    public function test_user_cannot_show_other_users_project(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $super = User::where('email', 'tuan.ho.designer@gmail.com')->first();
        $project = Project::factory()->create(['user_id' => $super->id]);
        $this->actingAs($admin);

        $this->getJson('/studio/projects/'.$project->id)->assertStatus(403);
    }

    public function test_update_changes_metadata_without_touching_status(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $project = Project::factory()->create([
            'user_id' => $admin->id,
            'status' => Project::STATUS_REVIEW,
            'name' => 'Old',
        ]);
        $this->actingAs($admin);

        $res = $this->putJson('/studio/projects/'.$project->id, [
            'name' => 'Renamed',
            'tags' => ['x', 'y'],
        ]);
        $res->assertOk();
        $res->assertJsonPath('name', 'Renamed');
        $res->assertJsonPath('status', Project::STATUS_REVIEW);
    }

    public function test_destroy_detaches_generations_and_keeps_them(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $project = Project::factory()->create(['user_id' => $admin->id]);
        $gen = Generation::factory()->create([
            'user_id' => $admin->id,
            'project_id' => $project->id,
            'media_url' => '/storage/sample.png',
        ]);
        $this->actingAs($admin);

        $this->deleteJson('/studio/projects/'.$project->id)->assertOk();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        // Generation ph\u1ea3i \u0111\u01b0\u1ee3c gi\u1eef l\u1ea1i (project_id = null).
        $this->assertDatabaseHas('generations', ['id' => $gen->id, 'project_id' => null]);
    }

    public function test_transition_moves_project_along_workflow(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $project = Project::factory()->create([
            'user_id' => $admin->id,
            'status' => Project::STATUS_IN_PROGRESS,
            'started_at' => now()->subDay(),
        ]);
        $this->actingAs($admin);

        $res = $this->postJson('/studio/projects/'.$project->id.'/transition', [
            'to' => Project::STATUS_REVIEW,
            'note' => 'G\u1eedi l\u00ean Super Admin duy\u1ec7t',
        ])->assertOk();

        $res->assertJsonPath('status', Project::STATUS_REVIEW);
        $res->assertJsonPath('status_label', fn ($label) => is_string($label) && $label !== '');
    }

    public function test_transition_returns_422_on_invalid_path(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $project = Project::factory()->create([
            'user_id' => $admin->id,
            'status' => Project::STATUS_DRAFT,
        ]);
        $this->actingAs($admin);

        // draft -> approved l\u00e0 b\u01b0\u1edbc nh\u1ea3y kh\u00f4ng h\u1ee3p l\u1ec7.
        $res = $this->postJson('/studio/projects/'.$project->id.'/transition', [
            'to' => Project::STATUS_APPROVED,
        ]);
        $res->assertStatus(422);
        $res->assertJsonPath('message', fn (string $msg) => $msg !== '' && str_contains($msg, 'approve') === false);
    }

    public function test_transition_reviewer_gate_blocks_admin_from_approving(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $project = Project::factory()->create([
            'user_id' => $admin->id,
            'status' => Project::STATUS_REVIEW,
        ]);
        $this->actingAs($admin);

        $res = $this->postJson('/studio/projects/'.$project->id.'/transition', [
            'to' => Project::STATUS_APPROVED,
        ]);
        $res->assertStatus(422);
        $res->assertJsonPath('message', fn (string $msg) => str_contains($msg, 'Super Admin'));
    }

    public function test_transition_reviewer_gate_allows_super_admin_to_approve(): void
    {
        $super = User::where('email', 'tuan.ho.designer@gmail.com')->first();
        $project = Project::factory()->create([
            'user_id' => $super->id,
            'status' => Project::STATUS_REVIEW,
            'completed_at' => null,
        ]);
        $this->actingAs($super);

        $res = $this->postJson('/studio/projects/'.$project->id.'/transition', [
            'to' => Project::STATUS_APPROVED,
            'note' => 'Ch\u1ed1t BST',
        ])->assertOk();

        $res->assertJsonPath('status', Project::STATUS_APPROVED);
        $res->assertJsonPath('completed_at', fn ($v) => filled($v));
    }

    public function test_attach_generation_links_output_to_project(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $project = Project::factory()->create(['user_id' => $admin->id]);
        $gen = Generation::factory()->create([
            'user_id' => $admin->id,
            'project_id' => null,
            'media_url' => '/storage/x.png',
        ]);
        $this->actingAs($admin);

        $res = $this->postJson('/studio/projects/'.$project->id.'/generations', [
            'generation_id' => $gen->id,
            'action' => 'attach',
        ])->assertOk();

        $res->assertJsonPath('generation.project_id', $project->id);
        $this->assertDatabaseHas('generations', ['id' => $gen->id, 'project_id' => $project->id]);
    }

    public function test_detach_generation_unlinks_output_from_project(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $project = Project::factory()->create(['user_id' => $admin->id]);
        $gen = Generation::factory()->create([
            'user_id' => $admin->id,
            'project_id' => $project->id,
            'media_url' => '/storage/y.png',
        ]);
        $this->actingAs($admin);

        $res = $this->postJson('/studio/projects/'.$project->id.'/generations', [
            'generation_id' => $gen->id,
            'action' => 'detach',
        ])->assertOk();

        $res->assertJsonPath('generation.project_id', null);
        $this->assertDatabaseHas('generations', ['id' => $gen->id, 'project_id' => null]);
    }

    public function test_cannot_attach_generation_owned_by_other_user(): void
    {
        $admin = User::where('email', 'admin@trillfa.com')->first();
        $super = User::where('email', 'tuan.ho.designer@gmail.com')->first();
        $project = Project::factory()->create(['user_id' => $admin->id]);
        // Generation thu\u1ed9c super, kh\u00f4ng thu\u1ed9c admin.
        $gen = Generation::factory()->create([
            'user_id' => $super->id,
            'project_id' => null,
            'media_url' => '/storage/z.png',
        ]);
        $this->actingAs($admin);

        $this->postJson('/studio/projects/'.$project->id.'/generations', [
            'generation_id' => $gen->id,
            'action' => 'attach',
        ])->assertStatus(403);
    }
}
