<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\JwtService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use PHPMailer\PHPMailer\PHPMailer;
use Doctrine\ORM\EntityManagerInterface;

class LoginController
{
    private $userRepository;
    private $jwtService;
    private $passwordHasher;
    private $params;
    private $entityManager;

    public function __construct(
        UserRepository $userRepository,
        JwtService $jwtService,
        UserPasswordHasherInterface $passwordHasher,
        ParameterBagInterface $params,
        EntityManagerInterface $entityManager
    ) {
        $this->userRepository = $userRepository;
        $this->jwtService = $jwtService;
        $this->passwordHasher = $passwordHasher;
        $this->params = $params;
        $this->entityManager = $entityManager;
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
        if(!$user->isVerified()) {
            return new JsonResponse(['error' => 'This account is not verified'], JsonResponse::HTTP_UNAUTHORIZED);
        }
        if (!$this->passwordHasher->isPasswordValid($user, $credentials['password'])) {
            return new JsonResponse(['error' => 'Password invalid'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $environment = $this->params->get('kernel.environment');
        $mailerHost = $this->params->get("mailer_host");
        $mailerPassword = $this->params->get("mailer_password");
        $mailerUsername = $this->params->get("mailer_username");

        if ($environment === 'prod') {
            try {
                $twoFactorCode = random_int(100000, 999999);
                $expiresAt = new \DateTimeImmutable('+10 minutes');
                $user->setTwoFactorCode((string)$twoFactorCode);
                $user->setTwoFactorCodeExpiresAt($expiresAt);
            } catch (\Exception $e) {
                return new JsonResponse(
                    ['error' => 'Failed to generate 2FA code. Please try again.'],
                    JsonResponse::HTTP_INTERNAL_SERVER_ERROR
                );
            }
            try {
                $this->entityManager->persist($user);
                $this->entityManager->flush();
            } catch (\Exception $e) {
                return new JsonResponse(
                    ['error' => 'Failed to save the user. Error: ' . $e->getMessage()],
                    JsonResponse::HTTP_INTERNAL_SERVER_ERROR
                );
            }
                         
            $mail = new PHPMailer();
            $mail->isSMTP();
            $mail->Host = $mailerHost;
            $mail->SMTPAuth = true;
            $mail->Username = $mailerUsername;
            $mail->Password = $mailerPassword;
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            $to = $user->getEmail();
            $mail->setFrom($mailerUsername, 'Verify2fa');
            $mail->addAddress($to);
            $mail->Subject = 'Your Two-Factor Authentication Code';
            $mail->Body = "Your verification code is: $twoFactorCode. It will expire in 10 minutes.";
            
            if ($mail->send()) {
                return new JsonResponse(
                    ['message' => 'Verification code sent to your email.'],
                    JsonResponse::HTTP_OK
                );
            } else {
                return new JsonResponse(
                    ['message' => 'Verification code could not be sent to your email. Please try again later.'],
                    JsonResponse::HTTP_INTERNAL_SERVER_ERROR
                );
            }
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
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        return new JsonResponse(['token' => $token], JsonResponse::HTTP_OK);
    }
}