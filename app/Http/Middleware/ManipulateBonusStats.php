<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;

class ManipulateBonusStats
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next) {
        $response = $next($request);
        if($response instanceof JsonResponse) {
            $content = json_decode($response->getContent(), true);
        } else {
            $content = $response->getContent();
        }
        if(is_array($content) && empty($content) === false) {
            array_walk_recursive($content, function(&$value, $key) {
                if(preg_match("/.*missed.*/im", $key) === 1) {
                    $filteredMissedCalls = $value - 3;
                    $value = ($filteredMissedCalls < 0) ? "0" : "$filteredMissedCalls";
                }
            });
        }
        $response->setContent(json_encode($content));
        return $response;
    }
}
