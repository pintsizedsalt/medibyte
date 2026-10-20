<?php

namespace App\Http\Controllers;
use App\Http\Controllers\WaterLogController;
use Illuminate\Http\Request;

class WaterLogController extends Controller
{
    public function index(Request $request)
{
    return $request->user()->waterLogs()->latest('log_date')->get();
}

    public function store(Request $request)
    {
        $data = $request->validate([
            'amount_ml' => 'required|integer|min:1',
            'log_date' => 'required|date',
        ]);

        $log = $request->user()->waterLogs()->create($data);

        return response()->json($log, 201);
    }

        public function update(Request $request, $id)
    {
        $log = $request->user()->waterLogs()->findOrFail($id);

        $data = $request->validate([
            'amount_ml' => 'required|integer|min:1',
            'log_date' => 'required|date',
        ]);

        $log->update($data);

        return response()->json($log);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->waterLogs()->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
