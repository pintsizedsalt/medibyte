<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;

class SleepLogController extends Controller
{
    private function rules()
    {
        return [
            'bedtime' => 'nullable|date_format:H:i|required_with:wake_time',
            'wake_time' => 'nullable|date_format:H:i|required_with:bedtime',
            'hours' => 'nullable|numeric|min:0|max:24|required_without:bedtime',
            'log_date' => 'required|date',
        ];
    }

    private function withHours(array $data)
    {
        // Only calculate hours from bedtime/wake_time if hours wasn't explicitly provided
        if (!empty($data['bedtime']) && !empty($data['wake_time']) && !isset($data['hours'])) {
            $bed = Carbon::createFromFormat('H:i', $data['bedtime']);
            $wake = Carbon::createFromFormat('H:i', $data['wake_time']);

            if ($wake->lessThanOrEqualTo($bed)) {
                $wake->addDay();
            }

            $data['hours'] = round($bed->diffInMinutes($wake) / 60, 2);
        }

        return $data;
    }

    public function index(Request $request)
    {
        $request->validate(['date' => 'nullable|date']);

        return $request->user()->sleepLogs()->latest('log_date')->get();
    }

    public function store(Request $request)
    {
        $data = $this->withHours($request->validate($this->rules()));

        return response()->json($request->user()->sleepLogs()->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $log = $request->user()->sleepLogs()->findOrFail($id);

        $log->update($this->withHours($request->validate($this->rules())));

        return response()->json($log);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->sleepLogs()->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}