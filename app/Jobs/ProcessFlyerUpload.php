<?php

namespace App\Jobs;

use App\Enums\FlyerStatus;
use App\Models\Flyer;
use App\Services\FlyerImageProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Turns an uploaded PDF (or set of images) into flyer pages with responsive
 * derivatives, then hands the flyer to the review queue.
 */
class ProcessFlyerUpload implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 2;

    public function __construct(public readonly int $flyerId) {}

    public function uniqueId(): string
    {
        return (string) $this->flyerId;
    }

    public function handle(FlyerImageProcessor $processor): void
    {
        $flyer = Flyer::query()->find($this->flyerId);

        if ($flyer === null) {
            return;
        }

        $flyer->forceFill(['status' => FlyerStatus::Processing, 'processing_error' => null])->save();

        try {
            $processor->process($flyer);

            $flyer->refresh()->forceFill(['status' => FlyerStatus::InReview])->save();
        } catch (Throwable $e) {
            $flyer->forceFill([
                'status' => FlyerStatus::Failed,
                'processing_error' => mb_substr($e->getMessage(), 0, 2000),
            ])->save();

            throw $e;
        }
    }
}
