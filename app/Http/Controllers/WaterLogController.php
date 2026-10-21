<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WaterLogController extends Controller
{
    private function format($log)
    {
        return [
            'id' => $log->id,
            'amount_ml' => $log->water_ml,
            'log_date' => $log->log_date->toDateString(),
        ];
    }

    public function index(Request $request)
    {
        return $request->user()->dailyLogs()
            ->whereNotNull('water_ml')
            ->latest('log_date')
            ->get()
            ->map(fn ($log) => $this->format($log));
    }

    // "+" button: nagdadagdag sa total ng araw
    public function store(Request $request)
    {
        $data = $request->validate([
            'amount_ml' => 'required|integer|min:1|max:10000',
            'log_date' => 'required|date',
        ]);

        $log = $request->user()->dailyLogs()->firstOrNew(['log_date' => $data['log_date']]);
        $total = ($log->water_ml ?? 0) + $data['amount_ml'];

        if ($total > 10000) {
            throw ValidationException::withMessages([
                'amount_ml' => 'Enter an amount between 0.1 and 10 L.',
            ]);
        }

        $log->water_ml = $total;
        $log->save();

        return response()->json($this->format($log), 201);
    }

    // Edit: pinapalitan mismo ang total
    public function update(Request $request, $id)
    {
        $log = $request->user()->dailyLogs()->findOrFail($id);

        $data = $request->validate([
            'amount_ml' => 'required|integer|min:1|max:10000',
        ]);

        $log->update(['water_ml' => $data['amount_ml']]);

        return response()->json($this->format($log));
    }

    // Delete: binubura lang ang water, hindi ang buong araw
    public function destroy(Request $request, $id)
    {
        $request->user()->dailyLogs()->findOrFail($id)->update(['water_ml' => null]);

        return response()->json(['message' => 'Deleted']);
    }
}