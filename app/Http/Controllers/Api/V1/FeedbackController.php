<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feedback\StoreFeedbackRequest;
use App\Services\FeedbackService;
use Illuminate\Http\JsonResponse;

class FeedbackController extends Controller
{
    public function __construct(protected FeedbackService $feedback) {}

    public function store(StoreFeedbackRequest $request): JsonResponse
    {
        return $this->created(
            $this->feedback->submit($request->validated(), $request->user()),
            __('messages.feedback.sent'),
        );
    }
}
