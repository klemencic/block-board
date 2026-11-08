<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    /**
     * Povabljeni uporabnik si z žetonom iz e-pošte nastavi geslo.
     */
    public function accept(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        $status = Password::broker('invites')->reset(
            $data,
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'email_verified_at' => now()])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => 'Povezava ni veljavna ali je potekla. Lastnika prosite za novo povabilo.',
            ]);
        }

        return response()->json(['message' => 'Geslo je nastavljeno. Zdaj se lahko prijavite.']);
    }
}
