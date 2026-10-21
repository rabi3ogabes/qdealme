<?php

namespace App\Actions\Flyers;

use App\Enums\LicenseStatus;
use App\Enums\SourceType;
use App\Exceptions\FeedPullException;
use App\Models\FlyerSource;
use App\Services\SocialPostsClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Pulls new flyers from many approved stores in one go, for the bulk actions,
 * the dashboard button and the daily schedule: each store's official flyer
 * link, and each store's official Facebook or Instagram page when Meta's API
 * is set up. One store failing never stops the others.
 */
class PullAllFeeds
{
    public function __construct(
        private readonly PullLatestFlyer $pull,
        private readonly PullSocialPosts $social,
    ) {}

    /**
     * @param  Collection<int, FlyerSource>|null  $sources  null means every approved source that can be pulled
     * @return array{created: int, unchanged: int, failed: array<string, string>, flyer_ids: array<int, int>}
     */
    public function handle(?Collection $sources = null): array
    {
        $sources = EloquentCollection::make(($sources ?? static::pullable()->get())->all())
            ->filter(fn (FlyerSource $source) => static::isPullable($source))
            ->loadMissing('retailer');

        $summary = ['created' => 0, 'unchanged' => 0, 'failed' => [], 'flyer_ids' => []];

        foreach ($sources as $source) {
            $store = (string) ($source->retailer?->name_en ?? '#'.$source->id);

            try {
                $flyers = $source->type === SourceType::SocialMedia
                    ? $this->social->handle($source)
                    : array_filter([$this->pull->handle($source)]);
            } catch (FeedPullException $e) {
                $summary['failed'][$store] = $e->getMessage();

                continue;
            } catch (Throwable $e) {
                report($e);
                $summary['failed'][$store] = __('feed.errors.unexpected');

                continue;
            }

            if ($flyers === []) {
                $summary['unchanged']++;
            }

            foreach ($flyers as $flyer) {
                $summary['created']++;
                $summary['flyer_ids'][] = $flyer->id;
            }
        }

        return $summary;
    }

    /**
     * Approved retailer feeds with an official flyer link, plus approved
     * social media sources with an official page when Meta's API is set up.
     */
    public static function pullable(): Builder
    {
        return FlyerSource::query()
            ->where('license_status', LicenseStatus::Approved->value)
            ->where(function (Builder $query): void {
                $query->where(fn (Builder $query) => $query
                    ->where('type', SourceType::RetailerFeed->value)
                    ->whereNotNull('feed_url')->where('feed_url', '!=', ''));

                if (SocialPostsClient::isConfigured()) {
                    $query->orWhere(fn (Builder $query) => $query
                        ->where('type', SourceType::SocialMedia->value)
                        ->whereNotNull('page_url')->where('page_url', '!=', ''));
                }
            });
    }

    public static function isPullable(FlyerSource $source): bool
    {
        if (! $source->isApproved()) {
            return false;
        }

        return match ($source->type) {
            SourceType::RetailerFeed => filled($source->feed_url),
            SourceType::SocialMedia => SocialPostsClient::isConfigured() && SocialPostsClient::supports($source->page_url),
            default => false,
        };
    }
}
