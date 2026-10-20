<?php

namespace App\Http\Controllers;

use App\Models\MoodLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MoodLogController extends Controller
{
    private function rules()
    {
        return [
            'mood' => ['required', Rule::in(MoodLog::MOODS)],
            'intensity' => 'required|integer|min:1|max:5',
            'cried' => 'boolean',
            'has_big_event' => 'boolean',
            'event_note' => 'nullable|string|max:255',
            'impacts' => 'nullable|array',
            'impacts.*' => 'string|max:50',
            'reflection' => 'nullable|string|max:2000',
            'log_date' => 'required|date',
        ];
    }

    public function moods()
    {
        return response()->json(MoodLog::MOODS);
    }

    public function index(Request $request)
    {
        return $request->user()->moodLogs()->latest('log_date')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        return response()->json($request->user()->moodLogs()->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $log = $request->user()->moodLogs()->findOrFail($id);

        $log->update($request->validate($this->rules()));

        return response()->json($log);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->moodLogs()->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}