<?php

namespace App\Service;

use App\Repository\ProjectRepository;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ProjectService
{
    private $repository;

    public function __construct(
        ProjectRepository $repository,
    ) {
        $this->repository = $repository;
    }

    public function verifyProjectAccess($project, $currentUser): void
    {
        if (!$currentUser || !$project->getMembers()->contains($currentUser)) {
            throw new AccessDeniedException('Access denied to this project.');
        }
    }
}
