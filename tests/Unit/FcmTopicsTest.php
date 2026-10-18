<?php

namespace Tests\Unit;

use App\Support\FcmTopics;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FcmTopicsTest extends TestCase
{
    public function test_category_topic_is_prefixed(): void
    {
        $this->assertSame('category-politique', FcmTopics::category('politique'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function slugs(): array
    {
        // Les mêmes cas que test/push_topics_service_test.dart côté application :
        // les deux doivent produire exactement la même chaîne.
        return [
            'simple' => ['politique', 'category-politique'],
            'espace et esperluette' => ['société & culture', 'category-soci-t----culture'],
            'barre oblique' => ['a/b c', 'category-a-b-c'],
            'caractères autorisés conservés' => ['a-b_c.d~e%f', 'category-a-b_c.d~e%f'],
            'apostrophe' => ["l'actu", 'category-l-actu'],
        ];
    }

    #[DataProvider('slugs')]
    public function test_category_topic_matches_the_mobile_app_rule(string $slug, string $expected): void
    {
        $this->assertSame($expected, FcmTopics::category($slug));
    }

    public function test_every_generated_topic_is_valid(): void
    {
        foreach (self::slugs() as [$slug]) {
            $this->assertTrue(FcmTopics::isValid(FcmTopics::category($slug)), $slug);
        }
    }

    public function test_condition_with_two_topics(): void
    {
        $this->assertSame(
            "'all-users' in topics || 'category-sport' in topics",
            FcmTopics::condition(['all-users', 'category-sport']),
        );
    }

    public function test_condition_removes_duplicates(): void
    {
        $this->assertSame(
            "'all-users' in topics",
            FcmTopics::condition(['all-users', 'all-users']),
        );
    }

    public function test_condition_rejects_a_name_that_could_break_the_expression(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FcmTopics::condition(["all-users' in topics || 'x"]);
    }

    public function test_condition_rejects_an_empty_list(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FcmTopics::condition([]);
    }

    public function test_condition_rejects_more_than_five_topics(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FcmTopics::condition(['a', 'b', 'c', 'd', 'e', 'f']);
    }

    public function test_condition_accepts_exactly_five_topics(): void
    {
        $condition = FcmTopics::condition(['a', 'b', 'c', 'd', 'e']);

        $this->assertSame(4, substr_count($condition, ' || '));
    }
}
