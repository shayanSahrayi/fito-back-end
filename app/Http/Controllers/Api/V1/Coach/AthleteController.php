<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Domain\Coaching\AthleteRegistrar;
use App\Domain\Coaching\InvitationActions;
use App\Domain\Coaching\RelationshipDirectory;
use App\Domain\Coaching\RelationshipEnder;
use App\Domain\Commerce\CommerceActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Coaching\EndRelationshipRequest;
use App\Http\Requests\Coaching\RegisterAthleteRequest;
use App\Models\AthleteInvitation;
use App\Models\CoachingRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AthleteController extends Controller
{
    public function store(RegisterAthleteRequest $request, CommerceActor $actor, AthleteRegistrar $registrar): JsonResponse
    {
        $coach = $actor->coach($request->user());
        $result = $registrar->register(
            $request->user(),
            $coach,
            $request->string('phone')->toString(),
            $request->string('display_name')->toString(),
            $request->string('national_code')->toString(),
        );

        return response()->json([
            'invitation' => $result['invitation'],
            'relationship' => $result['relationship'],
        ], $result['created'] ? 201 : 200);
    }

    public function index(Request $request, CommerceActor $actor, RelationshipDirectory $directory): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($directory->forCoach($coach));
    }

    public function revoke(Request $request, AthleteInvitation $invitation, CommerceActor $actor, InvitationActions $actions): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($actions->revoke($coach, $invitation));
    }

    public function end(EndRelationshipRequest $request, CoachingRelationship $relationship, CommerceActor $actor, RelationshipEnder $ender): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($ender->end(
            $request->user(),
            $coach,
            $relationship,
            $request->filled('end_reason') ? $request->string('end_reason')->toString() : null,
        ));
    }
}
