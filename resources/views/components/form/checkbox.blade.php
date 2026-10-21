@props(['name', 'label', 'checked' => false])
<label class="flex cursor-pointer items-start gap-3 text-sm">
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $checked)) class="mt-0.5 size-5 rounded-md border-border-strong text-primary focus:ring-primary/20">
    <span class="text-text-2">{{ $label }}</span>
</label>
