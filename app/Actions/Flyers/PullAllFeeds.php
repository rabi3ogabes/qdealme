<?php

namespace App\Actions\Flyers;

use App\Enums\LicenseStatus;
use App\Enums\SourceType;
use App\Exceptions\FeedPullException;
use App\Models\FlyerSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Pulls the latest flyer from many approved store feeds in one go, for the
 * bulk actions, the dashboard button and the daily schedule. One store
 * failing never stops the others.
 */
class PullAllFeeds
{
    public function __construct(private readonly PullLatestFlyer $pull) {}

    /**
     * @param  Collection<int, FlyerSource>|null  $sources  null means every approved feed with a link
     * @return array{created: int, unchanged: int, failed: array<string, string>, flyer_ids: array<int, int>}
     */
    public function handle(?Collection $sources = null): array
    {
        $sources = EloquentCollection::make(($sources ?? static::pullable()->get())->all())
            ->filter(fn (FlyerSource $source) => $source->type === SourceType::RetailerFeed
                && $source->isApproved()
                && filled($source->feed_url))
            ->loadMissing('retailer');

        $summary = ['created' => 0, 'unchanged' => 0, 'failed' => [], 'flyer_ids' => []];

        foreach ($sources as $source) {
            $store = (string) ($source->retailer?->name_en ?? '#'.$source->id);

            try {
                $flyer = $this->pull->handle($source);
            } catch (FeedPullException $e) {
                $summary['failed'][$store] = $e->getMessage();

                continue;
            } catch (Throwable $e) {
                report($e);
                $summary['failed'][$store] = __('feed.errors.unexpected');

                continue;
            }

            if ($flyer === null) {
                $summary['unchanged']++;
            } else {
                $summary['created']++;
                $summary['flyer_ids'][] = $flyer->id;
            }
        }

        return $summary;
    }

    /**
     * Approved retailer feeds that have an official flyer link.
     */
    public static function pullable(): Builder
    {
        return FlyerSource::query()
            ->where('type', SourceType::RetailerFeed->value)
            ->where('license_status', LicenseStatus::Approved->value)
            ->whereNotNull('feed_url')
            ->where('feed_url', '!=', '');
    }
}
