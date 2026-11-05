<?php

namespace App\Http\Controllers;

use App\Support\TextField;
use Illuminate\Http\Request;

class SymptomLogController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const TEXT_FIELDS = [
        'illness_name' => 'label',
        'symptom' => 'label',
    ];

    private function rules()
    {
        return [
            'illness_name' => TextField::rules('label', 'nullable', 100),
            'symptom' => TextField::rules('label', 'required', 100),
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
        TextField::normalize($request, self::TEXT_FIELDS);

        $data = $request->validate($this->rules(), TextField::messages(self::TEXT_FIELDS));

        return response()->json($request->user()->symptomLogs()->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $log = $request->user()->symptomLogs()->findOrFail($id);

        TextField::normalize($request, self::TEXT_FIELDS);

        $log->update($request->validate($this->rules(), TextField::messages(self::TEXT_FIELDS)));

        return response()->json($log);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->symptomLogs()->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
