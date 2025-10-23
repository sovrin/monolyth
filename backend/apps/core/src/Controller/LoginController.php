<?php
declare(strict_types=1);

namespace Thomann\Core\Controller;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use Thomann\Core\Dto\LoginRequest;
use Thomann\Core\Dto\LoginStatusView;
use Thomann\Core\Dto\UserView;

#[OA\Tag(name: 'Login')]
final class LoginController extends AbstractController {
    #[Route('/api/login/status', name: 'api_login_status', methods: ['GET'])]
    #[OA\Get(
        summary: 'Returns login status and (if logged in) basic user info',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login status',
                content: new OA\JsonContent(
                    ref: new Model(type: LoginStatusView::class, groups: ['login:read'])
                )
            )
        ]
    )]
    public function status (): Response {
        // Example: pretend “root” is currently logged in
        $user = new UserView();
        $user->username = 'root';
        $user->roles = ['admin', 'superadmin'];

        $view = new LoginStatusView();
        $view->loggedIn = true;
        $view->user = $user;

        return $this->json($view, 200, [], ['groups' => ['login:read']]);
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    #[OA\Post(
        summary: 'Attempt login',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: new Model(type: LoginRequest::class, groups: ['login:write'])
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login status',
                content: new OA\JsonContent(
                    ref: new Model(type: LoginStatusView::class, groups: ['login:read'])
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function login (#[MapRequestPayload] LoginRequest $request): Response {
        $view = new LoginStatusView();
        $view->loggedIn = $request->username === 'root';

        $user = new UserView();
        $user->username = $request->username;
        if ($view->loggedIn) {
            $user->roles = ['admin', 'superadmin'];
            $view->user = $user;
        }

        return $this->json($view, 200, [], ['groups' => ['login:read']]);
    }
}
