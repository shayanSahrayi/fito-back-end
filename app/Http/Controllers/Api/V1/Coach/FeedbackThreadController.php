<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Domain\Commerce\CommerceActor;
use App\Domain\Feedback\FeedbackBox;
use App\Http\Controllers\Controller;
use App\Http\Requests\Feedback\OpenFeedbackRequest;
use App\Http\Requests\Feedback\ReplyFeedbackRequest;
use App\Models\FeedbackThread;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackThreadController extends Controller
{
    public function index(Request $request, CommerceActor $actor, FeedbackBox $threads): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json([
            'threads' => $threads->forCoach($coach, $this->relationshipId($request)),
        ]);
    }

    public function store(OpenFeedbackRequest $request, CommerceActor $actor, FeedbackBox $threads): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($threads->openAsCoach($request->user(), $coach, $request->validated()), 201);
    }

    public function show(Request $request, FeedbackThread $feedbackThread, CommerceActor $actor, FeedbackBox $threads): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($threads->showForCoach($coach, $feedbackThread));
    }

    public function reply(
        ReplyFeedbackRequest $request,
        FeedbackThread $feedbackThread,
        CommerceActor $actor,
        FeedbackBox $threads,
    ): JsonResponse {
        $coach = $actor->coach($request->user());

        return response()->json($threads->replyAsCoach($request->user(), $coach, $feedbackThread, $request->validated()), 201);
    }

    public function resolve(Request $request, FeedbackThread $feedbackThread, CommerceActor $actor, FeedbackBox $threads): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($threads->resolveAsCoach($request->user(), $coach, $feedbackThread));
    }

    private function relationshipId(Request $request): ?int
    {
        $value = $request->query('coaching_relationship_id');

        return $value === null || $value === '' ? null : (int) $value;
    }
}
