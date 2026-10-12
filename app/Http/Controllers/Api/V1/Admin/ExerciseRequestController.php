<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Catalog\ExerciseRequestReview;
use App\Domain\Commerce\CommerceActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ApproveExerciseRequest;
use App\Http\Requests\Catalog\RejectExerciseRequest;
use App\Models\ExerciseRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExerciseRequestController extends Controller
{
    public function index(Request $request, CommerceActor $actor, ExerciseRequestReview $review): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json([
            'exercise_requests' => $review->forAdmin(),
        ]);
    }

    public function approve(ApproveExerciseRequest $request, ExerciseRequest $exerciseRequest, CommerceActor $actor, ExerciseRequestReview $review): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json($review->approve($request->user(), $exerciseRequest, $request->validated()));
    }

    public function reject(RejectExerciseRequest $request, ExerciseRequest $exerciseRequest, CommerceActor $actor, ExerciseRequestReview $review): JsonResponse
    {
        $actor->admin($request->user());

        return response()->json($review->reject($request->user(), $exerciseRequest, $request->validated()));
    }
}
