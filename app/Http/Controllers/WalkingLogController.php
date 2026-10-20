<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WalkingLogController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->walkingLogs()->latest('log_date')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'steps' => 'required|integer|min:0',
            'log_date' => 'required|date',
        ]);

        return response()->json($request->user()->walkingLogs()->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $log = $request->user()->walkingLogs()->findOrFail($id);

        $log->update($request->validate([
            'steps' => 'required|integer|min:0',
            'log_date' => 'required|date',
        ]));

        return response()->json($log);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->walkingLogs()->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}