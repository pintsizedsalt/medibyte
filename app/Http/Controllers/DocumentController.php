<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->documents()->latest()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:prescription,lab_result,medical_certificate,other',
            'title' => 'required|string|max:150',
            'notes' => 'nullable|string|max:1000',
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->store('documents/'.$request->user()->id, 'local');

        $document = $request->user()->documents()->create([
            'type' => $data['type'],
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json($document, 201);
    }

    public function download(Request $request, $id)
    {
        $document = $request->user()->documents()->findOrFail($id);

        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function destroy(Request $request, $id)
    {
        $document = $request->user()->documents()->findOrFail($id);

        Storage::disk('local')->delete($document->path);
        $document->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
