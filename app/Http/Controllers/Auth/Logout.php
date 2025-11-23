<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class Logout extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        // Set user as offline
        $user = $request->user();
        if ($user && $user->profile) {
            $user->profile->update(['online_status' => false]);
        }
        
        // Revoke all tokens for the user
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logout successful',
        ], 200);
    }
}
