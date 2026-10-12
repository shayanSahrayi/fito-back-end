<?php

namespace App\Http\Controllers\Api\V1\Athlete;

use App\Domain\Feedback\FeedbackBox;
use App\Http\Controllers\Controller;
use App\Http\Requests\Feedback\OpenFeedbackRequest;
use App\Http\Requests\Feedback\ReplyFeedbackRequest;
use App\Models\FeedbackThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackThreadController extends Controller
{
    public function index(Request $request, FeedbackBox $threads): JsonResponse
    {
        return response()->json([
            'threads' => $threads->forAthlete($request->user(), $this->relationshipId($request)),
        ]);
    }

    public function store(OpenFeedbackRequest $request, FeedbackBox $threads): JsonResponse
    {
        return response()->json($threads->openAsAthlete($request->user(), $request->validated()), 201);
    }

    public function show(Request $request, FeedbackThread $feedbackThread, FeedbackBox $threads): JsonResponse
    {
        return response()->json($threads->showForAthlete($request->user(), $feedbackThread));
    }

    public function reply(ReplyFeedbackRequest $request, FeedbackThread $feedbackThread, FeedbackBox $threads): JsonResponse
    {
        return response()->json($threads->replyAsAthlete($request->user(), $feedbackThread, $request->validated()), 201);
    }

    public function resolve(Request $request, FeedbackThread $feedbackThread, FeedbackBox $threads): JsonResponse
    {
        return response()->json($threads->resolveAsAthlete($request->user(), $feedbackThread));
    }

    private function relationshipId(Request $request): ?int
    {
        $value = $request->query('coaching_relationship_id');

        return $value === null || $value === '' ? null : (int) $value;
    }
}
