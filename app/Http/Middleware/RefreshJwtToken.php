<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use Symfony\Component\HttpFoundation\Response;

class RefreshJwtToken
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException $e) {
            try {
                $newToken = JWTAuth::parseToken()->refresh();
                return $this->setAuthenticationHeader($next($request), $newToken);
            } catch (Exception $e) {
                return response()->json(['error' => 'Token expired, please login again'], 401);
            }
        } catch (TokenInvalidException $e) {
            return response()->json(['error' => 'Invalid token'], 401);
        } catch (Exception $e) {
            return response()->json(['error' => 'Authorization token not found'], 401);
        }

        return $next($request);
    }

    protected function setAuthenticationHeader($response, $token)
    {
        $response->headers->set('Authorization', 'Bearer ' . $token);
        return $response;
    }
}
