<?php

namespace App\Http\Controllers;

use App\Models\PeriodDay;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class PeriodDayController extends Controller
{
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

        $query = $request->user()->periodDays();

        if ($request->filled('month')) {
            $month = Carbon::parse($request->query('month'));

            $query->whereBetween('log_date', [$month->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()]);
        }

        return $query->orderBy('log_date')->get();
    }

    public function store(Request $request)
    {
        $this->ensureActive($request);

        $data = $request->validate($this->rules());

        $day = $request->user()->periodDays()->updateOrCreate(
            ['log_date' => $data['log_date']],
            $data
        );

        return response()->json($day, 201);
    }

    public function update(Request $request, PeriodDay $periodDay)
    {
        $this->ensureActive($request);

        abort_unless($periodDay->user_id === $request->user()->id, 404);

        $periodDay->update($request->validate($this->rules()));

        return response()->json($periodDay);
    }

    public function destroy(Request $request, PeriodDay $periodDay)
    {
        $this->ensureActive($request);

        abort_unless($periodDay->user_id === $request->user()->id, 404);

        $periodDay->delete();

        return response()->json(['message' => 'Deleted']);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'log_date' => 'required|date|before_or_equal:today',
            'flow' => ['nullable', Rule::in(PeriodDay::FLOWS)],
            'pain' => 'nullable|integer|between:0,10',
            'symptoms' => 'nullable|array',
            'symptoms.*' => 'string|max:50',
            'mood' => ['nullable', 'string', 'max:60'],
            'discharge' => ['nullable', Rule::in(PeriodDay::DISCHARGES)],
            'bbt' => 'nullable|numeric|between:30,110',
            'lh_test' => ['nullable', Rule::in(PeriodDay::LH_RESULTS)],
            'pregnancy_test' => ['nullable', Rule::in(PeriodDay::TEST_RESULTS)],
            'medication' => 'nullable|string|max:150',
            'sexual_activity' => 'nullable|boolean',
            'protection' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
