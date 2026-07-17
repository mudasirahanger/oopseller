<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Client::query()
            ->where('organization_id', $request->attributes->get('organization_id'))
            ->withCount(['products', 'tasks', 'channelAccounts']);

        if ($request->filled('search')) {
            $search = trim(str_replace(['%', '_'], ' ', (string) $request->input('search')));
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', "%{$search}%")
                ->orWhere('contact_name', 'like', "%{$search}%")
                ->orWhere('contact_email', 'like', "%{$search}%"));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json($query->latest()->paginate(min(100, max(1, $request->integer('per_page', 20)))));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'onboarding', 'paused'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $client = Client::create([
            ...$data,
            'organization_id' => $request->attributes->get('organization_id'),
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)),
            'status' => $data['status'] ?? 'onboarding',
        ]);

        return response()->json(['data' => $client], 201);
    }

    public function show(Request $request, Client $client): JsonResponse
    {
        $this->assertAccess($request, $client);

        return response()->json(['data' => $client->load([
            'channelAccounts.marketplaces',
            'products.listings',
            'tasks.assignee',
        ])]);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $this->assertAccess($request, $client);
        $client->update($request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'onboarding', 'paused', 'archived'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]));

        return response()->json(['data' => $client->fresh()]);
    }

    public function destroy(Request $request, Client $client): JsonResponse
    {
        $this->assertAccess($request, $client);
        $this->requireOrganizationRole($request, 'owner', 'admin');
        abort_if($client->channelAccounts()->where('status', 'active')->exists(), 422, 'Disconnect active channel accounts before archiving this client.');
        $client->delete();

        return response()->json(status: 204);
    }

    private function assertAccess(Request $request, Client $client): void
    {
        abort_unless($client->organization_id === (int) $request->attributes->get('organization_id'), 404);
    }
}
