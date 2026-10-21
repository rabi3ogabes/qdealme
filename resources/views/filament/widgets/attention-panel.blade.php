<x-filament-widgets::widget class="qd-attention" wire:poll.60s>
    <x-filament::section :heading="__('admin.dashboard.attention')" :description="__('admin.dashboard.attention_hint')">
        @if ($items === [])
            <div class="qd-attention-clear">
                <span class="qd-attention-clear-icon">
                    <x-filament::icon icon="heroicon-o-check-badge" />
                </span>
                <p class="qd-attention-clear-title">{{ __('admin.dashboard.all_clear') }}</p>
                <p class="qd-attention-clear-text">{{ __('admin.dashboard.all_clear_hint') }}</p>
            </div>
        @else
            <ul class="qd-attention-list" role="list">
                @foreach ($items as $item)
                    <li>
                        <a href="{{ $item['url'] }}" class="qd-attention-item qd-tone-{{ $item['tone'] }}">
                            <span class="qd-attention-icon">
                                <x-filament::icon :icon="$item['icon']" />
                            </span>
                            <span class="qd-attention-label">{{ $item['label'] }}</span>
                            <span class="qd-attention-count">{{ \Illuminate\Support\Number::format($item['count']) }}</span>
                            <x-filament::icon icon="heroicon-m-chevron-right" class="qd-attention-chevron qd-flip-rtl" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
