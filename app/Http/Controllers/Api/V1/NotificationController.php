<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Feedback\Notifier;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request, Notifier $notifier): JsonResponse
    {
        return response()->json([
            'notifications' => $notifier->inbox($request->user()),
        ]);
    }

    public function read(Request $request, Notification $notification, Notifier $notifier): JsonResponse
    {
        return response()->json($notifier->markRead($request->user(), $notification));
    }
}
