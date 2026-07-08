<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class SuperAdminOnly {
    public function handle(Request $request, Closure $next) {
        if (!$request->user()?->isSuperAdmin()) {
            return redirect()->route('admin.dashboard')->with('error', 'Hanya Super Admin.');
        }
        return $next($request);
    }
}
