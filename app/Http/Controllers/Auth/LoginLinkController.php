<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class LoginLinkController extends Controller
{
    public function __invoke(User $user): RedirectResponse
    {
        // The signature is validated by the `signed` middleware. Hand the user
        // off to the web app's login with their email prefilled.
        $target = config('app.frontend_url').'/login?email='.urlencode($user->email);

        return redirect()->away($target);
    }
}
