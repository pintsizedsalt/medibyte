<?php

namespace App\Http\Controllers;

use App\Models\MoodLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MoodLogController extends Controller
{
    private function rules(Request $request, ?MoodLog $log = null): array
    {
        $allowed = array_merge(
            MoodLog::MOODS,
            $request->user()->customMoods()->pluck('name')->all()
        );

        if ($log && $log->mood) {
            $allowed[] = $log->mood;
        }

        return [
            'mood' => [
                'required',
                'string',
                Rule::in(array_unique($allowed)),
            ],
            'intensity' => 'required|integer|min:1|max:5',
            'cried' => 'nullable|boolean',
            'has_big_event' => 'nullable|boolean',
            'event_note' => 'nullable|string|max:255',
            'impacts' => 'nullable|array',
            'impacts.*' => 'string|max:50',
            'reflection' => 'nullable|string|max:2000',
            'log_date' => 'required|date_format:Y-m-d|before_or_equal:today',
        ];
    }

    private function attributes(): array
    {
        return [
            'log_date' => 'date',
            'event_note' => 'event note',
            'has_big_event' => 'big event',
        ];
    }

    public function moods(Request $request)
    {
        $customMoods = $request->user()
            ? $request->user()->customMoods()->orderBy('name')->pluck('name')->all()
            : [];

        return response()->json(
            array_values(array_unique(array_merge(MoodLog::MOODS, $customMoods)))
        );
    }

    public function index(Request $request)
    {
        $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
        ], [], ['date' => 'date filter']);

        $date = $request->query('date');

        $query = $request->user()->moodLogs()->latest('log_date');

        if ($date) {
            $query->whereDate('log_date', $date);
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules($request), [], $this->attributes());
        $data['cried'] = $request->boolean('cried');
        $data['has_big_event'] = $request->boolean('has_big_event');

        return response()->json($request->user()->moodLogs()->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $log = $request->user()->moodLogs()->findOrFail($id);

        $data = $request->validate($this->rules($request, $log), [], $this->attributes());
        $data['cried'] = $request->boolean('cried');
        $data['has_big_event'] = $request->boolean('has_big_event');

        $log->update($data);

        return response()->json($log);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->moodLogs()->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
