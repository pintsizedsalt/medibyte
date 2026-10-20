<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
        public function show(Request $request)
    {
        return response()->json($request->user()->profile);
    }

        public function store(Request $request)
    {
        $data = $request->validate([
            'sex' => 'required|in:female,male',
            'has_period' => 'boolean',
            'weight_goal' => 'nullable|numeric',
        ]);

        $profile = Profile::updateOrCreate(
            ['user_id' => $request->user()->id],
            $data
        );

        return response()->json($profile, 201);
    }
}
