<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WalkingLogController extends Controller
{
    private function rules()
    {
        return [
            'activity_type' => 'required|in:walking,jogging,running,workout',
            'steps' => 'nullable|integer|min:0|required_without:minutes',
            'minutes' => 'nullable|integer|min:1|max:1440|required_without:steps',
            'log_date' => 'required|date',
        ];
    }

    public function index(Request $request)
    {
        $request->validate(['date' => 'nullable|date']);

        $date = $request->query('date');

        $query = $request->user()->walkingLogs()->latest('log_date');

        if ($date) {
            $query->whereDate('log_date', $date);
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        return response()->json($request->user()->walkingLogs()->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $log = $request->user()->walkingLogs()->findOrFail($id);

        $log->update($request->validate($this->rules()));

        return response()->json($log);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->walkingLogs()->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}