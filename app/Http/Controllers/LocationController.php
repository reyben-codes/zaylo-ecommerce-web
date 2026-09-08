<?php

namespace App\Http\Controllers;

use App\Services\PsgcService;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function regions(PsgcService $psgc): JsonResponse
    {
        return response()->json($psgc->regions());
    }

    public function provinces(string $regionCode, PsgcService $psgc): JsonResponse
    {
        return response()->json($psgc->provinces($regionCode));
    }

    public function cities(string $provinceCode, PsgcService $psgc): JsonResponse
    {
        return response()->json($psgc->cities($provinceCode));
    }

    public function provinceFreeCities(string $regionCode, PsgcService $psgc): JsonResponse
    {
        return response()->json($psgc->provinceFreeCities($regionCode));
    }

    public function barangays(string $cityCode, PsgcService $psgc): JsonResponse
    {
        return response()->json($psgc->barangays($cityCode));
    }
}
