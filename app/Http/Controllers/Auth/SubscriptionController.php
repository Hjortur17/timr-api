<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $subscription = Subscription::with('plan')
            ->where('company_id', $user->company_id)
            ->first();

        return response()->json([
            'data' => $subscription ? new SubscriptionResource($subscription) : null,
        ]);
    }
}
