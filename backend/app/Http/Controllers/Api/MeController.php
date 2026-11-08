<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /**
     * Prijavljen uporabnik z njegovimi stavbami in vlogami.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $memberships = $user->memberships()
            ->with('building')
            ->get()
            ->map(fn ($membership) => [
                'building' => [
                    'id' => $membership->building->id,
                    'name' => $membership->building->name,
                    'address' => $membership->building->address,
                    'apartments_count' => $membership->building->apartments()->count(),
                ],
                'role' => $membership->role->value,
                'role_label' => $membership->role->label(),
            ]);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'memberships' => $memberships,
            ],
        ]);
    }
}
