<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\TeamFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Team\AssignTeamMemberRequest;
use App\Http\Requests\Team\StoreTeamRequest;
use App\Http\Requests\Team\UpdateTeamRequest;
use App\Http\Resources\TeamResource;
use App\Http\Resources\UserResource;
use App\Models\Team;
use App\Models\User;
use App\Services\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function __construct(protected TeamService $teamService) {}

    public function index(Request $request, TeamFilter $filters): JsonResponse
    {
        $this->authorize('viewAny', Team::class);

        $perPage = (int) $request->integer('per_page', 15);

        $teams = $this->teamService->paginateForUser($request->user(), $filters, $perPage);

        return $this->success(TeamResource::collection($teams));
    }

    public function store(StoreTeamRequest $request): JsonResponse
    {
        $this->authorize('create', Team::class);

        $team = $this->teamService->createTeam($request->validated());

        $team->load('manager');

        return $this->created(new TeamResource($team), 'Team created successfully');
    }

    public function show(Team $team): JsonResponse
    {
        $this->authorize('view', $team);

        $team->load('manager')->loadCount('members');

        return $this->success(new TeamResource($team));
    }

    public function update(UpdateTeamRequest $request, Team $team): JsonResponse
    {
        $this->authorize('update', $team);

        $team = $this->teamService->updateTeam($team, $request->validated());

        $team->load('manager')->loadCount('members');

        return $this->success(new TeamResource($team), 'Team updated successfully');
    }

    public function destroy(Team $team): JsonResponse
    {
        $this->authorize('delete', $team);

        $this->teamService->deleteTeam($team);

        return $this->noContent('Team deleted successfully');
    }

    public function members(Request $request, Team $team): JsonResponse
    {
        $this->authorize('view', $team);

        $members = $this->teamService->members($team, (int) $request->integer('per_page', 15));

        return $this->success(UserResource::collection($members));
    }

    public function addMember(AssignTeamMemberRequest $request, Team $team): JsonResponse
    {
        $this->authorize('manageMembers', $team);

        $user = User::query()->findOrFail($request->validated('user_id'));

        $user = $this->teamService->addMember($team, $user);

        return $this->success(new UserResource($user), 'Member added to team successfully');
    }

    public function removeMember(Team $team, User $user): JsonResponse
    {
        $this->authorize('manageMembers', $team);

        $user = $this->teamService->removeMember($team, $user);

        return $this->success(new UserResource($user), 'Member removed from team successfully');
    }

    public function statistics(Team $team): JsonResponse
    {
        $this->authorize('viewStatistics', $team);

        return $this->success($this->teamService->statistics($team));
    }
}
