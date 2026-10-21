<?php

namespace App\Actions\Flyers;

use App\Enums\FlyerStatus;
use App\Jobs\ProcessFlyerUpload;
use App\Models\City;
use App\Models\Flyer;
use App\Models\FlyerSource;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Turns page files already on the local disk into a draft flyer for review
 * and queues the page processing. Shared by feed pulls and post imports.
 */
class CreateDraftFlyer
{
    /**
     * @param  array<int, string>  $paths  page files in reading order
     * @param  array{title_en?: ?string, title_ar?: ?string, valid_from?: ?CarbonInterface, valid_until?: ?CarbonInterface, source_post_url?: ?string, content_hash?: ?string}  $details
     */
    public function handle(FlyerSource $source, array $paths, array $details = []): Flyer
    {
        $retailer = $source->retailer;
        $from = $details['valid_from'] ?? today(config('qdealme.timezone'));
        $until = $details['valid_until'] ?? $from->copy()->addDays(6);

        if ($until->lt($from)) {
            $until = $from->copy()->addDays(6);
        }

        $flyer = DB::transaction(function () use ($source, $retailer, $paths, $details, $from, $until) {
            $flyer = Flyer::query()->create([
                'retailer_id' => $retailer->id,
                'flyer_source_id' => $source->id,
                'title_en' => filled($details['title_en'] ?? null) ? $details['title_en'] : __('feed.default_title', ['store' => $retailer->name_en], 'en'),
                'title_ar' => filled($details['title_ar'] ?? null) ? $details['title_ar'] : __('feed.default_title', ['store' => $retailer->name_ar], 'ar'),
                'valid_from' => $from->toDateString(),
                'valid_until' => $until->toDateString(),
                'status' => FlyerStatus::Draft,
                'upload_files' => array_values($paths),
                'source_post_url' => $details['source_post_url'] ?? null,
                'content_hash' => $details['content_hash'] ?? null,
            ]);

            $cities = $retailer->branches()->pluck('city_id')->unique()->values();
            $flyer->cities()->sync($cities->isNotEmpty() ? $cities : City::query()->active()->pluck('id'));

            return $flyer;
        });

        try {
            ProcessFlyerUpload::dispatch($flyer->id);
        } catch (Throwable $e) {
            // With the sync queue (Vercel) a processing failure surfaces here.
            // The job has already marked the flyer as failed with the reason,
            // so staff see it on the draft; keep the draft and log the error.
            report($e);
        }

        return $flyer->refresh();
    }
}
