@if (session('status'))
    <div class="mb-6 flex items-center gap-3 rounded-2xl border border-success/20 bg-success-soft px-4 py-3 text-sm text-success" role="status">
        <x-icon name="check-badge" class="size-5 shrink-0" />
        <span>{{ session('status') }}</span>
    </div>
@endif
