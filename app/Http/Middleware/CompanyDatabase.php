<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompanyDatabase
{
    public function handle($request, Closure $next)
    {
        $user = Auth::user();
        if ($user) {
            $company = $user->company;
            if ($company) {
                DB::setDefaultConnection($company->database);
            }
        }
        return $next($request);
    }
}
