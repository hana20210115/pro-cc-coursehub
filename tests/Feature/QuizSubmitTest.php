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

class QuizSubmitTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    private Quiz $quiz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create();
        $category = Category::factory()->create();
        $this->course = Course::factory()->published()->create(['category_id' => $category->id]);
        $chapter = Chapter::factory()->create(['course_id' => $this->course->id]);
        $lesson = Lesson::factory()->create(['chapter_id' => $chapter->id]);
        $this->quiz = Quiz::factory()->create(['lesson_id' => $lesson->id]);

        Enrollment::factory()->create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
        ]);
    }

    public function test_score_is_100_when_all_answers_are_correct(): void
    {
        $questions = $this->createQuestions(3);

        $answers = $questions->map(fn (array $q) => [
            'question_id' => $q['question']->id,
            'option_id' => $q['correct']->id,
        ])->all();

        $this->submit($answers)->assertRedirect($this->resultUrl());

        $this->assertLatestScore(100);
    }

    public function test_score_is_calculated_when_some_questions_are_unanswered(): void
    {
        $questions = $this->createQuestions(3);

        // 2問正解、1問は未回答（フォームと同じく question_id のみ送信され option_id がない）
        $answers = [
            ['question_id' => $questions[0]['question']->id, 'option_id' => $questions[0]['correct']->id],
            ['question_id' => $questions[1]['question']->id, 'option_id' => $questions[1]['correct']->id],
            ['question_id' => $questions[2]['question']->id],
        ];

        $this->submit($answers)->assertRedirect($this->resultUrl());

        $this->assertLatestScore(67);
    }

    public function test_score_is_0_when_all_questions_are_unanswered(): void
    {
        $questions = $this->createQuestions(3);

        $answers = $questions->map(fn (array $q) => [
            'question_id' => $q['question']->id,
        ])->all();

        $this->submit($answers)->assertRedirect($this->resultUrl());

        $this->assertLatestScore(0);
    }

    public function test_quiz_without_questions_does_not_cause_error(): void
    {
        $this->submit([])->assertRedirect($this->resultUrl());

        $this->assertLatestScore(0);
    }

    /**
     * 正解1つ・不正解1つの選択肢を持つ問題を作成する
     */
    private function createQuestions(int $count)
    {
        return collect(range(1, $count))->map(function (int $order) {
            $question = Question::factory()->create([
                'quiz_id' => $this->quiz->id,
                'order' => $order,
            ]);

            $correct = Option::factory()->correct()->create(['question_id' => $question->id]);
            Option::factory()->create(['question_id' => $question->id]);

            return ['question' => $question, 'correct' => $correct];
        });
    }

    private function submit(array $answers)
    {
        return $this->actingAs($this->student)
            ->post("/courses/{$this->course->id}/quizzes/{$this->quiz->id}/submit", [
                'answers' => $answers,
            ]);
    }

    private function resultUrl(): string
    {
        return "/courses/{$this->course->id}/quizzes/{$this->quiz->id}/result";
    }

    private function assertLatestScore(int $expected): void
    {
        $submission = Submission::where('user_id', $this->student->id)
            ->where('quiz_id', $this->quiz->id)
            ->latest()
            ->first();

        $this->assertNotNull($submission);
        $this->assertSame($expected, $submission->score);
    }
}
