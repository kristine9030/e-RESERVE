<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\Audit;
use App\Services\DocumentService;
use Illuminate\Http\Request;

/**
 * Serves one student document (a PDF, or a picture of a receipt for sponsors). Files live outside the web root, so a
 * document is readable only by SSC staff or by the student who uploaded it.
 */
class DocumentFileController extends Controller
{
    public function show(Request $request, Document $document, DocumentService $documents)
    {
        $user = $request->user();

        abort_unless($user->isAdmin() || (int) $document->user_id === (int) $user->id, 403, 'Forbidden.');

        $path = $documents->path((string) $document->stored_name);
        abort_if($path === null, 404, 'The file is no longer on the server.');

        if ($user->isAdmin()) {
            Audit::log('document.view', 'document', (string) $document->id, ['type' => $document->doc_type]);
        }

        $type     = config("ereserve.documents.{$document->doc_type}.short", 'document');
        // The type comes from the extension we chose when storing it (only these four can exist).
        $ext      = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime     = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$ext] ?? 'application/pdf';
        $ext      = $mime === 'application/pdf' ? 'pdf' : $ext;
        $filename = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $type . '-' . $document->user_id) . '.' . $ext;

        return response()->file($path, [
            'Content-Type'            => $mime,
            'Content-Disposition'     => ($request->boolean('download') ? 'attachment' : 'inline') . '; filename="' . $filename . '"',
            'Cache-Control'           => 'private, max-age=300',
            'X-Content-Type-Options'  => 'nosniff',
            // A picture never needs to run anything: lock the response down.
            'Content-Security-Policy' => $mime === 'application/pdf' ? "frame-ancestors 'self'" : "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; frame-ancestors 'self'",
        ]);
    }
}
