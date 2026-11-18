<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseViewAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $coach;

    private User $student;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->coach = User::factory()->coach()->create();
        $this->student = User::factory()->student()->create();
        $this->category = Category::factory()->create();
    }

    public function test_admin_can_view_draft_course(): void
    {
        $course = $this->createCourse('draft');

        $response = $this->actingAs($this->admin)->get("/courses/{$course->id}");

        $response->assertStatus(200);
        $response->assertSee($course->title);
    }

    public function test_coach_can_view_own_draft_course(): void
    {
        $course = $this->createCourse('draft');

        $response = $this->actingAs($this->coach)->get("/courses/{$course->id}");

        $response->assertStatus(200);
        $response->assertSee($course->title);
    }

    public function test_student_can_view_published_course(): void
    {
        $course = $this->createCourse('published');

        $response = $this->actingAs($this->student)->get("/courses/{$course->id}");

        $response->assertStatus(200);
        $response->assertSee($course->title);
    }

    public function test_student_cannot_view_draft_course(): void
    {
        $course = $this->createCourse('draft');

        $response = $this->actingAs($this->student)->get("/courses/{$course->id}");

        $response->assertStatus(403);
    }

    public function test_student_cannot_view_archived_course(): void
    {
        $course = $this->createCourse('archived');

        $response = $this->actingAs($this->student)->get("/courses/{$course->id}");

        $response->assertStatus(403);
    }

    private function createCourse(string $status): Course
    {
        return Course::factory()->{$status}()->create([
            'user_id' => $this->coach->id,
            'category_id' => $this->category->id,
        ]);
    }
}
