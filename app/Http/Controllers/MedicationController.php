<?php

namespace App\Http\Controllers;

use App\Models\Medication;
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
            'dosage_amount' => ['nullable', 'numeric', 'min:0', 'max:99999', 'decimal:0,3'],
            'dosage_unit' => [
                'nullable',
                'string',
                'required_with:dosage_amount',
                'in:'.implode(',', Medication::UNITS),
            ],
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

        $data = $this->validated($request);

        return response()->json($request->user()->medications()->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $medication = $request->user()->medications()->findOrFail($id);

        TextField::normalize($request, self::TEXT_FIELDS);

        $medication->update($this->validated($request));

        return response()->json($medication);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->medications()->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }

    /**
     * Validation, plus the step that writes the amount and its unit into the
     * dosage text every other screen already reads. A request that carries
     * neither keeps the free-text dosage it sent, which is how the setup wizard
     * still records a dose.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate($this->rules(), TextField::messages(self::TEXT_FIELDS));

        if (array_key_exists('dosage_amount', $data) || array_key_exists('dosage_unit', $data)) {
            $data['dosage'] = Medication::dosageText(
                $data['dosage_amount'] ?? null,
                $data['dosage_unit'] ?? null,
            );
        }

        return $data;
    }
}
