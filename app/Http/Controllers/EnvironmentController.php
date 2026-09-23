<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class EnvironmentController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'application' => ['status' => 'ok', 'name' => config('app.name')],
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ]);
    }

    private function checkDatabase(): array
    {
        try {
            DB::select('select 1');

            return ['status' => 'ok', 'driver' => DB::getDriverName()];
        } catch (Throwable $exception) {
            report($exception);

            return ['status' => 'error'];
        }
    }

    private function checkRedis(): array
    {
        try {
            Cache::put('environment-health-check', true, 10);

            return ['status' => Cache::get('environment-health-check') === true ? 'ok' : 'error'];
        } catch (Throwable $exception) {
            report($exception);

            return ['status' => 'error'];
        }
    }
}

