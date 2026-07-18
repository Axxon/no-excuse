<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class MetaController extends Controller
{
    public function about(): JsonResponse
    {
        return response()->json([
            'author_name' => config('no-excuse.author.name'),
            'author_linkedin_url' => config('no-excuse.author.linkedin_url'),
            'license' => 'MIT',
        ]);
    }

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
