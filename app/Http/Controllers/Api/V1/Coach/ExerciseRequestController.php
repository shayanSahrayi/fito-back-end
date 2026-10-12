<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Domain\Catalog\ExerciseRequestReview;
use App\Domain\Commerce\CommerceActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\SubmitExerciseRequest;
use Illuminate\Http\JsonResponse;

class ExerciseRequestController extends Controller
{
    public function store(SubmitExerciseRequest $request, CommerceActor $actor, ExerciseRequestReview $review): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($review->submit($coach, $request->validated()), 201);
    }
}
