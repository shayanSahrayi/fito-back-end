<?php

namespace App\Http\Controllers\Api\V1\Athlete;

use App\Domain\Body\BodyLog;
use App\Domain\Body\BodyPhotos;
use App\Http\Controllers\Controller;
use App\Http\Requests\Body\SaveBodyPhotoRequest;
use App\Http\Requests\Body\SaveObservationRequest;
use App\Models\BodyObservation;
use App\Models\BodyPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BodyController extends Controller
{
    public function index(Request $request, BodyLog $log): JsonResponse
    {
        return response()->json([
            'observations' => $log->forAthlete($request->user()),
        ]);
    }

    public function store(SaveObservationRequest $request, BodyLog $log): JsonResponse
    {
        return response()->json($log->recordAsAthlete($request->user(), $request->validated()), 201);
    }

    public function show(Request $request, BodyObservation $bodyObservation, BodyLog $log): JsonResponse
    {
        return response()->json($log->showForAthlete($request->user(), $bodyObservation));
    }

    public function compare(Request $request, BodyLog $log): JsonResponse
    {
        return response()->json($log->compareForAthlete(
            $request->user(),
            (int) $request->query('before_id'),
            (int) $request->query('after_id'),
        ));
    }

    public function series(Request $request, BodyLog $log): JsonResponse
    {
        return response()->json([
            'points' => $log->seriesForAthlete($request->user(), (string) $request->query('metric', '')),
        ]);
    }

    public function photos(Request $request, BodyPhotos $photos): JsonResponse
    {
        return response()->json([
            'photos' => $photos->forAthlete($request->user()),
        ]);
    }

    public function storePhoto(SaveBodyPhotoRequest $request, BodyPhotos $photos): JsonResponse
    {
        return response()->json($photos->storeAsAthlete(
            $request->user(),
            $request->file('photo'),
            $request->validated(),
        ), 201);
    }

    public function showPhoto(Request $request, BodyPhoto $bodyPhoto, BodyPhotos $photos): JsonResponse
    {
        return response()->json($photos->showForAthlete($request->user(), $bodyPhoto));
    }

    public function file(Request $request, BodyPhoto $bodyPhoto, BodyPhotos $photos): StreamedResponse
    {
        return Storage::disk('local')->response($photos->pathForAthlete($request->user(), $bodyPhoto));
    }
}
