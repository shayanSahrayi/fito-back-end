<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Domain\Commerce\CommerceActor;
use App\Domain\Workout\SessionLogger;
use App\Http\Controllers\Controller;
use App\Models\CoachingRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkoutSessionController extends Controller
{
    public function index(Request $request, CoachingRelationship $relationship, CommerceActor $actor, SessionLogger $sessions): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json([
            'sessions' => $sessions->forCoach($coach, $relationship),
        ]);
    }
}
