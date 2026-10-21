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

    public function index(Request $request)
    {
        $request->validate(['date' => 'nullable|date']);

        $date = $request->query('date');

        $query = $request->user()->weightLogs()->latest('log_date');

        if ($date) {
            $query->whereDate('log_date', $date);
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        return response()->json($request->user()->weightLogs()->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $log = $request->user()->weightLogs()->findOrFail($id);

        $data = $request->validate($this->rules());

        $log->update($data);

        return response()->json($log);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->weightLogs()->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}