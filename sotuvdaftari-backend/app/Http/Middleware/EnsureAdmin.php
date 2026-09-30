<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Faqat `is_admin` foydalanuvchilar uchun */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_admin) {
            throw new ApiException(__('messages.bonus.admin_only'), 403, 'forbidden');
        }

        return $next($request);
    }
}
