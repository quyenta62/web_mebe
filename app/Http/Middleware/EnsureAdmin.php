<?php

namespace App\Http\Middleware;

use App\Support\AdminCredentials;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Sessions of a previous admin username stop working once .env changes.
        $sessionUser = $request->session()->get('admin_username');
        if (! AdminCredentials::configured() || ! is_string($sessionUser) || ! hash_equals(AdminCredentials::username(), $sessionUser)) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
