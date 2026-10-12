<?php

namespace App\Http\Controllers\Api\V1\Athlete;

use App\Domain\Coaching\InvitationActions;
use App\Domain\Coaching\RelationshipDirectory;
use App\Domain\Identity\AccountStatus;
use App\Http\Controllers\Controller;
use App\Models\AthleteInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function index(Request $request, RelationshipDirectory $directory): JsonResponse
    {
        return response()->json($directory->invitations($request->user()));
    }

    public function relationships(Request $request, RelationshipDirectory $directory): JsonResponse
    {
        return response()->json($directory->forAthlete($request->user()));
    }

    public function decline(Request $request, AthleteInvitation $invitation, InvitationActions $actions): JsonResponse
    {
        AccountStatus::assertActive($request->user());

        return response()->json($actions->decline($request->user(), $invitation));
    }
}
