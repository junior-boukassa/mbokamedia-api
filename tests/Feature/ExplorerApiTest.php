<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExplorerApiTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $attributes */
    protected function article(array $attributes = [], array $tags = []): Article
    {
        $article = Article::factory()->create([
            'status' => 'published',
            'published_at' => now()->subHour(),
            ...$attributes,
        ]);

        if ($tags !== []) {
            $article->tags()->sync(collect($tags)->map(fn (Tag $tag) => $tag->id)->all());
        }

        return $article;
    }

    protected function tag(string $name): Tag
    {
        return Tag::factory()->create(['name' => $name]);
    }

    /** @return array<int, array{name: string, slug: string, articles_count: int}> */
    protected function trending(string $query = ''): array
    {
        return $this->getJson('/api/public/tags/trending'.$query)->assertOk()->json('data');
    }

    // ─── Rubriques ──────────────────────────────────────────────────────────

    public function test_categories_are_sorted_by_position_then_name(): void
    {
        Category::factory()->create(['name' => 'Zeta', 'position' => 0]);
        Category::factory()->create(['name' => 'Education', 'position' => 2]);
        Category::factory()->create(['name' => 'Politique', 'position' => 1]);
        Category::factory()->create(['name' => 'Alpha', 'position' => 0]);

        $names = collect($this->getJson('/api/public/categories')->assertOk()->json('data'))->pluck('name')->all();

        $this->assertSame(['Alpha', 'Zeta', 'Politique', 'Education'], $names);
    }

    public function test_category_image_is_a_full_url_and_has_no_fallback_key_needed(): void
    {
        Category::factory()->create(['name' => 'Sport', 'image' => 'media/categories/sport.jpg']);

        $category = $this->getJson('/api/public/categories')->json('data.0');

        $this->assertStringEndsWith('/storage/media/categories/sport.jpg', $category['image']);
        $this->assertStringStartsWith('http', $category['image']);
        $this->assertNull($category['fallback_image']);
    }

    public function test_fallback_image_is_the_latest_published_article_image(): void
    {
        $category = Category::factory()->create(['name' => 'Culture', 'image' => null]);
        $this->article(['category_id' => $category->id, 'featured_image' => 'media/old.jpg', 'published_at' => now()->subDays(3)]);
        $this->article(['category_id' => $category->id, 'featured_image' => 'media/new.jpg', 'published_at' => now()->subHour()]);
        // Ni brouillon, ni futur, ni sans image : jamais retenus.
        $this->article(['category_id' => $category->id, 'featured_image' => 'media/draft.jpg', 'status' => 'draft']);
        $this->article(['category_id' => $category->id, 'featured_image' => 'media/future.jpg', 'published_at' => now()->addDay()]);
        $this->article(['category_id' => $category->id, 'featured_image' => null, 'published_at' => now()->subMinute()]);

        $data = $this->getJson('/api/public/categories')->json('data.0');

        $this->assertNull($data['image']);
        $this->assertStringEndsWith('media/new.jpg', $data['fallback_image']);
    }

    public function test_category_without_image_nor_article_has_a_null_fallback(): void
    {
        Category::factory()->create(['name' => 'Vide', 'image' => null]);

        $this->assertNull($this->getJson('/api/public/categories')->json('data.0.fallback_image'));
    }

    public function test_fallback_images_cost_a_fixed_number_of_queries(): void
    {
        foreach (range(1, 6) as $i) {
            $category = Category::factory()->create(['name' => "Rubrique {$i}", 'image' => null]);
            $this->article(['category_id' => $category->id, 'featured_image' => "media/{$i}.jpg"]);
        }

        \DB::enableQueryLog();
        $this->getJson('/api/public/categories')->assertOk();
        $queries = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        // Rubriques + une requête de repli, quel que soit leur nombre.
        $this->assertLessThanOrEqual(3, $queries);
    }

    public function test_admin_can_set_image_and_position_and_url_is_normalized(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $user->assignRole('editor');
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/admin/categories', [
            'name' => 'Economie',
            'image' => 'http://localhost/storage/media/categories/eco.jpg',
            'position' => 3,
        ])->assertCreated()->json('data.id');

        $category = Category::query()->findOrFail($id);
        $this->assertSame('media/categories/eco.jpg', $category->image);
        $this->assertSame(3, $category->position);

        $this->putJson("/api/admin/categories/{$id}", ['image' => null, 'position' => 0])->assertOk();
        $this->assertNull($category->fresh()->image);

        $this->putJson("/api/admin/categories/{$id}", ['position' => -1])->assertStatus(422);
    }

    // ─── Tendances ──────────────────────────────────────────────────────────

    public function test_a_tag_needs_two_articles_to_trend(): void
    {
        $solo = $this->tag('Solo');
        $duo = $this->tag('Duo');
        $this->article([], [$solo, $duo]);
        $this->article([], [$duo]);

        // Moins de 4 tags sur 7 jours : la fenêtre s'élargit, le seuil reste 2.
        $slugs = collect($this->trending())->pluck('slug')->all();

        $this->assertSame([$duo->slug], $slugs);
        $this->assertSame(2, $this->trending()[0]['articles_count']);
    }

    public function test_ranking_is_by_count_then_most_recent(): void
    {
        $big = $this->tag('Big');
        $recent = $this->tag('Recent');
        $older = $this->tag('Older');
        foreach (range(1, 3) as $i) {
            $this->article(['published_at' => now()->subHours($i + 5)], [$big]);
        }
        $this->article(['published_at' => now()->subHours(1)], [$recent]);
        $this->article(['published_at' => now()->subHours(2)], [$recent]);
        $this->article(['published_at' => now()->subHours(20)], [$older]);
        $this->article(['published_at' => now()->subHours(21)], [$older]);
        $filler = $this->tag('Filler');
        foreach (range(1, 2) as $i) {
            $this->article(['published_at' => now()->subDays(2)], [$filler]);
        }

        $this->assertSame(['big', 'recent', 'older', 'filler'], collect($this->trending())->pluck('slug')->all());
    }

    public function test_window_widens_to_thirty_days_when_fewer_than_four_trend(): void
    {
        $old = $this->tag('Ancien');
        $this->article(['published_at' => now()->subDays(20)], [$old]);
        $this->article(['published_at' => now()->subDays(21)], [$old]);

        $this->assertSame(['ancien'], collect($this->trending())->pluck('slug')->all());
    }

    public function test_window_stays_at_seven_days_when_four_or_more_trend(): void
    {
        foreach (['A', 'B', 'C', 'D'] as $name) {
            $tag = $this->tag($name);
            $this->article(['published_at' => now()->subDay()], [$tag]);
            $this->article(['published_at' => now()->subDays(2)], [$tag]);
        }
        $old = $this->tag('Ancien');
        $this->article(['published_at' => now()->subDays(20)], [$old]);
        $this->article(['published_at' => now()->subDays(21)], [$old]);

        $this->assertNotContains('ancien', collect($this->trending())->pluck('slug')->all());
    }

    public function test_nothing_trending_returns_an_empty_array(): void
    {
        $this->assertSame([], $this->trending());

        // Hors fenêtre de 30 jours, brouillons et articles futurs ne comptent pas.
        $tag = $this->tag('Rien');
        $this->article(['published_at' => now()->subDays(60)], [$tag]);
        $this->article(['published_at' => now()->subDays(61)], [$tag]);
        $this->article(['status' => 'draft'], [$tag]);
        $this->article(['published_at' => now()->addDay()], [$tag]);

        \Illuminate\Support\Facades\Cache::flush();
        $this->assertSame([], $this->trending());
    }

    public function test_limit_is_bounded_and_defaults_to_eight(): void
    {
        foreach (range(1, 14) as $i) {
            $tag = $this->tag("Tag {$i}");
            $this->article([], [$tag]);
            $this->article([], [$tag]);
        }

        $this->assertCount(8, $this->trending());
        $this->assertCount(12, $this->trending('?limit=50'));
        $this->assertCount(1, $this->trending('?limit=0'));
    }

    public function test_trending_is_cached_for_fifteen_minutes(): void
    {
        $tag = $this->tag('Cache');
        $this->article([], [$tag]);
        $this->article([], [$tag]);
        $this->assertCount(1, $this->trending());

        $other = $this->tag('Autre');
        $this->article([], [$other]);
        $this->article([], [$other]);
        $this->assertCount(1, $this->trending(), 'encore le résultat en cache');

        $this->travel(16)->minutes();
        $this->assertCount(2, $this->trending());
    }

    // ─── Recherche ──────────────────────────────────────────────────────────

    /** @return array<int, string> */
    protected function search(string $term): array
    {
        return collect($this->getJson('/api/public/articles?search='.urlencode($term))->assertOk()->json('data'))
            ->pluck('slug')->all();
    }

    public function test_search_finds_an_article_by_its_tag(): void
    {
        $article = $this->article(['title' => 'La sélection nationale se prépare', 'excerpt' => 'Entraînement.'], [$this->tag('Léopards')]);
        $this->article(['title' => 'Autre sujet', 'excerpt' => 'Rien.']);

        $this->assertSame([$article->slug], $this->search('Léopards'));
    }

    public function test_search_finds_an_article_by_its_category_name(): void
    {
        $category = Category::factory()->create(['name' => 'Diplomatie']);
        $article = $this->article(['category_id' => $category->id, 'title' => 'Sommet', 'excerpt' => 'Réunion.']);
        $this->article(['title' => 'Sans lien', 'excerpt' => 'Autre.']);

        $this->assertSame([$article->slug], $this->search('diplomatie'));
    }

    public function test_search_does_not_duplicate_an_article_matching_through_several_paths(): void
    {
        $category = Category::factory()->create(['name' => 'Football']);
        $article = $this->article(
            ['category_id' => $category->id, 'title' => 'Football en fête', 'excerpt' => 'Le football.'],
            [$this->tag('Football'), $this->tag('Football RDC')],
        );

        $this->assertSame([$article->slug], $this->search('football'));
    }

    public function test_search_still_matches_title_and_excerpt_and_ignores_the_body(): void
    {
        $byTitle = $this->article(['title' => 'Kinshasa bouge', 'excerpt' => 'x']);
        $byExcerpt = $this->article(['title' => 'Autre', 'excerpt' => 'À Kinshasa aussi']);
        $this->article(['title' => 'Corps seulement', 'excerpt' => 'x', 'content' => 'On parle de Kinshasa ici.']);

        $this->assertEqualsCanonicalizing([$byTitle->slug, $byExcerpt->slug], $this->search('Kinshasa'));
    }

    public function test_search_never_leaks_unpublished_articles_through_a_tag(): void
    {
        $tag = $this->tag('Secret');
        $this->article(['status' => 'draft'], [$tag]);

        $this->assertSame([], $this->search('Secret'));
    }
}
