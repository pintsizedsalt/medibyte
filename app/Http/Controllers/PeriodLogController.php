<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PeriodLogController extends Controller
{
    private function rules()
    {
        return [
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'flow' => 'nullable|in:light,medium,heavy',
            'notes' => 'nullable|string|max:500',
        ];
    }

    private function ensureActive(Request $request)
    {
        abort_unless(
            $request->user()->show_period_tracker,
            403,
            'Period tracker is not enabled for this profile.'
        );
    }

    public function index(Request $request)
    {
        $this->ensureActive($request);

        return $request->user()->periodLogs()->latest('start_date')->get();
    }

    public function store(Request $request)
    {
        $this->ensureActive($request);

        $data = $request->validate($this->rules());

        return response()->json($request->user()->periodLogs()->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $this->ensureActive($request);

        $log = $request->user()->periodLogs()->findOrFail($id);

        $log->update($request->validate($this->rules()));

        return response()->json($log);
    }

    public function destroy(Request $request, $id)
    {
        $this->ensureActive($request);

        $request->user()->periodLogs()->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function predict(Request $request)
    {
        $this->ensureActive($request);

        $starts = $request->user()->periodLogs()
            ->orderBy('start_date')
            ->get()
            ->pluck('start_date');

        if ($starts->isEmpty()) {
            return response()->json(['message' => 'No period logs yet.']);
        }

        $gaps = [];
        for ($i = 1; $i < $starts->count(); $i++) {
            $gaps[] = (int) abs($starts[$i - 1]->diffInDays($starts[$i]));
        }

        $fallback = $request->user()->cycle_length ?? 28;
        $average = count($gaps) ? (int) round(array_sum($gaps) / count($gaps)) : $fallback;

        $last = $starts->last();
        $next = $last->copy()->addDays($average);

        return response()->json([
            'last_period_start' => $last->toDateString(),
            'average_cycle_length' => $average,
            'based_on_cycles' => count($gaps),
            'cycle_day' => (int) abs($last->diffInDays(today())) + 1,
            'predicted_next_start' => $next->toDateString(),
            'days_until_next' => (int) today()->diffInDays($next, false),
        ]);
    }
}