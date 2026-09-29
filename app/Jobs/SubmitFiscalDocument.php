<?php

namespace App\Jobs;

use App\Domain\Fiscal\FiscalService;
use App\Models\FiscalDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SubmitFiscalDocument implements ShouldQueue
{
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $documentId) {}

    public function handle(FiscalService $service): void
    {
        $document = FiscalDocument::query()->findOrFail($this->documentId);
        if ($document->state === 'queued') {
            $service->performSubmission($document);
        }
    }
}
