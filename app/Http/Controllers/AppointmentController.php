<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    private function rules()
    {
        return [
            'doctor_name' => 'required|string|max:150',
            'specialty' => 'nullable|string|max:100',
            'contact_number' => 'nullable|string|max:30',
            'office_address' => 'nullable|string|max:255',
            'hospital' => 'nullable|string|max:150',
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
        $data = $request->validate($this->rules());

        return response()->json($request->user()->appointments()->create($data), 201);
    }

    public function update(Request $request, $id)
    {
        $appointment = $request->user()->appointments()->findOrFail($id);

        $appointment->update($request->validate($this->rules()));

        return response()->json($appointment);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->appointments()->findOrFail($id)->delete();

        return response()->json(['message' => 'Deleted']);
    }
}