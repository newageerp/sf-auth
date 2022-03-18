<?php

namespace Newageerp\SfAuth\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Firebase\JWT\JWT;
use Newageerp\SfBaseEntity\Controller\OaBaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use OpenApi\Annotations as OA;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/app/nae-core/auth")
 */
class AuthController extends OaBaseController
{
    protected string $className = 'App\\Entity\\User';

    /**
     * @var ObjectRepository $userRepository
     */
    protected ObjectRepository $userRepository;

    /**
     * AuthController constructor.
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct($entityManager);
        $this->userRepository = $entityManager->getRepository($this->className);
    }

    /**
     * @Route(path="/createFirstUser", methods={"GET"})
     * @param Request $request
     * @return Response
     */
    public function createFirstUser(Request $request): Response
    {
        try {
            $request = $this->transformJsonBody($request);

            $users = $this->userRepository->findAll();
            if (count($users) === 0) {
                $objClass = $this->className;

                $user = new $objClass();
                $user->setLogin('info@newageerp.com');
                $user->setEmail('info@newageerp.com');
                $user->setPlainPassword('123456');
                $this->em->persist($user);
                $this->em->flush();

                return $this->json(['success' => true]);
            }
            return $this->json(['fail' => true]);
        } catch (\Exception $e) {
            $response = $this->json([
                'description' => $e->getMessage(),
                'f' => $e->getFile(),
                'l' => $e->getLine()

            ]);
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            return $response;
        }
    }

    /**
     * @OA\Post (operationId="NAEauthDoLogin")
     * @Route(path="/login", methods={"POST"})
     * @param Request $request
     * @return Response
     */
    public function login(Request $request): Response
    {
        try {
            $request = $this->transformJsonBody($request);

            $username = trim($request->get('username'));
            $password = trim($request->get('password'));

            $user = $this->userRepository->findOneBy(['login' => $username]);

            if (!$password || !$username) {
                throw new \Exception('Fill required fields');
            }

            if (!$user) {
                throw new \Exception('No such user');
            }

            if (!password_verify($password, $user->getPassword())) {
                throw new \Exception('Wrong password');
            }

            $token = JWT::encode(['id' => $user->getId(), 'sessionId' => ''], $_ENV['AUTH_KEY']);

            return $this->json(['token' => $token, 'user' => $user]);
        } catch (\Exception $e) {
            $response = $this->json([
                'description' => $e->getMessage(),
                'f' => $e->getFile(),
                'l' => $e->getLine()

            ]);
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            return $response;
        }
    }

    /**
     * @OA\Post (operationId="NAEauthGetProfile")
     * @Route(path="/get", methods={"POST"})
     * @param Request $request
     * @return Response
     */
    public function getInfo(Request $request): Response
    {
        $request = $this->transformJsonBody($request);

        try {
            if ($user = $this->findUser($request)) {
                $userData = [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'fullName' => $user->getFullName(),
                ];
                return $this->json($userData);
            }
        } catch (\Exception $e) {
            $response = $this->json([
                'description' => $e->getMessage(),
                'f' => $e->getFile(),
                'l' => $e->getLine()

            ]);
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            return $response;
        }

        $response = $this->json([
            'description' => "Wrong token",

        ]);
        $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        return $response;
    }
}
