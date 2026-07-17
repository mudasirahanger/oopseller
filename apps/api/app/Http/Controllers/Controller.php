<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    protected function requireOrganizationRole(Request $request, string ...$roles): void
    {
        if ($request->user()?->is_platform_admin) {
            return;
        }

        abort_unless(
            in_array((string) $request->attributes->get('organization_role'), $roles, true),
            403,
            'You do not have permission to perform this action.',
        );
    }
}
