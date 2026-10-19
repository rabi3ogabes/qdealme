@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'autocomplete' => null, 'hint' => null])
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="label">{{ $label }} @unless ($required)<span class="text-muted font-normal">({{ __('ui.optional') }})</span>@endunless</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" @if ($required) required @endif @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
           {{ $attributes->except('class')->merge(['class' => 'input'.($errors->has($name) ? ' border-danger focus:border-danger focus:ring-danger/10' : '')]) }}
           @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
    @if ($hint && ! $errors->has($name))<p class="mt-1.5 text-xs text-muted">{{ $hint }}</p>@endif
    @error($name)<p id="{{ $name }}-error" class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
</div>
