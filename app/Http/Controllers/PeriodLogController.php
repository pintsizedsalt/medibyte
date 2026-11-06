<?php

namespace App\Http\Controllers;

use App\Support\CyclePrediction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PeriodLogController extends Controller
{
    private function rules()
    {
        return [
            'start_date' => 'required|date|before_or_equal:today',
            'end_date' => 'nullable|date|before_or_equal:today|after_or_equal:start_date',
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

        $prediction = $this->prediction($request->user());

        if ($prediction['last_period_start'] === null) {
            return response()->json(['message' => 'No period logs yet.']);
        }

        return response()->json($prediction);
    }

    /**
     * Everything the redesigned cycle page paints in one round trip: the
     * prediction, the period episodes that colour the calendar, and the daily
     * log entries for the month on screen.
     */
    public function overview(Request $request)
    {
        $this->ensureActive($request);

        $user = $request->user();

        $month = Carbon::parse($request->query('month', now()->toDateString()));
        $window = [$month->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()];

        $prediction = $this->prediction($user);
        $prediction['state'] = $user->cycle_paused ? 'paused' : $prediction['state'];
        $prediction['paused'] = (bool) $user->cycle_paused;
        $prediction['pause_reason'] = $user->cycle_pause_reason;

        return response()->json([
            'prediction' => $prediction,
            'period_logs' => $user->periodLogs()
                ->orderBy('start_date')
                ->get()
                ->map(fn ($log) => [
                    'id' => $log->id,
                    'start_date' => $log->start_date->toDateString(),
                    'end_date' => $log->end_date?->toDateString(),
                    'flow' => $log->flow,
                    'notes' => $log->notes,
                ])
                ->values(),
            'period_days' => $user->periodDays()
                ->whereBetween('log_date', $window)
                ->orderBy('log_date')
                ->get()
                ->map(fn ($day) => [
                    'id' => $day->id,
                    'log_date' => $day->log_date->toDateString(),
                    'flow' => $day->flow,
                    'pain' => $day->pain,
                    'symptoms' => $day->symptoms,
                    'mood' => $day->mood,
                    'discharge' => $day->discharge,
                    'bbt' => $day->bbt,
                    'lh_test' => $day->lh_test,
                    'pregnancy_test' => $day->pregnancy_test,
                    'medication' => $day->medication,
                    'sexual_activity' => $day->sexual_activity,
                    'protection' => $day->protection,
                    'notes' => $day->notes,
                ])
                ->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function prediction($user): array
    {
        return CyclePrediction::predict(
            $user->periodLogs()->orderBy('start_date')->pluck('start_date')->all(),
            (int) ($user->cycle_length ?? 28)
        );
    }
}
