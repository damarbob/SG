<?php

namespace App\Controllers\API;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;
use OpenApi\Generator;
use OpenApi\Attributes as OA;

#[OA\Info(title: "Placeholder", version: "1.0.0")]
#[OA\Server(url: "http://localhost", description: "Placeholder")]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    description: "Enter your bearer token in the format **Bearer &lt;token&gt;**"
)]
class Swagger extends BaseController
{
    /**
     * Display Swagger UI
     *
     * @return string
     */
    public function index()
    {
        return view('Swagger/swagger');
    }

    /**
     * Generate and return Swagger JSON
     *
     * @return ResponseInterface
     */
    public function json()
    {
        $generator = new Generator();
        $openapi = $generator->generate([APPPATH . 'Controllers']);

        // Dynamic Configuration
        $openapi->info->title = config('StarGate')->appName . ' API';
        // Base URL + 'api/v1' suffix (adjust as needed)
        $openapi->servers = [
            new OA\Server(
                url: rtrim(config('App')->baseURL, '/') . '/api/v1',
                description: config('StarGate')->appName . ' API v1'
            )
        ];

        return $this->response
            ->setHeader('Content-Type', 'application/json')
            ->setBody($openapi->toJson());
    }
}
