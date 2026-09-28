<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;

class FeedbackService
{
    public function __construct(protected PlaneService $plane) {}

    /**
     * Forward feedback with the authenticated author and server-configured classification.
     *
     * @param  array<string, mixed>  $data
     * @return array{id: string}
     */
    public function submit(array $data, User $user): array
    {
        $state = config('services.plane.feedback_state_id');
        $label = config('services.plane.feedback_labels.'.$data['type']);

        if (! $state || ! $label) {
            throw new ApiException(__('messages.plane.unavailable'), 503);
        }

        return $this->plane->createWorkItem([
            'name' => $data['title'],
            'description_html' => view('feedback.description', [
                'data' => $data,
                'user' => $user,
                'submittedAt' => now()->utc(),
            ])->render(),
            'state' => $state,
            'labels' => [$label],
        ]);
    }
}
