<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WeightLogController extends Controller
{
    private function rules()
    {
        return [
            'weight_kg' => 'required|numeric|min:1|max:500',
            'height_cm' => 'required|numeric|min:30|max:300',
            'log_date' => 'required|date',
        ];
    }

    private function bmi($weightKg, $heightCm)
    {
        $meters = $heightCm / 100;

        return round($weightKg / ($meters * $meters), 2);
    }

    public function index(Request $request)
    {
        return $request->user()->weightLogs()->latest('log_date')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $data['bmi'] = $this->bmi($data['weight_kg'], $data['height_cm']);

        return response()->json($request->user()->weightLogs()->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $log = $request->user()->weightLogs()->findOrFail($id);

        $data = $request->validate($this->rules());
        $data['bmi'] = $this->bmi($data['weight_kg'], $data['height_cm']);

        $log->update($data);

        return response()->json($log);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->weightLogs()->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}