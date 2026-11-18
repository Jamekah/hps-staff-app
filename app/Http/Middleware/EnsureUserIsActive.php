<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Log out and block any authenticated user whose account has been
     * deactivated, or whose login has since been turned off (a login account
     * reduced to a roster entry keeps no live session).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && (! $request->user()->is_active || ! $request->user()->login_enabled)) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account has been deactivated. Contact an administrator.']);
        }

        return $next($request);
    }
}
