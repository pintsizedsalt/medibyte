<?php

namespace App\Http\Controllers;

use App\Models\MoodLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MoodLogController extends Controller
{
    private function rules(): array
    {
        return [
            'mood' => ['required', 'string', Rule::in(MoodLog::MOODS)],
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

    public function moods()
    {
        return response()->json(MoodLog::MOODS);
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
        $data = $request->validate($this->rules(), [], $this->attributes());
        $data['cried'] = $request->boolean('cried');
        $data['has_big_event'] = $request->boolean('has_big_event');

        return response()->json($request->user()->moodLogs()->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $log = $request->user()->moodLogs()->findOrFail($id);

        $data = $request->validate($this->rules(), [], $this->attributes());
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