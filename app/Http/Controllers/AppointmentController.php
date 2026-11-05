<?php

namespace App\Http\Controllers;

use App\Support\TextField;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const TEXT_FIELDS = [
        'doctor_name' => 'name',
        'specialty' => 'name',
        'hospital' => 'name',
        'contact_number' => 'phone',
        'office_address' => 'address',
    ];

    private function rules()
    {
        return [
            'doctor_name' => TextField::rules('name', 'required', 150),
            'specialty' => TextField::rules('name', 'nullable', 100),
            'contact_number' => TextField::rules('phone', 'nullable', 30),
            'office_address' => TextField::rules('address', 'nullable', 255),
            'hospital' => TextField::rules('name', 'nullable', 150),
            'appointment_at' => 'required|date',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:500',
            'status' => 'in:scheduled,done,cancelled',
        ];
    }

    public function index(Request $request)
    {
        return $request->user()->appointments()->orderBy('appointment_at')->get();
    }

    public function store(Request $request)
    {
        TextField::normalize($request, self::TEXT_FIELDS);

        $data = $request->validate($this->rules(), TextField::messages(self::TEXT_FIELDS));

        return response()->json($request->user()->appointments()->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $appointment = $request->user()->appointments()->findOrFail($id);

        TextField::normalize($request, self::TEXT_FIELDS);

        $appointment->update($request->validate($this->rules(), TextField::messages(self::TEXT_FIELDS)));

        return response()->json($appointment);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->appointments()->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
