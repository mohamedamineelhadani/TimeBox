<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Response;
use App\Services\TaskService;

/** JSON API used by public/assets/js/app.js */
final class TaskController
{
    public function __construct(private readonly TaskService $tasks)
    {
    }

    public function store(array $params, array $input): void
    {
        Response::json($this->tasks->create($input), 201);
    }

    public function update(array $params, array $input): void
    {
        Response::json($this->tasks->update((int) $params['id'], $input));
    }

    public function destroy(array $params): void
    {
        $this->tasks->delete((int) $params['id']);
        Response::json(['ok' => true]);
    }
}
