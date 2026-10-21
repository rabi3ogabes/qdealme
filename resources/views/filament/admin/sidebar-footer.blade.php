<div class="qd-sidebar-footer">
    <a href="{{ route('home') }}" target="_blank" rel="noopener" class="qd-sidebar-footer-link">
        <x-filament::icon icon="heroicon-o-globe-alt" class="qd-sidebar-footer-icon" />
        <span class="qd-sidebar-footer-label">{{ __('admin.view_site') }}</span>
        <x-filament::icon icon="heroicon-m-arrow-up-right" class="qd-sidebar-footer-icon qd-flip-rtl" />
    </a>
    <p class="qd-sidebar-footer-meta">
        {{ __('admin.qatar_time') }} · {{ now(config('qdealme.timezone'))->translatedFormat('H:i') }}
    </p>
</div>
