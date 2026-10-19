@if($errors->any())
<div class="validation-summary rounded-xl border border-red-200 bg-red-50 p-4 text-red-800" role="alert" tabindex="-1">
    <p class="font-semibold">Please review the following before continuing.</p>
    <ul class="mt-2 list-inside list-disc text-sm">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
</div>
@endif
