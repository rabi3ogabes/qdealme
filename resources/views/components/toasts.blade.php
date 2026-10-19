<div x-data="toast({ initial: @js(session('status')) })" class="pointer-events-none fixed inset-x-0 bottom-24 z-[90] flex flex-col items-center gap-2 px-4 md:bottom-6" aria-live="polite" aria-atomic="true">
    <template x-for="item in items" :key="item.id">
        <div x-transition.duration.200ms
             class="pointer-events-auto flex max-w-md items-center gap-3 rounded-2xl border border-border bg-surface px-4 py-3 text-sm shadow-(--shadow-lift)"
             :class="item.tone === 'danger' ? 'text-danger' : 'text-text'">
            <span class="grid size-7 shrink-0 place-items-center rounded-full" :class="item.tone === 'danger' ? 'bg-danger-soft' : 'bg-success-soft text-success'">
                <x-icon name="check" class="size-4" x-show="item.tone !== 'danger'" />
                <x-icon name="info" class="size-4" x-show="item.tone === 'danger'" />
            </span>
            <span x-text="item.message"></span>
            <button type="button" @click="dismiss(item.id)" class="ms-1 text-muted hover:text-text" aria-label="{{ __('nav.close') }}"><x-icon name="x" class="size-4" /></button>
        </div>
    </template>
</div>
