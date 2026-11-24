<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ReviewPolicy
{
    public function create(User $user, Course $course): Response
    {
        if (! $user->isStudent()) {
            return Response::deny('レビューは受講生のみ投稿できます。');
        }

        // 受講を修了した受講生のみ投稿可能
        $isCompleted = $user->enrollments()
            ->where('course_id', $course->id)
            ->where('status', 'completed')
            ->exists();

        if (! $isCompleted) {
            return Response::deny('コースを修了するとレビューを投稿できます。');
        }

        // 1コースにつき1人1件
        if ($user->reviews()->where('course_id', $course->id)->exists()) {
            return Response::deny('レビュー投稿済みです。');
        }

        return Response::allow();
    }
}
