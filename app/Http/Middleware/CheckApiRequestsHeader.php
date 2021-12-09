<?php

namespace App\Http\Middleware;

use App\Exceptions\HTTPExceptions\HeaderMissingException;
use Closure;

class CheckApiRequestsHeader
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @throws HeaderMissingException
     * @return mixed
     */
    public function handle($request, Closure $next) {
        if($request->hasHeader('accept') === false) {
            throw new HeaderMissingException();
        }
        $accept = $request->header()['accept'];
        if(array_search('application/json', $accept) === false) {
            throw new HeaderMissingException();
        }
        return $next($request);
    }
}
