<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Domain\Body\BodyLog;
use App\Domain\Body\BodyPhotos;
use App\Domain\Commerce\CommerceActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Body\SaveBodyPhotoRequest;
use App\Http\Requests\Body\SaveObservationRequest;
use App\Models\BodyObservation;
use App\Models\BodyPhoto;
use App\Models\CoachingRelationship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BodyController extends Controller
{
    public function index(Request $request, CoachingRelationship $relationship, CommerceActor $actor, BodyLog $log): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json([
            'observations' => $log->forCoach($coach, $relationship),
        ]);
    }

    public function store(
        SaveObservationRequest $request,
        CoachingRelationship $relationship,
        CommerceActor $actor,
        BodyLog $log,
    ): JsonResponse {
        $coach = $actor->coach($request->user());

        return response()->json($log->recordAsCoach($request->user(), $coach, $relationship, $request->validated()), 201);
    }

    public function show(Request $request, BodyObservation $bodyObservation, CommerceActor $actor, BodyLog $log): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($log->showForCoach($coach, $bodyObservation));
    }

    public function compare(Request $request, CommerceActor $actor, BodyLog $log): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($log->compareForCoach(
            $coach,
            (int) $request->query('before_id'),
            (int) $request->query('after_id'),
        ));
    }

    public function series(Request $request, CoachingRelationship $relationship, CommerceActor $actor, BodyLog $log): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json([
            'points' => $log->seriesForCoach($coach, $relationship, (string) $request->query('metric', '')),
        ]);
    }

    public function photos(Request $request, CoachingRelationship $relationship, CommerceActor $actor, BodyPhotos $photos): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json([
            'photos' => $photos->forCoach($coach, $relationship),
        ]);
    }

    public function storePhoto(
        SaveBodyPhotoRequest $request,
        CoachingRelationship $relationship,
        CommerceActor $actor,
        BodyPhotos $photos,
    ): JsonResponse {
        $coach = $actor->coach($request->user());

        return response()->json($photos->storeAsCoach(
            $request->user(),
            $coach,
            $relationship,
            $request->file('photo'),
            $request->validated(),
        ), 201);
    }

    public function showPhoto(Request $request, BodyPhoto $bodyPhoto, CommerceActor $actor, BodyPhotos $photos): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($photos->showForCoach($coach, $bodyPhoto));
    }

    public function file(Request $request, BodyPhoto $bodyPhoto, CommerceActor $actor, BodyPhotos $photos): StreamedResponse
    {
        $coach = $actor->coach($request->user());

        return Storage::disk('local')->response($photos->pathForCoach($coach, $bodyPhoto));
    }
}
