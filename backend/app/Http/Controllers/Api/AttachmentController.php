<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Datoteke so dostopne samo prek API-ja, po preverjanju pravic do prijave.
     */
    public function show(Attachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment->attachable);

        return Storage::disk('local')->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
