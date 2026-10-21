<?php

namespace App\Services;

use App\Exceptions\FeedPullException;
use App\Models\Retailer;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Downloads a store's current flyer from the official link the store gave us.
 *
 * The link must be on the store's own website (its `website_url` domain), so
 * this can only ever read a retailer's own publication, never another flyer
 * site. It may point straight at the flyer (an image or PDF) or at a small
 * JSON manifest describing it; see docs/RETAILER-FEEDS.md.
 */
class RetailerFeedClient
{
    private const MAX_PAGES = 40;

    private const TIMEOUT_SECONDS = 30;

    /** @var Closure(string): array<int, string>|false */
    private Closure $resolver;

    /**
     * @param  (Closure(string): (array<int, string>|false))|null  $resolver  host to IP addresses; injectable for tests
     */
    public function __construct(?Closure $resolver = null)
    {
        $this->resolver = $resolver ?? fn (string $host) => gethostbynamel($host);
    }

    /**
     * @return array{files: array<int, array{bytes: string, ext: string}>, title_en: ?string, title_ar: ?string, valid_from: ?Carbon, valid_until: ?Carbon}
     */
    public function fetch(Retailer $retailer, string $feedUrl): array
    {
        $this->assertOnRetailerSite($retailer, $feedUrl);

        $body = $this->download($feedUrl);
        $file = $this->asFlyerFile($body);

        if ($file !== null) {
            return ['files' => [$file], 'title_en' => null, 'title_ar' => null, 'valid_from' => null, 'valid_until' => null];
        }

        return $this->fromManifest($body);
    }

    /**
     * @return array{files: array<int, array{bytes: string, ext: string}>, title_en: ?string, title_ar: ?string, valid_from: ?Carbon, valid_until: ?Carbon}
     */
    private function fromManifest(string $body): array
    {
        $manifest = json_decode($body, true);

        if (! is_array($manifest) || ! isset($manifest['pages']) || ! is_array($manifest['pages']) || $manifest['pages'] === []) {
            throw FeedPullException::because('unrecognised');
        }

        $pages = array_values(array_filter($manifest['pages'], 'is_string'));

        if (count($pages) > self::MAX_PAGES) {
            throw FeedPullException::because('too_many_pages', ['max' => self::MAX_PAGES]);
        }

        $files = [];

        foreach ($pages as $url) {
            $file = $this->asFlyerFile($this->download($url));

            if ($file === null) {
                throw FeedPullException::because('page_not_image', ['url' => $url]);
            }

            $files[] = $file;
        }

        return [
            'files' => $files,
            'title_en' => $this->text($manifest['title_en'] ?? null),
            'title_ar' => $this->text($manifest['title_ar'] ?? null),
            'valid_from' => $this->date($manifest['valid_from'] ?? null),
            'valid_until' => $this->date($manifest['valid_until'] ?? null),
        ];
    }

    private function assertOnRetailerSite(Retailer $retailer, string $url): void
    {
        $site = strtolower((string) parse_url((string) $retailer->website_url, PHP_URL_HOST));

        if ($site === '') {
            throw FeedPullException::because('no_website');
        }

        $site = preg_replace('/^www\./', '', $site);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host !== $site && ! str_ends_with($host, '.'.$site)) {
            throw FeedPullException::because('not_retailer_site', ['site' => $site]);
        }
    }

    /**
     * Only plain HTTPS to a public address; redirects are not followed so a
     * link cannot bounce the request somewhere else.
     */
    private function download(string $url): string
    {
        $parts = parse_url($url);

        if (($parts['scheme'] ?? null) !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw FeedPullException::because('not_https', ['url' => $url]);
        }

        $this->assertPublicHost($parts['host']);

        $max = (int) config('qdealme.max_upload_mb') * 1024 * 1024;

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withOptions(['allow_redirects' => false])
                ->withHeaders(['Accept' => 'application/json, application/pdf, image/*'])
                ->get($url);
        } catch (ConnectionException) {
            throw FeedPullException::because('unreachable', ['url' => $url]);
        }

        if (! $response->successful()) {
            throw FeedPullException::because('http_status', ['url' => $url, 'status' => $response->status()]);
        }

        $body = $response->body();

        if ($body === '' || strlen($body) > $max) {
            throw FeedPullException::because('too_large', ['url' => $url, 'mb' => config('qdealme.max_upload_mb')]);
        }

        return $body;
    }

    private function assertPublicHost(string $host): void
    {
        $host = trim($host, '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ($this->resolver)($host);

        if (! is_array($ips) || $ips === []) {
            throw FeedPullException::because('unreachable', ['url' => $host]);
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw FeedPullException::because('private_address', ['host' => $host]);
            }
        }
    }

    /**
     * @return array{bytes: string, ext: string}|null
     */
    private function asFlyerFile(string $bytes): ?array
    {
        if (str_starts_with($bytes, '%PDF-')) {
            return ['bytes' => $bytes, 'ext' => 'pdf'];
        }

        $info = @getimagesizefromstring($bytes);
        $ext = match ($info['mime'] ?? null) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => null,
        };

        return $ext === null ? null : ['bytes' => $bytes, 'ext' => $ext];
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, 255) : null;
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value, config('qdealme.timezone')) ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
