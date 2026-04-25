<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\AdminAuditLogResource;
use App\Models\AdminAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuditLogController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AdminAuditLog::class);

        $logs = AdminAuditLog::query()
            ->when($request->filled('actor_id'), fn ($query) => $query->where('actor_id', $request->integer('actor_id')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->string('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->string('date_to')))
            ->latest('created_at')
            ->paginate((int) $request->integer('per_page', config('admin.audit.pagination_per_page')));

        return $this->paginated(AdminAuditLogResource::collection($logs), 'Audit logs retrieved successfully.');
    }
}
