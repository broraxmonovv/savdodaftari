<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Bloklangan foydalanuvchi API'dan foydalana olmaydi */
class EnsureNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isBlocked()) {
            throw new ApiException(__('messages.account_blocked'), 403, 'account_blocked', [
                'reason' => $user->block_reason,
            ]);
        }

        return $next($request);
    }
}
