<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class VitalSignLogController extends Controller
{
    private function rules()
    {
        return [
            'systolic' => 'nullable|integer|min:30|max:300|required_with:diastolic',
            'diastolic' => 'nullable|integer|min:20|max:200|required_with:systolic',
            'heart_rate' => 'nullable|integer|min:20|max:300',
            'temperature_c' => 'nullable|numeric|min:30|max:45',
            'oxygen_saturation' => 'nullable|integer|min:50|max:100',
            'log_date' => 'required|date',
        ];
    }

    public function index(Request $request)
    {
        return $request->user()->vitalSignLogs()->latest('log_date')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        return response()->json($request->user()->vitalSignLogs()->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $log = $request->user()->vitalSignLogs()->findOrFail($id);

        $log->update($request->validate($this->rules()));

        return response()->json($log);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->vitalSignLogs()->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}