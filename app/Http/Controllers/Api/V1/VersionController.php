<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class VersionController extends Controller
{
    /**
     * Informations de version
     *
     * Retourne le nom public de l’API ainsi que les versions de l’application et du contrat HTTP.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'name' => config('api.name'),
            'version' => config('api.version'),
            'api_version' => config('api.version_prefix'),
        ]);
    }
}
