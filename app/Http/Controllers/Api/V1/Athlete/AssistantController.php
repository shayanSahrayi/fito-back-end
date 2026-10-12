<?php

namespace App\Http\Controllers\Api\V1\Athlete;

use App\Domain\Ai\CoachAssistant;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends Controller
{
    public function storeConversation(Request $request, CoachAssistant $assistant): JsonResponse
    {
        return response()->json($assistant->athleteTurn($request->user(), $request->all()), 201);
    }
}
