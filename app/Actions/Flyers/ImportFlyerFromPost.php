<?php

namespace App\Actions\Flyers;

use App\Enums\SourceType;
use App\Exceptions\PostImportException;
use App\Models\Flyer;
use App\Models\FlyerSource;
use App\Models\Retailer;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Imports a flyer that a store published in a post on its own social media
 * page or website. Staff save the post's flyer images and drop them in; the
 * post link is kept for reference. QDealMe never downloads from social
 * networks itself: their terms forbid automated collection (ADR 002).
 *
 * The flyer is created as a draft under the store's approved source, and
 * the same post or the same images are never imported twice.
 */
class ImportFlyerFromPost
{
    private const SOCIAL_HOSTS = [
        'facebook.com', 'fb.com', 'instagram.com', 'x.com', 'twitter.com', 'tiktok.com',
        'snapchat.com', 'threads.net', 'threads.com', 'linkedin.com', 'youtube.com',
    ];

    /** Path segments of social links that name a post, not the page that posted it. */
    private const NOT_A_PAGE = [
        'share', 'sharer', 'permalink.php', 'story.php', 'photo.php', 'photo', 'photos', 'watch', 'reel', 'reels',
        'p', 'groups', 'events', 'media', 'hashtag', 'stories', 'status', 'i', 'video', 'videos', 'tv', 'posts',
    ];

    private const SOURCE_PREFERENCE = [SourceType::SocialMedia, SourceType::AdminUpload, SourceType::RetailerUpload, SourceType::RetailerFeed];

    public function __construct(private readonly CreateDraftFlyer $drafts) {}

    /**
     * @param  array<int, string>  $paths  the post's images on the local disk, in page order
     * @param  array{title_en?: ?string, title_ar?: ?string, valid_from?: ?CarbonInterface, valid_until?: ?CarbonInterface}  $details
     */
    public function handle(FlyerSource $source, array $paths, ?string $postUrl, array $details = []): Flyer
    {
        $source->loadMissing('retailer');

        if (! $source->isApproved()) {
            throw PostImportException::because('not_approved');
        }

        $paths = array_values(array_filter($paths));

        if ($paths === []) {
            throw PostImportException::because('no_pages');
        }

        $postUrl = static::cleanUrl($postUrl);
        $hash = static::contentHash($paths);

        if ($existing = static::duplicateOf($source->retailer_id, $postUrl, $hash)) {
            throw PostImportException::duplicate($existing);
        }

        return $this->drafts->handle($source, $paths, [
            'title_en' => $details['title_en'] ?? null,
            'title_ar' => $details['title_ar'] ?? null,
            'valid_from' => $details['valid_from'] ?? null,
            'valid_until' => $details['valid_until'] ?? null,
            'source_post_url' => $postUrl,
            'content_hash' => $hash,
        ]);
    }

    /**
     * A fingerprint of the images, in order, so a post saved twice (or shared
     * under a different link) is recognised.
     *
     * @param  array<int, string>  $paths
     */
    public static function contentHash(array $paths): string
    {
        $disk = Storage::disk('local');

        return hash('sha256', implode("\0", array_map(fn (string $path) => hash('sha256', (string) $disk->get($path)), $paths)));
    }

    public static function duplicateOf(int $retailerId, ?string $postUrl, ?string $hash): ?Flyer
    {
        if (blank($postUrl) && blank($hash)) {
            return null;
        }

        return Flyer::query()
            ->where('retailer_id', $retailerId)
            ->where(function ($query) use ($postUrl, $hash): void {
                if (filled($hash)) {
                    $query->orWhere('content_hash', $hash);
                }

                if (filled($postUrl)) {
                    $query->orWhere('source_post_url', $postUrl);
                }
            })
            ->latest('id')
            ->first();
    }

    /**
     * Picks the store and its approved source from a post link: the page name
     * in the link is matched against each source's official page, and a
     * website link against each store's website. Share links carry no page
     * name, so for those staff pick the store.
     */
    public static function guessSource(?string $postUrl): ?FlyerSource
    {
        $key = static::pageKey($postUrl);

        if ($key === null) {
            return null;
        }

        $match = FlyerSource::query()->approved()->whereNotNull('page_url')->with('retailer')->get()
            ->first(fn (FlyerSource $source) => static::pageKey($source->page_url) === $key);

        if ($match !== null) {
            return $match;
        }

        $retailer = Retailer::query()->whereNotNull('website_url')->get()
            ->first(fn (Retailer $retailer) => static::pageKey($retailer->website_url) === $key);

        return $retailer ? static::bestSourceFor($retailer->id) : null;
    }

    /**
     * The approved source to import under, preferring the store's social media
     * permission, then an admin or store upload permission.
     */
    public static function bestSourceFor(?int $retailerId): ?FlyerSource
    {
        if ($retailerId === null) {
            return null;
        }

        return FlyerSource::query()->approved()->where('retailer_id', $retailerId)->latest('approved_at')->get()
            ->sortBy(fn (FlyerSource $source) => array_search($source->type, self::SOURCE_PREFERENCE, true))
            ->first();
    }

    /**
     * "facebook.com/parishypermarket" for a page or its posts, "facebook.com/id:123"
     * for numeric profile links, the bare host for websites, or null for links
     * that do not name a page (such as Facebook share links).
     */
    public static function pageKey(?string $url): ?string
    {
        $parts = parse_url(trim((string) $url));
        $host = Str::lower((string) ($parts['host'] ?? ''));

        if ($host === '') {
            return null;
        }

        $host = (string) preg_replace('/^(www|m|mobile|web|mbasic|business)\./', '', $host);
        $social = collect(self::SOCIAL_HOSTS)->first(fn (string $base) => $host === $base || str_ends_with($host, '.'.$base));

        if ($social === null) {
            return $host;
        }

        $segments = array_values(array_filter(explode('/', (string) ($parts['path'] ?? ''))));
        parse_str((string) ($parts['query'] ?? ''), $query);

        if (in_array($segments[0] ?? '', ['profile.php', 'permalink.php', 'story.php'], true) && filled($query['id'] ?? null)) {
            return $social.'/id:'.$query['id'];
        }

        $first = Str::lower(ltrim((string) ($segments[0] ?? ''), '@'));

        if ($first === '' || in_array($first, self::NOT_A_PAGE, true)) {
            return null;
        }

        return $social.'/'.$first;
    }

    /**
     * Trims the link and drops tracking parameters, so the same post is
     * recognised when it is pasted again.
     */
    public static function cleanUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

        if (! isset($parts['query'])) {
            return $url;
        }

        parse_str($parts['query'], $query);
        $query = array_filter($query, fn ($value, $key) => ! preg_match('/^(utm_|fbclid$|igsh$|igshid$|mibextid$|rdid$|ref$|__cft__|__tn__|s$|si$)/', (string) $key), ARRAY_FILTER_USE_BOTH);
        $base = Str::before($url, '?');

        return $query === [] ? $base : $base.'?'.http_build_query($query);
    }
}
