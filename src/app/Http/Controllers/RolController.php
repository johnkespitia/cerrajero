<?php

namespace App\Http\Controllers;

use App\Http\Requests\RolePermissionRequest;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Role;

/**
 * Controlador para gestión de roles
 */
class RolController extends Controller
{
    /**
     * Crear un nuevo rol
     *
     * @param StoreRoleRequest $request
     * @return JsonResponse
     */
    public function save(StoreRoleRequest $request): JsonResponse
    {
        $role = Role::create([
            'name' => $request->name,
            'guard_name' => $request->guard_name,
        ]);

        return response()->json([
            'message' => 'Rol creado exitosamente',
            'role' => $role->load('permissions')
        ], Response::HTTP_CREATED);
    }

    /**
     * Listar todos los roles
     * Si el usuario tiene rol root, muestra todos los roles
     * De lo contrario, muestra solo los roles del guard del usuario
     *
     * @return JsonResponse
     */
    public function list(): JsonResponse
    {
        $user = auth()->user();
        
        // Si el usuario tiene rol root, mostrar todos los roles
        if ($user->hasRole('root', 'cerrajero')) {
            $roles = Role::with('permissions')
                ->orderBy('guard_name')
                ->orderBy('name')
                ->get();
        } else {
            // Obtener el guard del primer rol del usuario
            $userRoles = $user->roles;
            if ($userRoles->isEmpty()) {
                $roles = collect([]);
            } else {
                // Obtener todos los guards únicos de los roles del usuario
                $guards = $userRoles->pluck('guard_name')->unique();
                $roles = Role::with('permissions')
                    ->whereIn('guard_name', $guards)
                    ->orderBy('guard_name')
                    ->orderBy('name')
                    ->get();
            }
        }

        return response()->json($roles, Response::HTTP_OK);
    }

    /**
     * Mostrar un rol específico
     *
     * @param Role $rol
     * @return JsonResponse
     */
    public function show(Role $rol): JsonResponse
    {
        $rol->load('permissions');

        return response()->json($rol, Response::HTTP_OK);
    }

    /**
     * Actualizar un rol
     *
     * @param UpdateRoleRequest $request
     * @param Role $rol
     * @return JsonResponse
     */
    public function update(UpdateRoleRequest $request, Role $rol): JsonResponse
    {
        $rol->name = $request->name ?? $rol->name;
        $rol->guard_name = $request->guard_name ?? $rol->guard_name;
        $rol->save();

        return response()->json([
            'message' => 'Rol actualizado exitosamente',
            'role' => $rol->load('permissions')
        ], Response::HTTP_OK);
    }

    /**
     * Otorgar uno o múltiples permisos a un rol
     *
     * @param RolePermissionRequest $request
     * @param Role $rol
     * @return JsonResponse
     */
    public function grantPermission(RolePermissionRequest $request, Role $rol): JsonResponse
    {
        // Si se envía un array de permisos
        if ($request->has('permissions') && is_array($request->permissions)) {
            $permissions = $request->permissions;
            $rol->givePermissionTo($permissions);
            
            $count = count($permissions);
            return response()->json([
                'message' => $count === 1 
                    ? 'Permiso otorgado exitosamente' 
                    : "{$count} permisos otorgados exitosamente",
                'role' => $rol->load('permissions')
            ], Response::HTTP_OK);
        }
        
        // Mantener compatibilidad con el formato anterior (un solo permiso)
        $rol->givePermissionTo($request->permission);

        return response()->json([
            'message' => 'Permiso otorgado exitosamente',
            'role' => $rol->load('permissions')
        ], Response::HTTP_OK);
    }

    /**
     * Revocar un permiso de un rol
     *
     * @param RolePermissionRequest $request
     * @param Role $rol
     * @return JsonResponse
     */
    public function revokePermission(RolePermissionRequest $request, Role $rol): JsonResponse
    {
        $rol->revokePermissionTo($request->permission);

        return response()->json([
            'message' => 'Permiso revocado exitosamente',
            'role' => $rol->load('permissions')
        ], Response::HTTP_OK);
    }
}
