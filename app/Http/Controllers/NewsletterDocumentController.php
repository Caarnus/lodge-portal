<?php

namespace App\Http\Controllers;

use App\Models\Lodge;
use App\Models\NewsletterDocument;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NewsletterDocumentController extends Controller
{
    public function store(Request $request, Lodge $lodge)
    {
        $this->allowLodge($lodge, 'newsletters.manage');
        $file = $request->validate(['file' => 'required|file|max:10240'])['file'];
        if (strtolower($file->getClientOriginalExtension()) !== 'pdf' || $file->getMimeType() !== 'application/pdf' || file_get_contents($file->getRealPath(), false, null, 0, 5) !== '%PDF-') {
            throw ValidationException::withMessages(['file' => 'Upload a valid PDF document.']);
        }
        $path = $file->storeAs('newsletter-documents/' . $lodge->id, Str::uuid() . '.pdf', 'local');
        $document = NewsletterDocument::create(['lodge_id' => $lodge->id, 'uploaded_by' => $request->user()->id, 'original_name' => $file->getClientOriginalName(), 'storage_path' => $path, 'mime_type' => 'application/pdf', 'size' => $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath())]);
        Audit::record('newsletter.document_uploaded', $document, $lodge);

        return back();
    }

    public function destroy(Lodge $lodge, NewsletterDocument $document)
    {
        $this->allowLodge($lodge, 'newsletters.manage');
        abort_unless($document->lodge_id === $lodge->id, 404);
        abort_if($document->issueVersions()->whereIn('status', ['draft', 'published'])->exists(), 422, 'Document is in use.');
        $document->delete();
        Audit::record('newsletter.document_deleted', $document, $lodge);

        return back();
    }
}
