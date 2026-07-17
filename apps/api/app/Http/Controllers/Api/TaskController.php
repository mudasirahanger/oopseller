<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Http\Controllers\Controller;
use App\Models\AgencyTask;
use App\Models\Client;
use App\Models\Listing;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AgencyTask::query()
            ->where('organization_id', $request->attributes->get('organization_id'))
            ->with(['client:id,name', 'assignee:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }

        return response()->json($query->orderByRaw('due_at IS NULL, due_at ASC')->latest('id')->paginate(min(100, max(1, $request->integer('per_page', 30)))));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'listing_id' => ['nullable', 'integer', 'exists:listings,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'type' => ['required', Rule::enum(TaskType::class)],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:10000'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'due_at' => ['nullable', 'date'],
        ]);

        $organizationId = (int) $request->attributes->get('organization_id');
        $client = Client::where('organization_id', $organizationId)->findOrFail($data['client_id']);
        if (filled($data['product_id'] ?? null)) {
            Product::where('organization_id', $organizationId)->where('client_id', $client->id)->findOrFail($data['product_id']);
        }
        if (filled($data['listing_id'] ?? null)) {
            Listing::where('organization_id', $organizationId)->where('client_id', $client->id)->findOrFail($data['listing_id']);
        }
        if (filled($data['assigned_to'] ?? null)) {
            abort_unless(User::whereKey($data['assigned_to'])->whereHas('organizations', fn ($query) => $query->whereKey($organizationId))->exists(), 422, 'Assignee does not belong to this organization.');
        }

        $task = AgencyTask::create([
            ...$data,
            'organization_id' => $organizationId,
            'created_by' => $request->user()->id,
            'status' => $data['status'] ?? TaskStatus::TODO->value,
        ]);

        return response()->json(['data' => $task->load('client:id,name', 'assignee:id,name')], 201);
    }

    public function update(Request $request, AgencyTask $task): JsonResponse
    {
        abort_unless($task->organization_id === (int) $request->attributes->get('organization_id'), 404);
        $data = $request->validate([
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'due_at' => ['sometimes', 'nullable', 'date'],
        ]);

        if (filled($data['assigned_to'] ?? null)) {
            abort_unless(User::whereKey($data['assigned_to'])->whereHas('organizations', fn ($query) => $query->whereKey($task->organization_id))->exists(), 422, 'Assignee does not belong to this organization.');
        }
        if (($data['status'] ?? null) === TaskStatus::DONE->value) {
            $data['completed_at'] = now();
        } elseif (array_key_exists('status', $data)) {
            $data['completed_at'] = null;
        }

        $task->update($data);

        return response()->json(['data' => $task->fresh(['client:id,name', 'assignee:id,name'])]);
    }
}
