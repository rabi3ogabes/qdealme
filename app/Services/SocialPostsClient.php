<?php

namespace App\Services;

use App\Actions\Flyers\ImportFlyerFromPost;
use App\Exceptions\FeedPullException;
use App\Models\FlyerSource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reads a store's recent posts from its official Facebook page or Instagram
 * account through Meta's Graph API, the only permitted way to read them
 * automatically. Nothing here loads facebook.com or instagram.com pages.
 *
 * Facebook pages are read with the Page Public Content Access feature;
 * Instagram accounts through business discovery, via QDealMe's own Instagram
 * professional account. Both need Meta's approval of QDealMe's app.
 */
class SocialPostsClient
{
    private const POSTS_PER_PULL = 10;

    private const MAX_IMAGES_PER_POST = 40;

    private const TIMEOUT_SECONDS = 30;

    public function __construct(private readonly RetailerFeedClient $downloads) {}

    public static function isConfigured(): bool
    {
        return filled(config('qdealme.meta.access_token'));
    }

    /**
     * Whether an official page link can be read: a Facebook page, or an
     * Instagram account when QDealMe's own Instagram account is set.
     */
    public static function supports(?string $pageUrl): bool
    {
        $network = static::network($pageUrl);

        return $network === 'facebook'
            || ($network === 'instagram' && filled(config('qdealme.meta.instagram_account_id')));
    }

    /**
     * @return array<int, array{id: string, url: string, text: string, created_at: CarbonImmutable, images: array<int, string>, photo_ids?: array<int, ?string>}>
     */
    public function recentPosts(FlyerSource $source): array
    {
        if (! static::isConfigured()) {
            throw FeedPullException::because('social_not_configured');
        }

        [$network, $name] = static::page($source->page_url);

        return match ($network) {
            'facebook' => $this->facebookPosts($source, $name),
            'instagram' => $this->instagramPosts($name),
            default => throw FeedPullException::because('social_unsupported_page'),
        };
    }

    /**
     * @return array{bytes: string, ext: string}
     */
    public function downloadImage(string $url): array
    {
        return $this->downloads->downloadImage($url);
    }

    /**
     * @return array{0: ?string, 1: ?string} network ("facebook" or "instagram") and page name or "id:123"
     */
    private static function page(?string $pageUrl): array
    {
        $key = ImportFlyerFromPost::pageKey($pageUrl);
        $network = static::network($pageUrl);

        if ($key === null || $network === null || ! str_contains($key, '/')) {
            return [null, null];
        }

        return [$network, substr($key, strpos($key, '/') + 1)];
    }

    private static function network(?string $pageUrl): ?string
    {
        $key = (string) ImportFlyerFromPost::pageKey($pageUrl);

        return match (true) {
            str_starts_with($key, 'facebook.com/'), str_starts_with($key, 'fb.com/') => 'facebook',
            str_starts_with($key, 'instagram.com/') => 'instagram',
            default => null,
        };
    }

    /**
     * @return array<int, array{id: string, url: string, text: string, created_at: CarbonImmutable, images: array<int, string>}>
     */
    private function facebookPosts(FlyerSource $source, string $name): array
    {
        $pageId = $source->social_page_id;

        if (blank($pageId)) {
            $pageId = str_starts_with($name, 'id:')
                ? substr($name, 3)
                : (string) ($this->get(rawurlencode($name), ['fields' => 'id'])['id'] ?? '');

            if ($pageId === '') {
                throw FeedPullException::because('social_page_not_found');
            }

            $source->forceFill(['social_page_id' => $pageId])->save();
        }

        $media = 'media_type,type,target{id},media{image{src,width,height}}';
        $posts = $this->get(rawurlencode($pageId).'/posts', [
            'fields' => "id,message,created_time,permalink_url,attachments{{$media},subattachments.limit(".self::MAX_IMAGES_PER_POST."){{$media}}}",
            'limit' => self::POSTS_PER_PULL,
        ])['data'] ?? [];

        $result = [];

        foreach ($posts as $post) {
            $photos = [];

            foreach ($post['attachments']['data'] ?? [] as $attachment) {
                foreach ($attachment['subattachments']['data'] ?? [$attachment] as $item) {
                    if (($item['type'] ?? $item['media_type'] ?? null) === 'photo' && filled($item['media']['image']['src'] ?? null)) {
                        $photos[] = ['id' => $item['target']['id'] ?? null, 'src' => $item['media']['image']['src']];
                    }
                }
            }

            if ($photos === [] || blank($post['permalink_url'] ?? null)) {
                continue;
            }

            $photos = array_slice($photos, 0, self::MAX_IMAGES_PER_POST);

            $result[] = [
                'id' => (string) $post['id'],
                'url' => (string) $post['permalink_url'],
                'text' => (string) ($post['message'] ?? ''),
                'created_at' => $this->time($post['created_time'] ?? null),
                'images' => array_column($photos, 'src'),
                'photo_ids' => array_column($photos, 'id'),
            ];
        }

        return $result;
    }

    /**
     * The image URLs to import for a post, in page order. Facebook attachments
     * list a medium-sized copy of each photo; the photo itself lists every
     * size, so flyers are imported at the best resolution available.
     *
     * @param  array{images: array<int, string>, photo_ids?: array<int, ?string>}  $post
     * @return array<int, string>
     */
    public function imageUrls(array $post): array
    {
        $ids = array_values(array_filter($post['photo_ids'] ?? []));

        if ($ids === []) {
            return $post['images'];
        }

        try {
            $photos = $this->get('', ['ids' => implode(',', array_unique($ids)), 'fields' => 'images']);
        } catch (FeedPullException) {
            return $post['images']; // The medium-sized copies are good enough.
        }

        return array_map(function (string $src, ?string $id) use ($photos): string {
            $best = collect($photos[$id]['images'] ?? [])->sortByDesc('width')->first();

            return filled($best['source'] ?? null) ? $best['source'] : $src;
        }, $post['images'], $post['photo_ids']);
    }

    /**
     * @return array<int, array{id: string, url: string, text: string, created_at: CarbonImmutable, images: array<int, string>}>
     */
    private function instagramPosts(string $username): array
    {
        $account = (string) config('qdealme.meta.instagram_account_id');
        $fields = sprintf(
            'business_discovery.username(%s){media.limit(%d){id,caption,media_type,media_url,permalink,timestamp,children{media_type,media_url}}}',
            $username,
            self::POSTS_PER_PULL,
        );

        $media = $this->get(rawurlencode($account), ['fields' => $fields])['business_discovery']['media']['data'] ?? [];
        $result = [];

        foreach ($media as $item) {
            $images = match ($item['media_type'] ?? null) {
                'IMAGE' => [$item['media_url'] ?? null],
                'CAROUSEL_ALBUM' => array_map(
                    fn (array $child) => ($child['media_type'] ?? null) === 'IMAGE' ? ($child['media_url'] ?? null) : null,
                    $item['children']['data'] ?? [],
                ),
                default => [],
            };

            $images = array_values(array_filter($images));

            if ($images === [] || blank($item['permalink'] ?? null)) {
                continue;
            }

            $result[] = [
                'id' => (string) $item['id'],
                'url' => (string) $item['permalink'],
                'text' => (string) ($item['caption'] ?? ''),
                'created_at' => $this->time($item['timestamp'] ?? null),
                'images' => array_slice($images, 0, self::MAX_IMAGES_PER_POST),
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    private function get(string $path, array $query): array
    {
        $token = (string) config('qdealme.meta.access_token');
        $secret = (string) config('qdealme.meta.app_secret');
        $url = rtrim((string) config('qdealme.meta.graph_url'), '/').'/'.config('qdealme.meta.version').'/'.$path;

        $query['access_token'] = $token;

        if ($secret !== '') {
            $query['appsecret_proof'] = hash_hmac('sha256', $token, $secret);
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->acceptJson()->get($url, $query);
        } catch (ConnectionException) {
            throw FeedPullException::because('social_unreachable');
        }

        if (! $response->successful()) {
            throw $this->apiError($response);
        }

        return (array) $response->json();
    }

    private function apiError(Response $response): FeedPullException
    {
        $error = (array) $response->json('error', []);
        $code = (int) ($error['code'] ?? 0);
        $message = trim((string) ($error['message'] ?? ''));

        return match (true) {
            $code === 190 => FeedPullException::because('social_token'),
            $code === 10, $code >= 200 && $code < 300 => FeedPullException::because('social_needs_approval'),
            $code === 803, $code === 110, str_contains($message, 'does not exist') => FeedPullException::because('social_page_not_found'),
            default => FeedPullException::because('social_api', ['message' => $message !== '' ? $message : 'HTTP '.$response->status()]),
        };
    }

    private function time(mixed $value): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse((string) $value)->setTimezone(config('qdealme.timezone'));
        } catch (Throwable) {
            return CarbonImmutable::now(config('qdealme.timezone'));
        }
    }
}
