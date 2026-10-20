<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MedicationController extends Controller
{
    private function rules()
    {
        return [
            'name' => 'required|string|max:150',
            'dosage' => 'nullable|string|max:100',
            'times' => 'nullable|array',
            'times.*' => 'date_format:H:i',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'food_instruction' => 'in:before_meal,after_meal,with_meal,anytime',
        ];
    }

    public function index(Request $request)
    {
        return $request->user()->medications()->latest('start_date')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        return response()->json($request->user()->medications()->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $medication = $request->user()->medications()->findOrFail($id);

        $medication->update($request->validate($this->rules()));

        return response()->json($medication);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->medications()->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}