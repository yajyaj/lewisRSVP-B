<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FoundationController extends Controller
{
    public function __invoke(Request $request): View
    {
        $section = $request->route()->defaults['section'];

        return view(match ($section) {
            'Event Settings' => 'admin.event-settings.index',
            'My Profile' => 'admin.profile.index',
            default => abort(404),
        }, ['section' => $section]);
    }
}
