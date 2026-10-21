@props(['name', 'label', 'rows' => 4, 'required' => false, 'value' => null])
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="label">{{ $label }} @unless ($required)<span class="text-muted font-normal">({{ __('ui.optional') }})</span>@endunless</label>
    <textarea id="{{ $name }}" name="{{ $name }}" rows="{{ $rows }}" @if ($required) required @endif
              {{ $attributes->except('class')->merge(['class' => 'input resize-y'.($errors->has($name) ? ' border-danger' : '')]) }}
              @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>{{ old($name, $value) }}</textarea>
    @error($name)<p id="{{ $name }}-error" class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
</div>
