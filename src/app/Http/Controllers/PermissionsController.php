<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Spatie\Permission\Models\Permission;

/**
 * Controlador para gestión de permisos
 */
class PermissionsController extends Controller
{
    /**
     * Crear un nuevo permiso
     *
     * @param StorePermissionRequest $request
     * @return JsonResponse
     */
    public function save(StorePermissionRequest $request): JsonResponse
    {
        $permission = Permission::create([
            'name' => $request->name,
            'guard_name' => $request->guard_name,
        ]);

        return response()->json([
            'message' => 'Permiso creado exitosamente',
            'permission' => $permission
        ], Response::HTTP_CREATED);
    }

    /**
     * Listar todos los permisos
     * Si el usuario tiene rol root, muestra todos los permisos
     * De lo contrario, muestra solo los permisos del guard del usuario
     *
     * @return JsonResponse
     */
    public function list(): JsonResponse
    {
        $user = auth()->user();
        
        // Si el usuario tiene rol root, mostrar todos los permisos
        if ($user->hasRole('root', 'cerrajero')) {
            $permissions = Permission::orderBy('guard_name')->orderBy('name')->get();
        } else {
            // Obtener el guard del primer rol del usuario
            $userRoles = $user->roles;
            if ($userRoles->isEmpty()) {
                $permissions = collect([]);
            } else {
                // Obtener todos los guards únicos de los roles del usuario
                $guards = $userRoles->pluck('guard_name')->unique();
                $permissions = Permission::whereIn('guard_name', $guards)
                    ->orderBy('guard_name')
                    ->orderBy('name')
                    ->get();
            }
        }

        return response()->json($permissions, Response::HTTP_OK);
    }

    /**
     * Mostrar un permiso específico
     *
     * @param Permission $permission
     * @return JsonResponse
     */
    public function show(Permission $permission): JsonResponse
    {
        return response()->json($permission, Response::HTTP_OK);
    }

    /**
     * Actualizar un permiso
     *
     * @param UpdatePermissionRequest $request
     * @param Permission $permission
     * @return JsonResponse
     */
    public function update(UpdatePermissionRequest $request, Permission $permission): JsonResponse
    {
        $permission->name = $request->name ?? $permission->name;
        $permission->guard_name = $request->guard_name ?? $permission->guard_name;
        $permission->save();

        return response()->json([
            'message' => 'Permiso actualizado exitosamente',
            'permission' => $permission
        ], Response::HTTP_OK);
    }
}
