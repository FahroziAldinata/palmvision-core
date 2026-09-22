<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthCheckController extends Controller
{
    /**
     * Perform system health checks.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'status' => 'ok',
            'app' => config('app.name', 'PalmVision'),
            'environment' => config('app.env', 'local'),
            'database' => [
                'status' => 'disconnected',
                'postgis_version' => null,
            ],
            'redis' => [
                'status' => 'disconnected',
            ],
            'forecasting' => [
                'status' => 'unreachable',
            ],
        ];

        $statusCode = 200;

        // 1. Check Database & PostGIS (Critical)
        try {
            /** @var array<int, object{ver: string}> $postgis */
            $postgis = DB::select('SELECT PostGIS_Version() as ver');
            $checks['database']['status'] = 'connected';
            $checks['database']['postgis_version'] = $postgis[0]->ver ?? 'available';
        } catch (Throwable $e) {
            $checks['database']['status'] = 'error';
            $checks['database']['error'] = $e->getMessage();
            $checks['status'] = 'degraded';
            $statusCode = 503;
        }

        // 2. Check Redis (Critical)
        try {
            $redisPong = Redis::ping();
            if ($redisPong) {
                $checks['redis']['status'] = 'connected';
            }
        } catch (Throwable $e) {
            $checks['redis']['status'] = 'error';
            $checks['redis']['error'] = $e->getMessage();
            $checks['status'] = 'degraded';
            $statusCode = 503;
        }

        // 3. Check Forecasting Service Reachability (Auxiliary Service)
        try {
            $forecastingUrl = (string) config('services.forecasting.url', 'http://forecasting:8000');
            $response = Http::timeout(2)->get($forecastingUrl.'/health');

            if ($response->successful()) {
                $checks['forecasting']['status'] = 'reachable';
                $checks['forecasting']['response'] = $response->json();
            } else {
                $checks['forecasting']['status'] = 'unreachable';
                $checks['forecasting']['http_status'] = $response->status();
            }
        } catch (Throwable) {
            $checks['forecasting']['status'] = 'unreachable';
            $checks['forecasting']['message'] = 'Forecasting service is currently unreachable';
        }

        return response()->json($checks, $statusCode);
    }
}
