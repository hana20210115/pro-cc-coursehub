<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Option;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizRetakeTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Category $category;

    private Course $course;

    private Quiz $quiz;

    private Question $question;

    private Option $correctOption;

    private Option $wrongOption;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create();
        $this->category = Category::factory()->create();
        $this->course = $this->createCourse();

        $chapter = Chapter::factory()->create(['course_id' => $this->course->id]);
        $lesson = Lesson::factory()->create(['chapter_id' => $chapter->id]);
        $this->quiz = Quiz::factory()->create(['lesson_id' => $lesson->id, 'passing_score' => 70]);

        $this->question = Question::factory()->create(['quiz_id' => $this->quiz->id]);
        $this->correctOption = Option::factory()->correct()->create(['question_id' => $this->question->id]);
        $this->wrongOption = Option::factory()->create(['question_id' => $this->question->id]);

        Enrollment::factory()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
        ]);
    }

    public function test_student_can_take_quiz_for_the_first_time(): void
    {
        $this->submit($this->correctOption)->assertRedirect($this->resultUrl());

        $this->assertSame(1, $this->submissionCount());
    }

    public function test_failed_student_can_retake_quiz(): void
    {
        $this->createSubmission(0);

        $this->submit($this->correctOption)->assertRedirect($this->resultUrl());

        $this->assertSame(2, $this->submissionCount());
    }

    public function test_passed_student_is_redirected_to_result_when_trying_to_retake(): void
    {
        $this->createSubmission(100);

        $this->actingAs($this->student)->get($this->quizUrl())
            ->assertRedirect($this->resultUrl())
            ->assertSessionHas('success');
    }

    public function test_passed_student_cannot_submit_directly(): void
    {
        $this->createSubmission(100);

        $this->submit($this->correctOption)->assertForbidden();

        $this->assertSame(1, $this->submissionCount());
    }

    public function test_result_page_shows_all_submissions_newest_first(): void
    {
        $this->createSubmission(20, '2026-01-10 09:00:00');
        $this->createSubmission(40, '2026-01-11 10:30:00');
        $this->createSubmission(60, '2026-01-12 15:45:00');

        $response = $this->actingAs($this->student)->get($this->resultUrl());

        $response->assertOk();
        $response->assertSee('受験履歴');
        $response->assertSeeInOrder([
            '2026/01/12 15:45', '60%',
            '2026/01/11 10:30', '40%',
            '2026/01/10 09:00', '20%',
        ]);
    }

    public function test_result_page_hides_retake_button_when_passed(): void
    {
        $this->createSubmission(0, '2026-01-10 09:00:00');
        $this->createSubmission(100, '2026-01-11 09:00:00');

        $this->actingAs($this->student)->get($this->resultUrl())
            ->assertOk()
            ->assertDontSee('再受験する');
    }

    public function test_result_page_shows_retake_button_when_failed(): void
    {
        $this->createSubmission(0);

        $this->actingAs($this->student)->get($this->resultUrl())
            ->assertOk()
            ->assertSee('再受験する');
    }

    // 以下は SubmissionPolicy の境界条件

    public function test_student_with_score_equal_to_passing_score_cannot_retake_quiz(): void
    {
        $this->createSubmission($this->quiz->passing_score);

        $this->submit($this->correctOption)->assertForbidden();
    }

    public function test_student_with_completed_enrollment_can_retake_failed_quiz(): void
    {
        Enrollment::where('user_id', $this->student->id)->update(['status' => 'completed']);
        $this->createSubmission(0);

        $this->submit($this->correctOption)->assertRedirect($this->resultUrl());
    }

    public function test_student_without_enrollment_cannot_submit(): void
    {
        Enrollment::where('user_id', $this->student->id)->delete();

        $this->submit($this->correctOption)->assertForbidden();
    }

    public function test_cannot_submit_quiz_through_another_course_url(): void
    {
        $otherCourse = $this->createCourse();
        Enrollment::factory()->create([
            'user_id' => $this->student->id,
            'course_id' => $otherCourse->id,
        ]);

        $this->actingAs($this->student)
            ->post("/courses/{$otherCourse->id}/quizzes/{$this->quiz->id}/submit", [
                'answers' => [['question_id' => $this->question->id, 'option_id' => $this->correctOption->id]],
            ])
            ->assertForbidden();
    }

    private function createCourse(): Course
    {
        return Course::factory()->published()->create(['category_id' => $this->category->id]);
    }

    private function createSubmission(int $score, string $submittedAt = 'now'): Submission
    {
        return Submission::factory()->create([
            'user_id' => $this->student->id,
            'quiz_id' => $this->quiz->id,
            'score' => $score,
            'submitted_at' => $submittedAt,
        ]);
    }

    private function submit(Option $option)
    {
        return $this->actingAs($this->student)
            ->post("/courses/{$this->course->id}/quizzes/{$this->quiz->id}/submit", [
                'answers' => [['question_id' => $this->question->id, 'option_id' => $option->id]],
            ]);
    }

    private function submissionCount(): int
    {
        return Submission::where('user_id', $this->student->id)
            ->where('quiz_id', $this->quiz->id)
            ->count();
    }

    private function quizUrl(): string
    {
        return "/courses/{$this->course->id}/quizzes/{$this->quiz->id}";
    }

    private function resultUrl(): string
    {
        return "{$this->quizUrl()}/result";
    }
}
