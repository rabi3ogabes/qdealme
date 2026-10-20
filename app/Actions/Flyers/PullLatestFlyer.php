<?php

namespace App\Actions\Flyers;

use App\Enums\SourceType;
use App\Exceptions\FeedPullException;
use App\Models\Flyer;
use App\Models\FlyerSource;
use App\Services\FlyerImageProcessor;
use App\Services\RetailerFeedClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pulls the current flyer from a store's approved feed and turns it into a
 * draft flyer for review. Nothing is published automatically.
 *
 * Returns null when the store has not changed its flyer since the last pull.
 */
class PullLatestFlyer
{
    public function __construct(
        private readonly RetailerFeedClient $client,
        private readonly CreateDraftFlyer $drafts,
    ) {}

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

            $paths = $this->storeFiles($source, $feed);
        } catch (FeedPullException $e) {
            $source->forceFill(['last_pull_error' => $e->getMessage()])->save();

            throw $e;
        }

        $source->forceFill(['last_pulled_at' => now(), 'last_pulled_hash' => $hash, 'last_pull_error' => null])->save();

        return $this->drafts->handle($source, $paths, [
            'title_en' => $feed['title_en'] ?? null,
            'title_ar' => $feed['title_ar'] ?? null,
            'valid_from' => $feed['valid_from'] ?? null,
            'valid_until' => $feed['valid_until'] ?? null,
        ]);
    }

    /**
     * @param  array{files: array<int, array{bytes: string, ext: string}>, title_en: ?string, title_ar: ?string, valid_from: ?Carbon, valid_until: ?Carbon}  $feed
     */
    private function storeFiles(FlyerSource $source, array $feed): array
    {
        $directory = 'uploads/flyers/feed-'.$source->id.'-'.Str::lower(Str::random(10));
        $paths = [];

        foreach ($feed['files'] as $i => $file) {
            $path = sprintf('%s/page-%03d.%s', $directory, $i + 1, $file['ext']);
            Storage::disk('local')->put($path, $file['bytes']);
            $paths[] = $path;
        }

        return $paths;
    }
}
