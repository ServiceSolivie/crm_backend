<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\UserFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\AssignUserRoleRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Requests\User\UpdateUserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(protected UserService $userService) {}

    public function index(Request $request, UserFilter $filters): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $perPage = (int) $request->integer('per_page', 15);

        $users = $this->userService->paginateFiltered($filters, $perPage);

        return $this->success(UserResource::collection($users));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = $this->userService->createUser($request->validated());

        return $this->created(new UserResource($user), 'User created successfully');
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $user->load(['roles', 'team']);

        return $this->success(new UserResource($user));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user = $this->userService->updateUser($user, $request->validated());

        return $this->success(new UserResource($user->load(['roles', 'team'])), 'User updated successfully');
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $this->userService->deleteUser($user);

        return $this->noContent('User deleted successfully');
    }

    public function assignRole(AssignUserRoleRequest $request, User $user): JsonResponse
    {
        $this->authorize('assignRole', $user);

        $user = $this->userService->assignRole($user, $request->validated('role'));

        return $this->success(new UserResource($user), 'Role assigned successfully');
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        $this->authorize('updateStatus', $user);

        $user = $this->userService->setActive($request->user(), $user, (bool) $request->validated('is_active'));

        return $this->success(new UserResource($user), 'User status updated successfully');
    }
}
