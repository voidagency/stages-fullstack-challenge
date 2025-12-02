<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     * Add security headers to prevent common attacks.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Détecter les tentatives d'injection SQL dans les paramètres de requête
        $this->detectSqlInjectionAttempts($request);

        $response = $next($request);

        // Ajouter des headers de sécurité
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Content Security Policy pour prévenir XSS
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:;");
        
        return $response;
    }

    /**
     * Detect SQL injection attempts in request parameters.
     * Log suspicious activity.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    private function detectSqlInjectionAttempts(Request $request)
    {
        $suspiciousPatterns = [
            '/(\bUNION\b.*\bSELECT\b)/i',
            '/(\bOR\b.*=.*)/i',
            '/(\bAND\b.*=.*)/i',
            '/(;.*DROP\b)/i',
            '/(;.*DELETE\b)/i',
            '/(;.*UPDATE\b)/i',
            '/(;.*INSERT\b)/i',
            '/(\bEXEC\b|\bEXECUTE\b)/i',
            '/(--)/i',
            '/(\/\*.*\*\/)/i',
        ];

        $allInputs = array_merge(
            $request->query->all(),
            $request->request->all()
        );

        foreach ($allInputs as $key => $value) {
            if (is_string($value)) {
                foreach ($suspiciousPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        \Log::warning('Potential SQL injection attempt detected', [
                            'ip' => $request->ip(),
                            'url' => $request->fullUrl(),
                            'parameter' => $key,
                            'value' => $value,
                            'user_agent' => $request->userAgent(),
                        ]);
                        break;
                    }
                }
            }
        }
    }
}
