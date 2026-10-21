<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SymptomLogController extends Controller
{
    private function rules()
    {
        return [
            'illness_name' => 'nullable|string|max:100',
            'symptom' => 'required|string|max:100',
            'severity' => 'required|integer|min:1|max:5',
            'notes' => 'nullable|string|max:500',
            'log_date' => 'required|date',
        ];
    }

    public function index(Request $request)
    {
        $date = $request->query('date');

        $query = $request->user()->symptomLogs()->latest('log_date');

        if ($date) {
            $query->whereDate('log_date', $date);
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        return response()->json($request->user()->symptomLogs()->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $log = $request->user()->symptomLogs()->findOrFail($id);

        $log->update($request->validate($this->rules()));

        return response()->json($log);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->symptomLogs()->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}