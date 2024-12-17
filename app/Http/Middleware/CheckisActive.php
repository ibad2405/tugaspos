<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckisActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if(Auth::check() && !Auth::user()->isActive){
            Auth::logout();
            session()->flush();  
            return redirect(url('/admin/login'))->withErrors([
                'loginerror' => 'Akun Anda tidak aktif. Silakan hubungi administrator.',
            ]);
            
        }
        session()->regenerate();
        return $next($request);
       
    }
}
