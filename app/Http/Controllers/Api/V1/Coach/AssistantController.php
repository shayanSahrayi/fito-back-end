<?php

namespace App\Http\Controllers\Api\V1\Coach;

use App\Domain\Ai\CoachAssistant;
use App\Domain\Commerce\CommerceActor;
use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiGeneration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    public function saveSettings(Request $request, CommerceActor $actor, CoachAssistant $assistant): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($assistant->saveSettings($request->user(), $coach, $request->all()), 201);
    }

    public function storeKnowledge(Request $request, CommerceActor $actor, CoachAssistant $assistant): JsonResponse
    {
        $coach = $actor->coach($request->user());
        $file = $request->file('document');
        $contents = $file !== null ? (string) $file->get() : (string) $request->input('body', '');

        return response()->json($assistant->storeKnowledge(
            $request->user(),
            $coach,
            (string) $request->input('title', ''),
            $contents,
        ), 201);
    }

    public function storeConversation(Request $request, CommerceActor $actor, CoachAssistant $assistant): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($assistant->coachTurn($request->user(), $coach, $request->all(), null), 201);
    }

    public function showConversation(Request $request, AiConversation $aiConversation, CommerceActor $actor, CoachAssistant $assistant): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($assistant->show($coach, $aiConversation));
    }

    public function storeGeneration(Request $request, CommerceActor $actor, CoachAssistant $assistant): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($assistant->draft($request->user(), $coach, $request->all()), 201);
    }

    public function approve(Request $request, AiGeneration $aiGeneration, CommerceActor $actor, CoachAssistant $assistant): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($assistant->approve($request->user(), $coach, $aiGeneration));
    }

    public function reject(Request $request, AiGeneration $aiGeneration, CommerceActor $actor, CoachAssistant $assistant): JsonResponse
    {
        $coach = $actor->coach($request->user());

        return response()->json($assistant->reject($request->user(), $coach, $aiGeneration));
    }
}
