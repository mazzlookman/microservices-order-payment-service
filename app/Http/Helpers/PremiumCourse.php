<?php

use Illuminate\Support\Facades\Http;

function createPremiumAccess($request)
{
    $url = env("COURSE_SERVICE_URL") . "/api/my-courses/premium";
    try {
        $response = Http::post($url , $request);
        return $response->json();

    } catch (Throwable $throwable){
        return [
            "code" => 500,
            "status" => "Internal Server Error",
            "errors" => [
                "message" => $throwable->getMessage()
            ]
        ];
    }
}
