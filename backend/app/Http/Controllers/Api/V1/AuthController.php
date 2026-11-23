<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CurrentUserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): CurrentUserResource
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $attempt = ['email' => $credentials['email'], 'password' => $credentials['password'], 'is_active' => true];

        if (! Auth::guard('web')->attempt($attempt, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $request->session()->regenerate();

        return new CurrentUserResource($request->user()->load(['defaultRoom.floor.building', 'department']));
    }

    public function me(Request $request): CurrentUserResource
    {
        return new CurrentUserResource($request->user()->load(['defaultRoom.floor.building', 'department']));
    }

    public function logout(Request $request): Response|JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
