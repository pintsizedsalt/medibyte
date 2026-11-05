<?php

namespace App\Http\Controllers;

use App\Support\TextField;
use Illuminate\Http\Request;

class MedicationController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const TEXT_FIELDS = [
        'name' => 'label',
        'dosage' => 'measure',
    ];

    private function rules()
    {
        return [
            'name' => TextField::rules('label', 'required', 150),
            'dosage' => TextField::rules('measure', 'nullable', 100),
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
        TextField::normalize($request, self::TEXT_FIELDS);

        $data = $request->validate($this->rules(), TextField::messages(self::TEXT_FIELDS));

        return response()->json($request->user()->medications()->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $medication = $request->user()->medications()->findOrFail($id);

        TextField::normalize($request, self::TEXT_FIELDS);

        $medication->update($request->validate($this->rules(), TextField::messages(self::TEXT_FIELDS)));

        return response()->json($medication);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->medications()->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
