<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;
use App\Models\Role;
use App\Models\Permission;

// use Spatie\Permission\Models\Permission;
// use Spatie\Permission\Models\Role;

class PermissionsController extends Controller
{

    public function index()
    {
        // $this->authorize('index', Role::class);

        $permissions = Permission::all();
        return $this->successResponse($permissions);
    }

    public function store(Request $request)
    {
        // $this->authorize('store', Role::class);

        $validatedData = $request->validate([
            'name' => 'required|unique:permissions,name'
        ]);

        $permission = Permission::create($validatedData);

        return $this->successResponse([
            'message' => 'permission_success',
            'data' => $permission
        ], 201);
    }

    public function storeMany(Request $request)
    {
        // $this->authorize('store', Role::class);

        $validatedData = $request->validate([
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'required|unique:permissions,name'
        ]);

        $permissions = [];
        foreach ($validatedData['permissions'] as $permission) {
            $permission = Permission::create($permission);
            $permissions[] = $permission;
        }

        return $this->successResponse([
            'message' => 'permission_success',
            'data' => $permissions
        ], 201);
    }

    public function show($id)
    {
        // $this->authorize('show', Role::class);

        $permission = Permission::findOrFail($id);
        return $this->successResponse($permission);
    }

    public function update(Request $request, $id)
    {
        // $this->authorize('update', Role::class);

        $validatedData = $request->validate([
            'name' => ['required', Rule::unique('permissions', 'name')->ignore($id)]
        ]);

        $permission = Permission::findOrFail($id);
        $permission->name = $validatedData['name'];
        $permission->save();

        return $this->successResponse([
            'message' => 'permission_success',
            'data' => $permission
        ]);
    }

    public function destroy($id)
    {   
        // $this->authorize('destroy', Role::class);

        $permission = Permission::findOrFail($id);
        $permission->delete();

        return $this->successResponse([
            'message' => 'permission_success',
            'data' => $permission
        ]);
    }

    public function getArchivedPermissions()
    {
        // $this->authorize('index', Permission::class);

        $archivedPermissions = Permission::onlyTrashed()->get();
        return $this->successResponse($archivedPermissions);
    }

    public function restorePermission($id)
    {
         // $this->authorize('index', Permission::class);

        $permission = Permission::withTrashed()->findOrFail($id);

        if ($permission->restore()) {
            return $this->successResponse([
                'message' => 'permission restored successfully',
                'data' => $permission
            ], 200);
        }

        return $this->errorResponse('permission is not archived or restoration failed', 400);
    }

}
