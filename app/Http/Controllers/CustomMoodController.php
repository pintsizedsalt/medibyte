<?php

namespace App\Http\Controllers;

use App\Models\MoodLog;
use App\Support\TextField;
use Illuminate\Http\Request;

class CustomMoodController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            $request->user()->customMoods()->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        TextField::normalize($request, ['name' => 'name']);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                'regex:'.TextField::NAME,
                function ($attribute, $value, $fail) use ($request) {
                    $lower = mb_strtolower(trim($value));

                    $predefined = array_map('mb_strtolower', MoodLog::MOODS);
                    if (in_array($lower, $predefined, true)) {
                        $fail('This mood is already available as a predefined mood.');

                        return;
                    }

                    $exists = $request->user()->customMoods()
                        ->whereRaw('LOWER(name) = ?', [$lower])
                        ->exists();

                    if ($exists) {
                        $fail('You already have this custom mood.');
                    }
                },
            ],
        ], TextField::messages(['name' => 'name']));

        $mood = $request->user()->customMoods()->create($data);

        return response()->json($mood, 201);
    }

    public function update(Request $request, $id)
    {
        $mood = $request->user()->customMoods()->findOrFail($id);

        TextField::normalize($request, ['name' => 'name']);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                'regex:'.TextField::NAME,
                function ($attribute, $value, $fail) use ($request, $mood) {
                    $lower = mb_strtolower(trim($value));

                    $predefined = array_map('mb_strtolower', MoodLog::MOODS);
                    if (in_array($lower, $predefined, true)) {
                        $fail('This mood is already available as a predefined mood.');

                        return;
                    }

                    $exists = $request->user()->customMoods()
                        ->where('id', '!=', $mood->id)
                        ->whereRaw('LOWER(name) = ?', [$lower])
                        ->exists();

                    if ($exists) {
                        $fail('You already have this custom mood.');
                    }
                },
            ],
        ], TextField::messages(['name' => 'name']));

        $mood->update($data);

        return response()->json($mood);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->customMoods()->findOrFail($id)->delete();

        return response()->json(['message' => 'Custom mood deleted.']);
    }
}
