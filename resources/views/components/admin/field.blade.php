@props(['name', 'label', 'type' => 'text', 'autocomplete' => null])
<div class="grid gap-2">
<label class="text-sm font-semibold" for="{{ $name }}">{{ $label }}</label>
<input class="min-h-12 w-full rounded-xl border border-slate-300 bg-white px-4" id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" autocomplete="{{ $autocomplete }}" @if($type !== 'password') value="{{ old($name) }}" @endif
 required @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror {{ $attributes }}>@error($name)<p id="{{ $name }}-error" class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror</div>
