<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Models\Course;
use App\Models\Review;
use App\Services\ReviewService;

class ReviewController extends Controller
{
    public function __construct(
        private ReviewService $reviewService
    ) {}

    public function store(StoreReviewRequest $request, Course $course)
    {
        $this->authorize('create', [Review::class, $course]);

        try {
            $this->reviewService->post(auth()->user(), $course, $request->validated());
        } catch (\Exception $e) {
            return redirect()->route('courses.show', $course)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('courses.show', $course)
            ->with('success', 'レビューを投稿しました。');
    }
}
