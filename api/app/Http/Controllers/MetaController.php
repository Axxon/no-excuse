<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class MetaController extends Controller
{
    public function aiProviders(): JsonResponse
    {
        $mode = config('no-excuse.ai.mode');

        return response()->json([
            'mode' => $mode,
            'providers' => collect(config('no-excuse.ai.providers'))->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'defaults' => config('no-excuse.ai.defaults.'.$key),
                'configured' => $mode === 'demo' || (bool) config('no-excuse.ai.credentials.'.$key, false),
                'credential_configured' => (bool) config('no-excuse.ai.credentials.'.$key, false),
            ])->values(),
        ]);
    }
}
