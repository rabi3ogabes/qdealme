@php($other = app()->getLocale() === 'ar' ? 'en' : 'ar')
<p class="qd-simple-footer">
    <span>&copy; {{ now(config('qdealme.timezone'))->year }} QDealMe</span>
    <span aria-hidden="true">·</span>
    <span>{{ __('store.footer.private_link') }}</span>
    <span aria-hidden="true">·</span>
    <a href="{{ request()->fullUrlWithQuery(['lang' => $other]) }}" lang="{{ $other }}" class="qd-simple-footer-link">{{ config("qdealme.locales.{$other}.name") }}</a>
</p>
