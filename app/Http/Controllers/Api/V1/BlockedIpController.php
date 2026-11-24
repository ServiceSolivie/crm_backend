<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleEnum;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\BlockedIp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * « IP bloquées » (super admin): GET /blocked-ips, POST /blocked-ips,
 * DELETE /blocked-ips/{id}.
 */
class BlockedIpController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        return $this->success([
            'your_ip' => $request->ip(),
            'items' => BlockedIp::query()->with('creator:id,name')->latest('id')->get()->map(fn (BlockedIp $b) => $this->row($b))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'ip' => ['required', 'string', 'max:64', function (string $attribute, mixed $value, \Closure $fail) {
                [$address, $mask] = array_pad(explode('/', trim((string) $value), 2), 2, null);
                $valid = filter_var($address, FILTER_VALIDATE_IP) !== false
                    && ($mask === null || (ctype_digit($mask) && (int) $mask <= (str_contains($address, ':') ? 128 : 32)));
                if (! $valid) {
                    $fail('Adresse IP invalide (exemple : 176.185.163.76 ou 176.185.163.0/24).');
                }
            }],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $ip = trim($data['ip']);
        if (IpUtils::checkIp((string) $request->ip(), $ip)) {
            throw new ApiException('C’est votre propre adresse IP : vous seriez bloqué vous-même.', 422);
        }
        if (BlockedIp::query()->where('ip', $ip)->exists()) {
            throw new ApiException('Cette adresse est déjà bloquée.', 422);
        }

        $blocked = BlockedIp::create(['ip' => $ip, 'note' => $data['note'] ?? null, 'created_by' => $request->user()->id]);

        return $this->success($this->row($blocked->load('creator:id,name')), 'Adresse bloquée', 201);
    }

    public function destroy(Request $request, BlockedIp $blockedIp): JsonResponse
    {
        $this->authorizeManage($request);
        $blockedIp->delete();

        return $this->success(null, 'Adresse débloquée');
    }

    protected function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->hasRole(RoleEnum::SUPER_ADMIN->value), 403, 'Réservé au super admin.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(BlockedIp $b): array
    {
        return [
            'id' => $b->id,
            'ip' => $b->ip,
            'note' => $b->note,
            'created_by' => $b->creator?->name,
            'created_at' => $b->created_at?->toIso8601String(),
            'blocked_attempts' => $b->blocked_attempts,
            'last_attempt_at' => $b->last_attempt_at?->toIso8601String(),
        ];
    }
}
