<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SleepLogController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->sleepLogs()->latest('log_date')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'hours' => 'required|numeric|min:0|max:24',
            'log_date' => 'required|date',
        ]);

        return response()->json($request->user()->sleepLogs()->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $log = $request->user()->sleepLogs()->findOrFail($id);

        $log->update($request->validate([
            'hours' => 'required|numeric|min:0|max:24',
            'log_date' => 'required|date',
        ]));

        return response()->json($log);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->sleepLogs()->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
