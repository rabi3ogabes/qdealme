<?php

namespace App\Support;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Collects everything the <head> needs for one page. Controllers build it,
 * the layout renders it, tests assert on it.
 */
final class Seo implements Arrayable
{
    private string $title = '';

    private string $description = '';

    private ?string $canonical = null;

    private ?string $image = null;

    private bool $noindex = false;

    private string $type = 'website';

    /** @var array<string, string> */
    private array $alternates = [];

    /** @var array<int, array<string, mixed>> */
    private array $jsonLd = [];

    public static function make(): self
    {
        return new self;
    }

    public function title(string $title, bool $withBrand = true): self
    {
        $this->title = $withBrand ? $title.' | '.brand() : $title;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = mb_substr(trim(preg_replace('/\s+/', ' ', $description) ?? ''), 0, 300);

        return $this;
    }

    public function canonical(string $url): self
    {
        $this->canonical = $url;

        return $this;
    }

    public function image(?string $url): self
    {
        $this->image = $url;

        return $this;
    }

    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function noindex(bool $noindex = true): self
    {
        $this->noindex = $noindex;

        return $this;
    }

    /**
     * @param  array<string, string>  $urls  locale => url
     */
    public function alternates(array $urls): self
    {
        $this->alternates = $urls;

        return $this;
    }

    /**
     * Build hreflang alternates for the current route in every locale.
     */
    public function alternatesForCurrentRoute(): self
    {
        $alternates = [];

        foreach (array_keys(config('qdealme.locales')) as $locale) {
            $alternates[$locale] = localized_url($locale);
        }

        return $this->alternates($alternates);
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public function jsonLd(array $schema): self
    {
        $this->jsonLd[] = ['@context' => 'https://schema.org'] + $schema;

        return $this;
    }

    public function breadcrumbs(array $crumbs): self
    {
        $items = [];
        $position = 1;

        foreach ($crumbs as $name => $url) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $name,
                'item' => $url,
            ];
        }

        return $this->jsonLd([
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ]);
    }

    private function absolute(string $url): string
    {
        return str_starts_with($url, '/') && ! str_starts_with($url, '//') ? url($url) : $url;
    }

    public function toArray(): array
    {
        $canonical = $this->canonical ?? url()->current();

        return [
            'title' => $this->title ?: brand(),
            'description' => $this->description,
            'canonical' => $canonical,
            'image' => $this->absolute($this->image ?? asset('images/og-default.png')),
            'type' => $this->type,
            'noindex' => $this->noindex,
            'alternates' => $this->alternates,
            'jsonLd' => $this->jsonLd,
            'locale' => app()->getLocale(),
        ];
    }
}
