<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Exceptions\AppException;
use App\Response;
use App\Services\AuthService;
use App\Session;
use App\View;

final class AuthController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly Session $session,
        private readonly View $view,
    ) {
    }

    public function showSetup(): void
    {
        $this->renderSetup();
    }

    public function setup(array $params, array $input): void
    {
        $username = (string) ($input['username'] ?? '');

        try {
            $this->auth->setupAdmin(
                $username,
                (string) ($input['password'] ?? ''),
                (string) ($input['password_confirm'] ?? '')
            );
        } catch (AppException $e) {
            $this->renderSetup($e->getMessage(), $username, $e->httpStatus());

            return;
        }

        $this->session->flash('notice', 'Admin created. Log in to start boxing your day.');
        Response::redirect('/login');
    }

    public function showLogin(): void
    {
        $this->renderLogin(null, '', 200, $this->session->pullFlash('notice'));
    }

    public function login(array $params, array $input): void
    {
        $username = (string) ($input['username'] ?? '');
        $adminId = $this->auth->authenticate($username, (string) ($input['password'] ?? ''));

        if ($adminId === null) {
            $this->renderLogin('Wrong username or password.', $username, 401);

            return;
        }

        $this->session->logIn($adminId);
        Response::redirect('/');
    }

    public function logout(): void
    {
        $this->session->logOut();
        Response::redirect('/login');
    }

    private function renderSetup(?string $error = null, string $username = '', int $status = 200): void
    {
        Response::html($this->view->render('setup', [
            'title'    => 'Set up',
            'error'    => $error,
            'username' => $username,
        ]), $status);
    }

    private function renderLogin(?string $error, string $username, int $status, ?string $notice = null): void
    {
        Response::html($this->view->render('login', [
            'title'    => 'Log in',
            'error'    => $error,
            'notice'   => $notice,
            'username' => $username,
        ]), $status);
    }
}
