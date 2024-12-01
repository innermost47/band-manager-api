<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\JwtService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;


class LoginController
{
    private $userRepository;
    private $jwtService;
    private $passwordHasher;
    private $params;
    private $mailer;

    public function __construct(
        UserRepository $userRepository,
        JwtService $jwtService,
        UserPasswordHasherInterface $passwordHasher,
        ParameterBagInterface $params,
        MailerInterface $mailer
    ) {
        $this->userRepository = $userRepository;
        $this->jwtService = $jwtService;
        $this->passwordHasher = $passwordHasher;
        $this->params = $params;
        $this->mailer = $mailer;
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $credentials = json_decode($request->getContent(), true);

        if (!isset($credentials['email']) || !isset($credentials['password'])) {
            return new JsonResponse(['error' => 'Invalid credentials'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $credentials['email']]);
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $credentials['password'])) {
            return new JsonResponse(['error' => 'Password invalid'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $environment = $this->params->get('kernel.environment');
        $from = $this->params->get('mailer_from');

        if ($environment === 'prod') {
            $twoFactorCode = random_int(100000, 999999);
            $expiresAt = new \DateTimeImmutable('+10 minutes');

            $user->setTwoFactorCode((string)$twoFactorCode);
            $user->setTwoFactorCodeExpiresAt($expiresAt);

            $this->userRepository->save($user);

            $email = (new Email())
                ->from($from)
                ->to($user->getEmail())
                ->subject('Your Two-Factor Authentication Code')
                ->text("Your verification code is: $twoFactorCode. It will expire in 10 minutes.");

            $this->mailer->send($email);

            return new JsonResponse(['message' => 'Verification code sent to your email.'], JsonResponse::HTTP_OK);
        }

        $token = $this->jwtService->createToken($user->getEmail());
        return new JsonResponse(['token' => $token], JsonResponse::HTTP_OK);
    }

    #[Route('/verify-2fa', name: 'verify_2fa', methods: ['POST'])]
    public function verify2FA(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email']) || !isset($data['code'])) {
            return new JsonResponse(['error' => 'Invalid input'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $data['email']]);

        if (!$user || $user->getTwoFactorCode() !== $data['code']) {
            return new JsonResponse(['error' => 'Invalid code'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        if ($user->getTwoFactorCodeExpiresAt() < new \DateTimeImmutable()) {
            return new JsonResponse(['error' => 'Code expired'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $token = $this->jwtService->createToken($user->getEmail());


        $user->setTwoFactorCode(null);
        $user->setTwoFactorCodeExpiresAt(null);
        $this->userRepository->save($user);

        return new JsonResponse(['token' => $token], JsonResponse::HTTP_OK);
    }
}
