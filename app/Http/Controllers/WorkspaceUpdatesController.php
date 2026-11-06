<?php

namespace App\Http\Controllers;

use App\Services\SidebarCounts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** What open Admin and Logistics Center pages poll every 20 seconds: the numbers on their sidebar. */
class WorkspaceUpdatesController extends Controller
{
    public function admin(Request $request, SidebarCounts $counts): JsonResponse
    {
        return response()->json(['signature' => null, 'badges' => $counts->totals($request->user(), 'admin')]);
    }

    public function logistics(Request $request, SidebarCounts $counts): JsonResponse
    {
        return response()->json(['signature' => null, 'badges' => $counts->totals($request->user(), 'logistics')]);
    }
}
