<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\User;

class SubmissionPolicy
{
    public function submit(User $user, Quiz $quiz, Course $course): bool
    {
        if ($user->role !== 'student') {
            return false;
        }

        // URL の quiz が course に属していること
        if ($quiz->lesson->chapter->course_id !== $course->id) {
            return false;
        }

        // 修了済みの受講生も再受験できるよう completed も許可する
        $isEnrolled = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereIn('status', ['active', 'completed'])
            ->exists();

        if (! $isEnrolled) {
            return false;
        }

        // 合格済みの場合は再受験不可
        return ! $quiz->isPassedBy($user->id);
    }
}
