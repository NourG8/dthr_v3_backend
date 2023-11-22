<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesPermissionsController extends Controller
{

    public function index()
    {
        // $this->authorize('index', Role::class);

        $roles = Role::with('permissions')->get();
        return $this->successResponse($roles);
    }

    public function store(Request $request)
    {
        // $this->authorize('store', Role::class);

        $validatedData = $request->validate([
            'name' => 'required|unique:roles',
        ]);

        $role = Role::create(['name' => $validatedData['name']]);

        return $this->successResponse([
            'message' => 'role_success',
            'data' => $role
        ], 200);
    }

    public function show($id)
    {
        // $this->authorize('show', Role::class);

        $role = Role::with('permissions')->findOrFail($id);
        return $this->successResponse(['data' => $role], 200);
    }

    public function update(Request $request, $id)
    {
        // $this->authorize('update', Role::class);

        $validatedData = $request->validate([
            'name' => ["required", Rule::unique('roles')->ignore($id)],
        ]);

        $role = Role::findOrFail($id);
        $role->name = $validatedData['name'];
        $role->save();

        return $this->successResponse([
            'message' => 'role_success',
            'data' => $role
        ], 200);
    }

    public function destroy($id)
    {
        // $this->authorize('destroy', Role::class);

        $role = Role::findOrFail($id);
        $role->permissions()->delete();
        $role->delete();

        return $this->successResponse([
            'message' => 'role_success',
            'data' => $role
        ], 200);
    }

    public function addPermissions(Request $request, $id)
    {
        // $this->authorize('store', Role::class);

        $validatedData = $request->validate([
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role = Role::findOrFail($id);

        $permissions = Permission::whereIn('name', $validatedData['permissions'])->get();
        $role->syncPermissions($permissions);

        return $this->successResponse([
            'message' => 'role_success',
            'data' => $role
        ], 200);
    }

    
}
