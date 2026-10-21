<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MealLogController extends Controller
{
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
        $data = $request->validate([
            'meal_type' => 'required|in:breakfast,lunch,dinner,snack',
            'food_name' => 'required|string|max:255',
            'calories' => 'required|integer|min:0',
            'log_date' => 'required|date',
        ]);

        return response()->json($request->user()->mealLogs()->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $log = $request->user()->mealLogs()->findOrFail($id);

        $log->update($request->validate([
            'meal_type' => 'required|in:breakfast,lunch,dinner,snack',
            'food_name' => 'required|string|max:255',
            'calories' => 'required|integer|min:0',
            'log_date' => 'required|date',
        ]));

        return response()->json($log);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->mealLogs()->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}