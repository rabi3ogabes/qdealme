<?php

namespace App\Actions\Flyers;

use App\Enums\FlyerStatus;
use App\Enums\SourceType;
use App\Exceptions\FeedPullException;
use App\Jobs\ProcessFlyerUpload;
use App\Models\City;
use App\Models\Flyer;
use App\Models\FlyerSource;
use App\Services\FlyerImageProcessor;
use App\Services\RetailerFeedClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pulls the current flyer from a store's approved feed and turns it into a
 * draft flyer for review. Nothing is published automatically.
 *
 * Returns null when the store has not changed its flyer since the last pull.
 */
class PullLatestFlyer
{
    public function __construct(private readonly RetailerFeedClient $client) {}

    public function handle(FlyerSource $source): ?Flyer
    {
        $source->loadMissing('retailer');

        if (! $source->isApproved()) {
            throw FeedPullException::because('not_approved');
        }

        if ($source->type !== SourceType::RetailerFeed || blank($source->feed_url)) {
            throw FeedPullException::because('no_feed');
        }

        try {
            $feed = $this->client->fetch($source->retailer, $source->feed_url);

            if (! FlyerImageProcessor::canRasterizePdf() && collect($feed['files'])->contains('ext', 'pdf')) {
                throw FeedPullException::because('pdf_unsupported');
            }

            $hash = hash('sha256', implode("\0", array_column($feed['files'], 'bytes')));

            if ($hash === $source->last_pulled_hash) {
                $source->forceFill(['last_pulled_at' => now(), 'last_pull_error' => null])->save();

                return null;
            }

            $flyer = $this->createDraft($source, $feed);
        } catch (FeedPullException $e) {
            $source->forceFill(['last_pull_error' => $e->getMessage()])->save();

            throw $e;
        }

        $source->forceFill(['last_pulled_at' => now(), 'last_pulled_hash' => $hash, 'last_pull_error' => null])->save();

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

    /**
     * @param  array{files: array<int, array{bytes: string, ext: string}>, title_en: ?string, title_ar: ?string, valid_from: ?Carbon, valid_until: ?Carbon}  $feed
     */
    private function createDraft(FlyerSource $source, array $feed): Flyer
    {
        $retailer = $source->retailer;
        $directory = 'uploads/flyers/feed-'.$source->id.'-'.Str::lower(Str::random(10));
        $paths = [];

        foreach ($feed['files'] as $i => $file) {
            $path = sprintf('%s/page-%03d.%s', $directory, $i + 1, $file['ext']);
            Storage::disk('local')->put($path, $file['bytes']);
            $paths[] = $path;
        }

        $from = $feed['valid_from'] ?? today(config('qdealme.timezone'));
        $until = $feed['valid_until'] ?? $from->copy()->addDays(6);

        if ($until->lt($from)) {
            $until = $from->copy()->addDays(6);
        }

        return DB::transaction(function () use ($source, $retailer, $feed, $paths, $from, $until) {
            $flyer = Flyer::query()->create([
                'retailer_id' => $retailer->id,
                'flyer_source_id' => $source->id,
                'title_en' => $feed['title_en'] ?? __('feed.default_title', ['store' => $retailer->name_en], 'en'),
                'title_ar' => $feed['title_ar'] ?? __('feed.default_title', ['store' => $retailer->name_ar], 'ar'),
                'valid_from' => $from->toDateString(),
                'valid_until' => $until->toDateString(),
                'status' => FlyerStatus::Draft,
                'upload_files' => $paths,
            ]);

            $cities = $retailer->branches()->pluck('city_id')->unique()->values();
            $flyer->cities()->sync($cities->isNotEmpty() ? $cities : City::query()->active()->pluck('id'));

            return $flyer;
        });
    }
}
