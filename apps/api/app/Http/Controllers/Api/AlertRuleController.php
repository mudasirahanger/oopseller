<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlertRule;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertRuleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            AlertRule::query()
                ->where('organization_id', $request->attributes->get('organization_id'))
                ->with('client:id,name')
                ->latest()
                ->paginate(min(100, max(1, $request->integer('per_page', 30)))),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'name' => ['required', 'string', 'max:150'],
            'metric' => ['required', 'string', 'max:150'],
            'operator' => ['required', 'in:greater_than,greater_than_or_equal,less_than,less_than_or_equal,equal,not_equal,percentage_drop,percentage_increase'],
            'threshold' => ['required', 'numeric'],
            'scope' => ['nullable', 'array'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['in:email,slack,webhook,in_app'],
            'cooldown_minutes' => ['nullable', 'integer', 'min:60', 'max:43200'],
            'enabled' => ['nullable', 'boolean'],
        ]);

        $organizationId = (int) $request->attributes->get('organization_id');
        if (filled($data['client_id'] ?? null)) {
            Client::where('organization_id', $organizationId)->findOrFail($data['client_id']);
        }

        $rule = AlertRule::create([
            ...$data,
            'organization_id' => $organizationId,
            'cooldown_minutes' => $data['cooldown_minutes'] ?? 1440,
            'enabled' => $data['enabled'] ?? true,
        ]);

        return response()->json(['data' => $rule->load('client:id,name')], 201);
    }

    public function update(Request $request, AlertRule $alertRule): JsonResponse
    {
        abort_unless($alertRule->organization_id === (int) $request->attributes->get('organization_id'), 404);
        $alertRule->update($request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'threshold' => ['sometimes', 'numeric'],
            'scope' => ['sometimes', 'nullable', 'array'],
            'channels' => ['sometimes', 'array', 'min:1'],
            'channels.*' => ['in:email,slack,webhook,in_app'],
            'cooldown_minutes' => ['sometimes', 'integer', 'min:60', 'max:43200'],
            'enabled' => ['sometimes', 'boolean'],
        ]));

        return response()->json(['data' => $alertRule->fresh('client:id,name')]);
    }
}
