<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class IsAdminMiddleware
{
    /**
     * @throws HttpResponseException
     * @throws HttpException
     * @throws NotFoundHttpException
     */
    public function handle(Request $request, Closure $next)
    {
        // This shouldn't really be necessary as we have policies implemented, but it's a good idea to be safe.
        $user = $request->user();
        if (! $user || $user->role !== 'admin') {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
