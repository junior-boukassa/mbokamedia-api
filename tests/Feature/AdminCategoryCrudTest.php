<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCategoryCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_update_and_delete_category(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);

        $category = Category::factory()->create();

        $updateResponse = $this->putJson("/api/admin/categories/{$category->id}", [
            'name' => 'Categorie mise a jour',
            'description' => 'Description mise a jour',
            'is_active' => false,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Categorie mise a jour')
            ->assertJsonPath('data.is_active', false);

        $deleteResponse = $this->deleteJson("/api/admin/categories/{$category->id}");

        $deleteResponse->assertOk();
        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_category_creation_requires_unique_name(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);

        Category::factory()->create(['name' => 'Unique Nom']);

        $response = $this->postJson('/api/admin/categories', [
            'name' => 'Unique Nom',
            'description' => 'Duplicat',
            'is_active' => true,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }
}
