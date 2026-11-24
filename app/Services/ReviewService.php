<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * コースレビューに関するビジネスロジックを集約するサービスクラス
 *
 * 投稿可否（修了済みの受講生か・投稿済みでないか）は ReviewPolicy で判定する。
 * ここでは Review 作成を担当し、同時送信などで Policy をすり抜けた重複は
 * DB の unique 制約違反を業務エラーに変換して返す。
 */
class ReviewService
{
    public function post(User $user, Course $course, array $data): Review
    {
        try {
            return Review::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new \Exception('既にこのコースのレビューを投稿済みです');
        }
    }
}
