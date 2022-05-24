<?php

namespace Newageerp\SfAuth\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Firebase\JWT\JWT;
use Newageerp\SfBaseEntity\Controller\OaBaseController;
use Newageerp\SfMailjet\Service\MailjetService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use OpenApi\Annotations as OA;
use Symfony\Component\Routing\Annotation\Route;
use Mailjet\Client;
use Mailjet\Resources;


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

    protected MailjetService $mailjetService;

    /**
     * AuthController constructor.
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager, EventDispatcherInterface $eventDispatcher, MailjetService $mailjetService)
    {
        parent::__construct($entityManager, $eventDispatcher);
        $this->userRepository = $entityManager->getRepository($this->className);
        $this->mailjetService = $mailjetService;
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
                $this->getEm()->persist($user);
                $this->getEm()->flush();

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

            $user = $this->userRepository->findOneBy(['login' => $username, 'disabled' => false]);

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
     * @Route(path="/remind", methods={"POST"})
     * @param Request $request
     * @return Response
     */
    public function remind(Request $request): Response
    {
        try {
            $request = $this->transformJsonBody($request);

            $username = trim($request->get('username'));

            $user = $this->userRepository->findOneBy(['login' => $username, 'disabled' => false]);
            if (!$user) {
                $user = $this->userRepository->findOneBy(['email' => $username, 'disabled' => false]);
            }
            if ($user) {
                $password = $this->randomStr(6);
                $user->setPlainPassword($password);
                $this->em->flush();


                $mj = $this->mailjetService->getClientV31();
                $body = [
                    'Messages' => [
                        [
                            'From' => [
                                'Email' => $_ENV['NAE_SFS_MAILJET_DEFAULT_SENDER'],
                                'Name' => "NewAgeErp"
                            ],
                            'To' => [
                                [
                                    'Email' => $user->getEmail(),
                                ]
                            ],
                            'TemplateID' => 3957691,
                            'Variables' => [
                                'login' => $user->getLogin(),
                                'password' => $password,
                            ]
                        ]
                    ]
                ];
                $response = $mj->post(Resources::$Email, ['body' => $body]);
            }
            return $this->json(['success' => 1]);
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
//                $userData = [
//                    'id' => $user->getId(),
//                    'email' => $user->getEmail(),
//                    'fullName' => $user->getFullName(),
//                ];
                return $this->json($user);
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

    public function randomStr(
        $length,
        $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
    )
    {
        $str = '';
        $max = mb_strlen($keyspace, '8bit') - 1;
        if ($max < 1) {
            throw new \Exception('$keyspace must be at least two characters long');
        }
        for ($i = 0; $i < $length; ++$i) {
            $str .= $keyspace[random_int(0, $max)];
        }
        return $str;
    }
}
