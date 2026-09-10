<?php
// app/Http/Middleware/TenantMiddleware.php

namespace App\Http\Middleware;

use Closure;
use App\Modules\Core\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Get the host from the request
        $host = $request->getHost();
        
        // Get the domain from config
        $domain = config('app.domain');
        
        // Extract subdomain
        $subdomain = $this->extractSubdomain($host, $domain);
        
        // If no subdomain, allow access (for landing page, etc.)
        if (!$subdomain) {
            return $next($request);
        }
        
        // Find school by subdomain
        $school = School::where('subdomain', $subdomain)->first();
        
        if (!$school) {
            abort(404, 'School not found');
        }
        
        // Check if school is unlocked
        if (!$school->is_unlocked) {
            abort(403, 'This school is currently locked. Please contact support.');
        }
        
        // Store school in request for later use
        $request->merge(['tenant' => $school]);
        $request->merge(['school_id' => $school->id]);
        
        // Share school data with all views
        view()->share('tenant', $school);
        
        return $next($request);
    }
    
    /**
     * Extract subdomain from host
     */
    private function extractSubdomain($host, $domain)
    {
        // For local development (localhost:8000)
        if ($domain === 'localhost:8000') {
            $parts = explode('.', $host);
            // If host is like "school1.localhost", first part is subdomain
            if (count($parts) > 1 && $parts[0] !== 'localhost' && $parts[0] !== 'www') {
                return $parts[0];
            }
            return null;
        }
        
        // For production (ohisedtech.com)
        $domainParts = explode('.', $domain);
        $hostParts = explode('.', $host);
        
        // Remove www from host if present
        if ($hostParts[0] === 'www') {
            array_shift($hostParts);
        }
        
        // If host has more parts than domain, first part is subdomain
        if (count($hostParts) > count($domainParts)) {
            return $hostParts[0];
        }
        
        return null;
    }
}