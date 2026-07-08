<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class AdminOnly {
    public function handle(Request $request, Closure $next) {
        if (!$request->user()?->isAdmin()) {
            return redirect()->route('dashboard')->with('error', 'Akses ditolak.');
        }
        return $next($request);
    }
}
