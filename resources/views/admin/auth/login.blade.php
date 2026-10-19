<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Sign in &middot; Gather administration</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-mist text-deep antialiased">
<main class="grid min-h-screen lg:grid-cols-2">
<section class="login-intro flex flex-col justify-between gap-10 bg-deep p-7 text-white sm:p-12 lg:p-16">
<a href="{{ route('admin.login') }}" class="text-2xl font-semibold tracking-tight">gather<span class="text-teal-200">.</span>
</a>
<div class="max-w-lg">
<p class="mb-5 text-xs font-semibold uppercase tracking-[0.2em] text-teal-200">Celebration administration</p>
<h1 class="text-3xl font-semibold leading-tight tracking-tight sm:text-5xl">Every guest.<br>Every little detail.</h1>
<p class="mt-6 max-w-md text-base leading-8 text-teal-50/80">Keep your celebration organized, so you can focus on the moments that matter.</p>
</div>
<p class="text-sm text-teal-100/70">A thoughtful workspace for your event.</p>
</section>
<section class="flex items-center justify-center px-5 py-12 sm:px-10">
<div class="w-full max-w-md">
<p class="text-xs font-semibold uppercase tracking-widest text-ocean">Welcome back</p>
<h2 class="mt-3 text-3xl font-semibold tracking-tight">Sign in to Gather</h2>
<p class="mt-3 leading-7 text-muted">Use your organizer account to continue.</p>
<form method="POST" action="{{ route('admin.login.store') }}" class="mt-8 grid gap-6">@csrf<x-admin.field name="identifier" label="Username or email" autocomplete="username" maxlength="255" autofocus />
<x-admin.field name="password" label="Password" type="password" autocomplete="current-password" maxlength="4096" />
<x-admin.button type="submit" class="w-full">Sign in <span aria-hidden="true">&#8594;</span>
</x-admin.button>
</form>
<p class="mt-7 text-sm leading-6 text-muted">Need access? Contact your event&#8217;s Super Admin.</p>
</div>
</section>
</main>
</body>
</html>
