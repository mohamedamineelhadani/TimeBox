<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Response;
use App\Services\TaskService;
use App\View;

final class PageController
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly View $view,
    ) {
    }

    public function app(): void
    {
        Response::html($this->view->render('app', [
            'title'    => 'Today',
            'scripts'  => ['js/app.js'],
            'tasks'    => $this->tasks->list(),
            'dayStart' => TaskService::DAY_START,
            'dayEnd'   => TaskService::DAY_END,
            'snap'     => TaskService::SNAP,
        ]));
    }
}
