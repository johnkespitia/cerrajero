<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Controlador para gestión de usuarios
 */
class UserController extends Controller
{
    /**
     * Login de usuario
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function apiLogin(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(
                ['message' => 'Credenciales inválidas'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        if (!$user->active) {
            return response()->json(
                ['message' => 'Usuario inactivo'],
                Response::HTTP_FORBIDDEN
            );
        }

        $token = $user->createToken($user->email);
        
        return response()->json([
            'token' => $token->plainTextToken,
            'user' => $user->load('roles')
        ], Response::HTTP_OK);
    }

    /**
     * Crear un nuevo usuario
     *
     * @param StoreUserRequest $request
     * @return JsonResponse
     */
    public function save(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'active' => $request->active ?? true,
        ]);

        // Asignar rol
        if ($request->rol) {
            $role = Role::findOrFail($request->rol);
            // Para asignar roles de diferentes guards, configuramos temporalmente el guard del modelo User
            $originalGuard = $user->guard_name;
            $user->guard_name = $role->guard_name;
            $user->assignRole($role);
            $user->guard_name = $originalGuard;
        }

        // Asignar superior
        if ($request->superior) {
            $user->superior()->attach($request->superior);
        }

        return response()->json([
            'message' => 'Usuario creado exitosamente',
            'user' => $user->load(['roles', 'superior'])
        ], Response::HTTP_CREATED);
    }

    /**
     * Actualizar un usuario
     *
     * @param UpdateUserRequest $request
     * @param User $user
     * @return JsonResponse
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->name = $request->name ?? $user->name;
        $user->email = $request->email ?? $user->email;
        
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        
        if ($request->has('active')) {
            $user->active = $request->active;
        }
        
        $user->save();

        // Actualizar rol
        if ($request->has('rol')) {
            if ($request->rol) {
                $role = Role::findOrFail($request->rol);
                // Para sincronizar roles de diferentes guards, configuramos temporalmente el guard del modelo User
                $originalGuard = $user->guard_name;
                $user->guard_name = $role->guard_name;
                $user->syncRoles([$role]);
                $user->guard_name = $originalGuard;
            } else {
                // Si no se especifica rol, solo sincronizamos roles del guard por defecto
                $user->syncRoles([]);
            }
        }

        // Actualizar superior
        if ($request->has('superior')) {
            if ($request->superior) {
                $user->superior()->sync([$request->superior]);
            } else {
                $user->superior()->detach();
            }
        }

        return response()->json([
            'message' => 'Usuario actualizado exitosamente',
            'user' => $user->load(['roles', 'superior', 'dependency'])
        ], Response::HTTP_OK);
    }

    /**
     * Listar todos los usuarios
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        $users = User::with(['roles.permissions', 'superior', 'dependency'])
            ->get();

        return response()->json($users, Response::HTTP_OK);
    }

    /**
     * Listar usuarios básicos (solo para selectores, sin información sensible)
     * No requiere permiso específico, solo autenticación
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listBasic(Request $request): JsonResponse
    {
        $users = User::where('active', true)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json($users, Response::HTTP_OK);
    }

    /**
     * Mostrar un usuario específico
     *
     * @param User $user
     * @return JsonResponse
     */
    public function show(User $user): JsonResponse
    {
        $user->load([
            'roles.permissions',
            'permissions',
            'superior',
            'dependency'
        ]);

        return response()->json($user, Response::HTTP_OK);
    }

    /**
     * Obtener datos del usuario autenticado
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function mydata(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load([
            'roles.permissions',
            'permissions',
            'superior',
            'dependency'
        ]);

        return response()->json($user, Response::HTTP_OK);
    }

    /**
     * Asignar un rol a un usuario
     *
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     */
    public function assignRole(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'rol' => ['required', 'exists:roles,id'],
        ]);

        $role = Role::findOrFail($request->rol);
        
        // Para asignar roles de diferentes guards, necesitamos usar el objeto Role directamente
        // y configurar temporalmente el guard del modelo User
        $originalGuard = $user->guard_name;
        $user->guard_name = $role->guard_name;
        $user->assignRole($role);
        $user->guard_name = $originalGuard;

        return response()->json([
            'message' => 'Rol asignado exitosamente',
            'user' => $user->load(['roles', 'superior'])
        ], Response::HTTP_OK);
    }

    /**
     * Remover un rol de un usuario
     *
     * @param Request $request
     * @param User $user
     * @param int $rol
     * @return JsonResponse
     */
    public function removeRole(Request $request, User $user, int $rol): JsonResponse
    {
        $role = Role::findOrFail($rol);
        
        // Para remover roles de diferentes guards, configuramos temporalmente el guard del modelo User
        $originalGuard = $user->guard_name;
        $user->guard_name = $role->guard_name;
        $user->removeRole($role);
        $user->guard_name = $originalGuard;

        return response()->json([
            'message' => 'Rol removido exitosamente',
            'user' => $user->load(['roles', 'superior'])
        ], Response::HTTP_OK);
    }

    /**
     * Asignar un superior a un usuario
     *
     * @param Request $request
     * @param User $user
     * @return JsonResponse
     */
    public function assignSuperior(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'superior' => ['required', 'exists:users,id'],
        ]);

        $user->superior()->syncWithoutDetaching([$request->superior]);

        return response()->json([
            'message' => 'Superior asignado exitosamente',
            'user' => $user->load(['roles', 'superior'])
        ], Response::HTTP_OK);
    }

    /**
     * Remover un superior de un usuario
     *
     * @param Request $request
     * @param User $user
     * @param int $superior
     * @return JsonResponse
     */
    public function removeSuperior(Request $request, User $user, int $superior): JsonResponse
    {
        $user->superior()->detach($superior);

        return response()->json([
            'message' => 'Superior removido exitosamente',
            'user' => $user->load(['roles', 'superior'])
        ], Response::HTTP_OK);
    }

    /**
     * Verificar si el usuario tiene un permiso específico
     *
     * @param Request $request
     * @param string $guard
     * @param string $permission
     * @return JsonResponse
     */
    public function cani(Request $request, string $guard, string $permission): JsonResponse
    {
        $hasPermission = $request->user()->hasPermissionTo($permission, $guard);

        if ($hasPermission) {
            return response()->json([
                'message' => "Tienes permiso para: {$permission}",
                'has_permission' => true
            ], Response::HTTP_OK);
        }

        return response()->json([
            'message' => "No tienes permiso para: {$permission}",
            'has_permission' => false
        ], Response::HTTP_FORBIDDEN);
    }
}
