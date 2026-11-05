<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentController extends Controller
{
    /**
     * Every uploadable type, with the bytes that must open the file. Checking
     * the signature matters because a base64 upload declares its own MIME
     * type, and that value is replayed as the response Content-Type when the
     * user previews the document.
     *
     * @var array<string, array{extension: string, signature: string}>
     */
    private const ALLOWED_MIME_TYPES = [
        'application/pdf' => ['extension' => 'pdf', 'signature' => '%PDF-'],
        'image/jpeg' => ['extension' => 'jpg', 'signature' => "\xff\xd8\xff"],
        'image/png' => ['extension' => 'png', 'signature' => "\x89PNG\r\n\x1a\n"],
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

        // The browser sends the bytes as base64 JSON, so PHP never needs to
        // create an upload temp file. This path works even when upload_tmp_dir
        // is not writable, which is what breaks multipart on shared hosting.
        $bytes = base64_decode((string) $data['file_data'], true);

        if ($bytes === false) {
            throw ValidationException::withMessages([
                'file' => 'The file could not be read. Please choose it again.',
            ]);
        }

        $mime = (string) ($data['mime_type'] ?? '');
        $allowed = self::ALLOWED_MIME_TYPES[$mime] ?? null;

        if ($allowed === null || ! str_starts_with($bytes, $allowed['signature'])) {
            throw ValidationException::withMessages([
                'file' => 'Only PDF, JPG, or PNG files are supported.',
            ]);
        }

        if (strlen($bytes) > 5 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'file' => 'The file must be 5 MB or smaller.',
            ]);
        }

        $extension = $allowed['extension'];
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

    public function update(Request $request, $id)
    {
        $document = $request->user()->documents()->findOrFail($id);

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:150',
            'type' => 'sometimes|required|in:prescription,lab_result,medical_certificate,other',
            'notes' => 'nullable|string|max:1000',
        ]);

        $document->update($data);

        return response()->json($document);
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
