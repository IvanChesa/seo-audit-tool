<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\FetchPageJob;
use App\Models\Audit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuditController extends Controller
{
    /**
     * List all audits.
     */
    public function index(): JsonResponse
    {
        $audits = Audit::latest()->paginate(15);

        return response()->json($audits);
    }

    /**
     * Create a new audit and dispatch it for processing.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $audit = Audit::create([
            'url' => $validated['url'],
            'status' => 'pending',
        ]);

        FetchPageJob::dispatch($audit);

        return response()->json($audit, 201);
    }

    /**
     * Show a single audit with its results.
     */
    public function show(Audit $audit): JsonResponse
    {
        $audit->load(['results', 'brokenLinks']);

        return response()->json($audit);
    }
}