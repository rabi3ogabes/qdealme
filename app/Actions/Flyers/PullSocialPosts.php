<?php

namespace App\Actions\Flyers;

use App\Enums\SourceType;
use App\Exceptions\FeedPullException;
use App\Exceptions\PostImportException;
use App\Models\Flyer;
use App\Models\FlyerSource;
use App\Services\SocialPostsClient;
use App\Support\PostDates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns a store's new flyer posts on its official Facebook page or Instagram
 * account into drafts for review, through Meta's official API. Posts that do
 * not look like a flyer (no images, or no offer words or dates), old posts and
 * posts already imported are skipped. The offer dates are read from the text.
 */
class PullSocialPosts
{
    /** Words that mark a post as an offers flyer, in English and Arabic. */
    private const OFFER_WORDS = [
        'offer', 'deal', 'promo', 'sale', 'discount', 'save', 'flyer', 'leaflet', 'price', 'festival', '% off',
        'عرض', 'عروض', 'تخفيض', 'خصم', 'خصومات', 'وفر', 'توفير', 'أسعار', 'اسعار', 'نشرة', 'مهرجان', 'تنزيلات', 'ريال',
    ];

    public function __construct(
        private readonly SocialPostsClient $client,
        private readonly ImportFlyerFromPost $import,
    ) {}

    /**
     * @return array<int, Flyer> the new drafts; empty when there is nothing new
     */
    public function handle(FlyerSource $source): array
    {
        $source->loadMissing('retailer');

        if (! $source->isApproved()) {
            throw FeedPullException::because('not_approved');
        }

        if ($source->type !== SourceType::SocialMedia || blank($source->page_url)) {
            throw FeedPullException::because('no_social_page');
        }

        if (! SocialPostsClient::supports($source->page_url)) {
            throw FeedPullException::because('social_unsupported_page');
        }

        try {
            $drafts = [];

            foreach ($this->client->recentPosts($source) as $post) {
                if ($flyer = $this->importPost($source, $post)) {
                    $drafts[] = $flyer;
                }
            }
        } catch (FeedPullException $e) {
            $source->forceFill(['last_pull_error' => $e->getMessage()])->save();

            throw $e;
        }

        $source->forceFill(['last_pulled_at' => now(), 'last_pull_error' => null])->save();

        return $drafts;
    }

    /**
     * @param  array{id: string, url: string, text: string, created_at: CarbonImmutable, images: array<int, string>, photo_ids?: array<int, ?string>}  $post
     */
    private function importPost(FlyerSource $source, array $post): ?Flyer
    {
        $today = CarbonImmutable::today(config('qdealme.timezone'));
        $dates = PostDates::find($post['text'], $today);
        $url = ImportFlyerFromPost::cleanUrl($post['url']);

        if (! static::looksLikeFlyer($post['text'], $dates !== null)
            || $post['created_at']->lt($today->subDays((int) config('qdealme.meta.max_post_age_days')))
            || ($dates !== null && $dates['until']->lt($today))
            || ImportFlyerFromPost::duplicateOf($source->retailer_id, $url, null) !== null) {
            return null;
        }

        $directory = 'uploads/flyers/social-'.$source->id.'-'.Str::lower(Str::random(10));
        $paths = [];

        try {
            foreach ($this->client->imageUrls($post) as $i => $imageUrl) {
                $file = $this->client->downloadImage($imageUrl);
                $path = sprintf('%s/page-%03d.%s', $directory, $i + 1, $file['ext']);
                Storage::disk('local')->put($path, $file['bytes']);
                $paths[] = $path;
            }

            return $this->import->handle($source, $paths, $url, [
                'valid_from' => $dates['from'] ?? $post['created_at']->startOfDay(),
                'valid_until' => $dates['until'] ?? null,
            ]);
        } catch (PostImportException) {
            // The same images were imported before, under another post.
            Storage::disk('local')->delete($paths);

            return null;
        } catch (FeedPullException $e) {
            Storage::disk('local')->delete($paths);

            throw $e;
        }
    }

    public static function looksLikeFlyer(string $text, bool $hasDates): bool
    {
        if ($hasDates) {
            return true;
        }

        $text = mb_strtolower($text);

        foreach (self::OFFER_WORDS as $word) {
            if (str_contains($text, $word)) {
                return true;
            }
        }

        return false;
    }
}
