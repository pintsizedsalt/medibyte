<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    private const ALLOWED_MIME_TYPES = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

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
            'file' => 'nullable|required_without:file_data|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'file_data' => 'nullable|required_without:file|string',
            'file_name' => 'nullable|required_with:file_data|string|max:255',
            'mime_type' => 'nullable|required_with:file_data|string|max:100',
        ]);

        $user = $request->user();

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('documents/'.$user->id, 'local');

            $document = $user->documents()->create([
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

        // Fallback for environments where PHP cannot create upload temp files.
        // The browser sends the file as a base64 JSON string instead.
        $bytes = base64_decode((string) $data['file_data'], true);

        if ($bytes === false) {
            throw ValidationException::withMessages([
                'file' => 'The file could not be read. Please choose it again.',
            ]);
        }

        $mime = (string) ($data['mime_type'] ?? '');
        $extension = self::ALLOWED_MIME_TYPES[$mime] ?? null;

        if ($extension === null) {
            throw ValidationException::withMessages([
                'file' => 'Only PDF, JPG, or PNG files are supported.',
            ]);
        }

        if (strlen($bytes) > 5 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'file' => 'The file must be 5 MB or smaller.',
            ]);
        }

        $originalName = basename((string) ($data['file_name'] ?? 'document.'.$extension));

        if (! Str::endsWith(Str::lower($originalName), '.'.$extension)) {
            $originalName .= '.'.$extension;
        }

        $path = 'documents/'.$user->id.'/'.Str::uuid().'.'.$extension;

        Storage::disk('local')->put($path, $bytes);

        $document = $user->documents()->create([
            'type' => $data['type'],
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'original_name' => Str::limit($originalName, 255, ''),
            'path' => $path,
            'mime_type' => $mime,
            'size' => strlen($bytes),
        ]);

        return response()->json($document, 201);
    }

    public function download(Request $request, $id)
    {
        $document = $request->user()->documents()->findOrFail($id);

        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->original_name);
    }

    public function preview(Request $request, $id)
    {
        $document = $request->user()->documents()->findOrFail($id);

        abort_unless(Storage::disk('local')->exists($document->path), 404);

        $mime = $document->mime_type ?: 'application/octet-stream';
        $headers = [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=3600',
        ];

        return Storage::disk('local')->response($document->path, $document->original_name, $headers);
    }

    public function destroy(Request $request, $id)
    {
        $document = $request->user()->documents()->findOrFail($id);

        Storage::disk('local')->delete($document->path);
        $document->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
