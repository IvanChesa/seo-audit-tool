<?php

namespace App\Http\Controllers\Api;

use App\Analysis\SnapshotStore;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListAuditsRequest;
use App\Http\Requests\StoreAuditRequest;
use App\Http\Resources\AuditResource;
use App\Http\Resources\AuditSummaryResource;
use App\Jobs\FetchPageJob;
use App\Models\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AuditController extends Controller
{
    /**
     * Paginated history, newest first. Filters: ?status=, ?search= (URL contains).
     */
    public function index(ListAuditsRequest $request): AnonymousResourceCollection
    {
        $audits = Audit::query()
            ->filter($request->status(), $request->search())
            ->orderByDesc('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return AuditSummaryResource::collection($audits);
    }

    /**
     * Creates the audit and queues it. The client follows its progress by
     * polling the URL in the Location header.
     */
    public function store(StoreAuditRequest $request): JsonResponse
    {
        $audit = Audit::createFor($request->safeUrl());

        FetchPageJob::dispatch($audit->id);

        return AuditResource::make($audit->fresh() ?? $audit)
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('audits.show', $audit));
    }

    public function show(Audit $audit): AuditResource
    {
        return AuditResource::make($audit->load(['results', 'brokenLinks']));
    }

    /**
     * Deletes the audit, its results and broken links (ON DELETE CASCADE).
     * Jobs still running for it will find no audit and stop.
     */
    public function destroy(Audit $audit, SnapshotStore $snapshots): Response
    {
        $audit->delete();
        $snapshots->forget($audit->id);

        return response()->noContent();
    }
}
