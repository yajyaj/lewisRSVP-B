@props(['name', 'label', 'value' => '', 'required' => false])
<div class="grid min-w-0 gap-2">
    <label class="admin-label" for="{{ $name }}">{{ $label }} @if($required)<span class="text-xs text-muted">(required)</span>@endif</label>
    <textarea id="{{ $name }}" name="{{ $name }}" rows="3" @required($required) @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror {{ $attributes->class(['admin-control py-3']) }}>{{ old($name, $value) }}</textarea>
    @error($name)<p id="{{ $name }}-error" class="text-sm text-red-700">{{ $message }}</p>@enderror
</div>
