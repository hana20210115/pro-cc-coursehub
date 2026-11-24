<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;

    private User $student;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coach = User::factory()->create(['role' => 'coach']);
        $this->student = User::factory()->create(['role' => 'student']);
        $this->category = Category::factory()->create();
    }

    public function test_student_can_view_course_list(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'status' => 'published',
        ]);

        $response = $this->actingAs($this->student)->get('/courses');

        $response->assertStatus(200);
        $response->assertSee($course->title);
    }

    public function test_student_can_view_published_course(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'status' => 'published',
        ]);

        $response = $this->actingAs($this->student)->get("/courses/{$course->id}");

        $response->assertStatus(200);
        $response->assertSee($course->title);
    }

    public function test_coach_can_create_course(): void
    {
        $tags = Tag::factory()->count(2)->create();

        $response = $this->actingAs($this->coach)->post('/coach/courses', [
            'title' => 'テストコース',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'draft',
            'tags' => $tags->pluck('id')->toArray(),
        ]);

        $response->assertRedirect('/coach/courses');
        $this->assertDatabaseHas('courses', [
            'title' => 'テストコース',
            'user_id' => $this->coach->id,
        ]);
    }

    public function test_coach_can_create_course_with_image(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->coach)->post('/coach/courses', $this->coursePayload([
            'image' => UploadedFile::fake()->image('thumbnail.jpg'),
        ]));

        $response->assertRedirect('/coach/courses');
        $response->assertSessionHasNoErrors();

        $course = Course::where('title', 'テストコース')->firstOrFail();
        $this->assertNotNull($course->image_path);
        $this->assertStringStartsWith('courses/', $course->image_path);
        Storage::disk('public')->assertExists($course->image_path);
    }

    public function test_published_at_is_set_when_course_is_created_as_published(): void
    {
        // DB の timestamp は秒精度のため秒単位で固定する
        $this->freezeSecond();

        $response = $this->actingAs($this->coach)->post('/coach/courses', $this->coursePayload([
            'status' => 'published',
        ]));

        $response->assertRedirect('/coach/courses');

        $course = Course::where('title', 'テストコース')->firstOrFail();
        $this->assertSame('published', $course->status);
        $this->assertTrue($course->published_at->equalTo(now()));
    }

    public function test_published_at_is_null_when_course_is_created_as_draft(): void
    {
        $response = $this->actingAs($this->coach)->post('/coach/courses', $this->coursePayload([
            'status' => 'draft',
        ]));

        $response->assertRedirect('/coach/courses');

        $course = Course::where('title', 'テストコース')->firstOrFail();
        $this->assertNull($course->published_at);
    }

    public function test_selected_tags_are_attached_when_course_is_created(): void
    {
        $tags = Tag::factory()->count(2)->create();
        $otherTag = Tag::factory()->create();

        $response = $this->actingAs($this->coach)->post('/coach/courses', $this->coursePayload([
            'tags' => $tags->pluck('id')->toArray(),
        ]));

        $response->assertRedirect('/coach/courses');

        $course = Course::where('title', 'テストコース')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            $tags->pluck('id')->toArray(),
            $course->tags()->pluck('tags.id')->toArray()
        );
        $this->assertDatabaseMissing('course_tag', [
            'course_id' => $course->id,
            'tag_id' => $otherTag->id,
        ]);
    }

    public function test_initial_chapter_is_created_with_course(): void
    {
        $response = $this->actingAs($this->coach)->post('/coach/courses', $this->coursePayload());

        $response->assertRedirect('/coach/courses');

        $course = Course::where('title', 'テストコース')->firstOrFail();
        $this->assertSame(1, $course->chapters()->count());
        $this->assertDatabaseHas('chapters', [
            'course_id' => $course->id,
            'title' => 'はじめに',
            'order' => 1,
        ]);
    }

    public function test_coach_cannot_create_course_with_duplicate_title(): void
    {
        Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'title' => 'テストコース',
        ]);

        $response = $this->actingAs($this->coach)
            ->from('/coach/courses/create')
            ->post('/coach/courses', $this->coursePayload());

        $response->assertRedirect('/coach/courses/create');
        $response->assertSessionHasErrors(['title' => '同じタイトルのコースが既に存在します。']);
        $this->assertSame(1, Course::where('title', 'テストコース')->count());
    }

    public function test_coach_can_update_course(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->actingAs($this->coach)->put("/coach/courses/{$course->id}", [
            'title' => '更新されたタイトル',
            'category_id' => $this->category->id,
            'description' => '更新された説明文です。',
            'difficulty' => 'intermediate',
            'status' => 'published',
        ]);

        $response->assertRedirect('/coach/courses');
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'title' => '更新されたタイトル',
        ]);
    }

    public function test_coach_can_delete_own_course(): void
    {
        $course = Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->actingAs($this->coach)->delete("/coach/courses/{$course->id}");

        $response->assertRedirect('/coach/courses');
        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }

    public function test_coach_cannot_delete_other_coaches_course(): void
    {
        $otherCoach = User::factory()->create(['role' => 'coach']);
        $course = Course::factory()->create([
            'user_id' => $otherCoach->id,
            'category_id' => $this->category->id,
        ]);

        $response = $this->actingAs($this->coach)->delete("/coach/courses/{$course->id}");

        $response->assertStatus(403);
    }

    public function test_student_cannot_create_course(): void
    {
        $response = $this->actingAs($this->student)->get('/coach/courses/create');

        $response->assertStatus(403);
    }

    public function test_course_list_can_be_filtered_by_category(): void
    {
        $otherCategory = Category::factory()->create();

        Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'status' => 'published',
            'title' => 'カテゴリAのコース',
        ]);

        Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $otherCategory->id,
            'status' => 'published',
            'title' => 'カテゴリBのコース',
        ]);

        $response = $this->actingAs($this->student)
            ->get("/courses?category={$this->category->id}");

        $response->assertStatus(200);
        $response->assertSee('カテゴリAのコース');
        $response->assertDontSee('カテゴリBのコース');
    }

    public function test_course_list_can_be_searched(): void
    {
        Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'status' => 'published',
            'title' => 'Laravel入門',
        ]);

        Course::factory()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
            'status' => 'published',
            'title' => 'React基礎',
        ]);

        $response = $this->actingAs($this->student)
            ->get('/courses?search=Laravel');

        $response->assertStatus(200);
        $response->assertSee('Laravel入門');
        $response->assertDontSee('React基礎');
    }

    private function coursePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'テストコース',
            'category_id' => $this->category->id,
            'description' => 'テストコースの説明文です。',
            'difficulty' => 'beginner',
            'status' => 'draft',
        ], $overrides);
    }
}
