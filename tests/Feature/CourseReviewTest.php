<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CourseReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create();
        $this->course = Course::factory()->published()->create();
    }

    public function test_completed_student_can_post_review(): void
    {
        $this->enroll($this->student, completed: true);

        $this->postReview($this->student, ['rating' => 4, 'comment' => 'とても分かりやすいコースでした'])
            ->assertRedirect(route('courses.show', $this->course))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reviews', [
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'rating' => 4,
            'comment' => 'とても分かりやすいコースでした',
        ]);
    }

    public function test_completed_student_can_post_review_without_comment(): void
    {
        $this->enroll($this->student, completed: true);

        $this->postReview($this->student, ['rating' => 5])
            ->assertRedirect(route('courses.show', $this->course))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('reviews', [
            'user_id' => $this->student->id,
            'rating' => 5,
            'comment' => null,
        ]);
    }

    public function test_active_student_cannot_post_review(): void
    {
        $this->enroll($this->student, completed: false);

        $this->postReview($this->student, ['rating' => 4])->assertForbidden();

        $this->assertSame(0, Review::count());
    }

    public function test_not_enrolled_student_cannot_post_review(): void
    {
        $this->postReview($this->student, ['rating' => 4])->assertForbidden();

        $this->assertSame(0, Review::count());
    }

    public function test_student_cannot_post_second_review(): void
    {
        $this->enroll($this->student, completed: true);
        Review::factory()->create(['user_id' => $this->student->id, 'course_id' => $this->course->id]);

        $this->postReview($this->student, ['rating' => 1])->assertForbidden();

        $this->assertSame(1, Review::count());
    }

    public function test_service_rejects_duplicate_review_even_if_policy_is_bypassed(): void
    {
        Review::factory()->create(['user_id' => $this->student->id, 'course_id' => $this->course->id]);

        $this->expectExceptionMessage('既にこのコースのレビューを投稿済みです');

        app(ReviewService::class)->post($this->student, $this->course, ['rating' => 3]);
    }

    public function test_coach_and_admin_cannot_post_review(): void
    {
        foreach ([User::factory()->coach()->create(), User::factory()->admin()->create()] as $user) {
            $this->postReview($user, ['rating' => 4])->assertForbidden();
        }

        $this->assertSame(0, Review::count());
    }

    public function test_rating_must_be_between_1_and_5(): void
    {
        $this->enroll($this->student, completed: true);

        foreach ([0, 6, null, 'abc'] as $rating) {
            $this->postReview($this->student, ['rating' => $rating])->assertSessionHasErrors('rating');
        }

        $this->assertSame(0, Review::count());
    }

    public function test_comment_must_be_at_most_1000_characters(): void
    {
        $this->enroll($this->student, completed: true);

        $this->postReview($this->student, ['rating' => 3, 'comment' => str_repeat('あ', 1001)])
            ->assertSessionHasErrors('comment');

        $this->assertSame(0, Review::count());
    }

    public function test_reviews_are_displayed_to_all_roles_without_form_for_coach_and_admin(): void
    {
        $reviewer = User::factory()->student()->create(['name' => 'レビュー太郎']);
        Review::factory()->create([
            'user_id' => $reviewer->id,
            'course_id' => $this->course->id,
            'rating' => 4,
            'comment' => '実践的な内容でした',
        ]);

        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();

        foreach ([$this->student, $coach, $admin] as $user) {
            $this->actingAs($user)->get(route('courses.show', $this->course))
                ->assertOk()
                ->assertSee('レビュー太郎')
                ->assertSee('実践的な内容でした')
                ->assertSee('4.0')
                ->assertDontSee($this->formAction(), false);
        }
    }

    public function test_review_form_is_shown_only_to_completed_student_without_review(): void
    {
        $url = route('courses.show', $this->course);

        // 未受講
        $this->actingAs($this->student)->get($url)
            ->assertDontSee($this->formAction(), false)
            ->assertSee('コースを修了するとレビューを投稿できます');

        // 受講中
        $enrollment = $this->enroll($this->student, completed: false);
        $this->actingAs($this->student)->get($url)->assertDontSee($this->formAction(), false);

        // 修了済み・未投稿
        $enrollment->update(['status' => 'completed', 'completed_at' => now()]);
        $this->actingAs($this->student)->get($url)->assertSee($this->formAction(), false);

        // 投稿済み
        Review::factory()->create(['user_id' => $this->student->id, 'course_id' => $this->course->id]);
        $this->actingAs($this->student)->get($url)
            ->assertDontSee($this->formAction(), false)
            ->assertSee('レビュー投稿済みです');
    }

    public function test_review_comment_is_escaped(): void
    {
        Review::factory()->create([
            'course_id' => $this->course->id,
            'comment' => '<script>alert(1)</script>',
        ]);

        $this->actingAs($this->student)->get(route('courses.show', $this->course))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    private function enroll(User $user, bool $completed): Enrollment
    {
        $factory = Enrollment::factory();

        return ($completed ? $factory->completed() : $factory)->create([
            'user_id' => $user->id,
            'course_id' => $this->course->id,
        ]);
    }

    private function postReview(User $user, array $data): TestResponse
    {
        return $this->actingAs($user)->from(route('courses.show', $this->course))
            ->post(route('courses.reviews.store', $this->course), $data);
    }

    // 投稿フォームの有無はフォームの action 属性で判定する
    private function formAction(): string
    {
        return 'action="'.route('courses.reviews.store', $this->course).'"';
    }
}
