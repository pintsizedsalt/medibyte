<?php

namespace App\Http\Controllers;

use App\Support\TextField;
use Illuminate\Http\Request;

class MealLogController extends Controller
{
    private const TEXT_FIELDS = ['food_name' => 'label'];

    private function rules(): array
    {
        return [
            'meal_type' => 'required|in:breakfast,lunch,dinner,snack',
            'meal_time' => 'nullable|date_format:H:i',
            'food_name' => TextField::rules('label', 'required', 255),
            'calories' => 'nullable|integer|min:0|max:10000',
            'log_date' => 'required|date',
        ];
    }

    public function index(Request $request)
    {
        $request->validate(['date' => 'nullable|date']);

        $date = $request->query('date');

        $query = $request->user()->mealLogs()->latest('log_date');

        if ($date) {
            $query->whereDate('log_date', $date);
        }

        return $query->get();
    }

    public function store(Request $request)
    {
        TextField::normalize($request, self::TEXT_FIELDS);

        $data = $request->validate($this->rules(), TextField::messages(self::TEXT_FIELDS));
        $data['calories'] = $data['calories'] ?? null;

        return response()->json($request->user()->mealLogs()->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $log = $request->user()->mealLogs()->findOrFail($id);

        TextField::normalize($request, self::TEXT_FIELDS);

        $data = $request->validate($this->rules(), TextField::messages(self::TEXT_FIELDS));
        $data['calories'] = $data['calories'] ?? null;

        $log->update($data);

        return response()->json($log);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->mealLogs()->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
