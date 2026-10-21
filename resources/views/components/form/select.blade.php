@props(['name', 'label', 'options' => [], 'value' => null, 'required' => false, 'placeholder' => null])
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="label">{{ $label }} @unless ($required)<span class="text-muted font-normal">({{ __('ui.optional') }})</span>@endunless</label>
    <select id="{{ $name }}" name="{{ $name }}" @if ($required) required @endif {{ $attributes->except('class')->merge(['class' => 'input appearance-none bg-no-repeat pe-10'.($errors->has($name) ? ' border-danger' : '')]) }} style="background-image:url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23948774' stroke-width='2'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='m19.5 8.25-7.5 7.5-7.5-7.5'/%3E%3C/svg%3E&quot;);background-size:1rem;background-position:{{ is_rtl() ? 'left 1rem center' : 'right 1rem center' }}">
        @if ($placeholder)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected((string) old($name, $value) === (string) $optValue)>{{ $optLabel }}</option>
        @endforeach
    </select>
    @error($name)<p class="mt-1.5 text-xs text-danger">{{ $message }}</p>@enderror
</div>
