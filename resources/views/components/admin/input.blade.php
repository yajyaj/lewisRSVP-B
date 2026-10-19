@props(['name', 'label', 'value' => '', 'type' => 'text', 'required' => false, 'id' => null])
@php
    $id = $id ?? $name;
    $errorName = str_replace(['][', '[', ']'], ['.', '.', ''], $name);
@endphp
<div class="grid min-w-0 gap-2">
    <label class="admin-label" for="{{ $id }}">{{ $label }} @if($required)<span class="text-xs text-muted">(required)</span>@endif</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" @if($type !== 'password') value="{{ old($errorName, $value) }}" @endif @required($required) @if($errors->has($errorName)) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif {{ $attributes->class(['admin-control']) }}>
    @if($errors->has($errorName))<p id="{{ $id }}-error" class="text-sm text-red-700">{{ $errors->first($errorName) }}</p>@endif
</div>
