<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function update(Request $request, Organization $organization): JsonResponse
    {
        abort_unless($organization->id === (int) $request->attributes->get('organization_id'), 404);
        $this->requireOrganizationRole($request, 'owner', 'admin');

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'slug' => [
                'required',
                'string',
                'max:120',
                Rule::unique('organizations')->ignore($organization->id),
            ],
            'timezone' => 'sometimes|string|timezone|max:64',
            'currency' => 'sometimes|string|size:3',
        ]);

        if (isset($validated['currency'])) {
            $validated['currency'] = strtoupper($validated['currency']);
        }

        $organization->update($validated);

        return response()->json(['data' => $organization, 'message' => 'Organization updated successfully.']);
    }
}
