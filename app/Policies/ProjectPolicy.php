<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Every ability on a project boils down to ownership: the project must
     * belong to the authenticated user. Routes under /projects/{project}
     * run this through the `can:view,project` middleware.
     */
    public function view(User $user, Project $project): bool
    {
        return $this->owns($user, $project);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->owns($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->owns($user, $project);
    }

    public function create(User $user): bool
    {
        return true;
    }

    private function owns(User $user, Project $project): bool
    {
        return $user->id === $project->user_id;
    }
}
