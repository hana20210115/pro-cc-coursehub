<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseProgressRateTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Course $course;

    private Chapter $chapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->create();
        $this->course = Course::factory()->create();
        $this->chapter = Chapter::factory()->create(['course_id' => $this->course->id]);
    }

    public function test_progress_rate_is_100_when_all_published_lessons_are_completed_with_unpublished_lessons(): void
    {
        $publishedLessons = $this->createLessons(3);
        Lesson::factory()->unpublished()->create(['chapter_id' => $this->chapter->id]);

        $this->completeLessons($publishedLessons);

        $this->assertSame(100, $this->course->getProgressRate($this->student->id));
    }

    public function test_progress_rate_is_calculated_from_published_lessons_when_partially_completed(): void
    {
        $publishedLessons = $this->createLessons(4);
        Lesson::factory()->unpublished()->create(['chapter_id' => $this->chapter->id]);

        // 4 公開レッスン中 1 件完了、1 件は未着手 → 25%
        $this->completeLessons($publishedLessons->take(1));
        LessonProgress::factory()->create([
            'user_id' => $this->student->id,
            'lesson_id' => $publishedLessons[1]->id,
            'status' => 'not_started',
            'completed_at' => null,
        ]);

        $this->assertSame(25, $this->course->getProgressRate($this->student->id));
    }

    public function test_progress_rate_is_0_when_course_has_no_lessons(): void
    {
        $this->assertSame(0, $this->course->getProgressRate($this->student->id));
    }

    private function createLessons(int $count): Collection
    {
        return Lesson::factory()->count($count)->create(['chapter_id' => $this->chapter->id]);
    }

    private function completeLessons(iterable $lessons): void
    {
        foreach ($lessons as $lesson) {
            LessonProgress::factory()->create([
                'user_id' => $this->student->id,
                'lesson_id' => $lesson->id,
                'status' => 'completed',
            ]);
        }
    }
}
