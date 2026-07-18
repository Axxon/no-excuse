<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class MetaController extends Controller
{
    public function aiProviders(): JsonResponse
    {
        return response()->json([
            'mode' => config('no-excuse.ai.mode'),
            'providers' => collect(config('no-excuse.ai.providers'))->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'defaults' => config('no-excuse.ai.defaults.'.$key),
            ])->values(),
        ]);
    }
}
