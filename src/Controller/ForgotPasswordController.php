<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\EmailService;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Route('/password', name: 'password_')]
class ForgotPasswordController extends AbstractController
{
    private $entityManager;
    private $userRepository;
    private $emailService;

    public function __construct(EntityManagerInterface $entityManager, UserRepository $userRepository, ParameterBagInterface $params)
    {
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->emailService = new EmailService($params);
    }

    #[Route('/forgot', name: 'forgot', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email']) || empty($data['email'])) {
            return $this->json(['error' => 'Email is required'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $data['email']]);

        if (!$user) {
            return $this->json(['error' => 'User not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->setTwoFactorCode($verificationCode);
        $user->setTwoFactorCodeExpiresAt(new \DateTimeImmutable('+15 minutes'));

        $this->entityManager->flush();

        $recipientEmail = $user->getEmail();
        $emailData = $this->emailService->getPasswordResetVerificationEmail($verificationCode);

        $isEmailSent = $this->emailService->sendEmail(
            $recipientEmail,
            $emailData['subject'],
            $emailData['body'],
            $emailData['altBody'],
            $emailData['fromSubject']
        );

        if ($isEmailSent) {
            return new JsonResponse(
                ['message' => 'Verification code sent successfully'],
                JsonResponse::HTTP_OK
            );
        } else {
            return new JsonResponse(
                ['message' => 'Verification code could not be sent. Please try again later.'],
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/reset', name: 'reset', methods: ['POST'])]
    public function resetPassword(Request $request, UserPasswordHasherInterface $passwordHasher, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email']) || empty($data['email'])) {
            return $this->json(['error' => 'Email is required'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!isset($data['code']) || empty($data['code'])) {
            return $this->json(['error' => 'Verification code is required'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!isset($data['password']) || empty($data['password'])) {
            return $this->json(['error' => 'New password is required'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy([
            'email' => $data['email'],
            'twoFactorCode' => $data['code']
        ]);

        if (!$user) {
            return $this->json(['error' => 'Invalid verification code'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (!$user->getTwoFactorCodeExpiresAt() || $user->getTwoFactorCodeExpiresAt() < new \DateTimeImmutable()) {
            return $this->json(['error' => 'Verification code has expired'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $constraints = new \Symfony\Component\Validator\Constraints\NotCompromisedPassword();
        $errors = $validator->validate($data['password'], $constraints);

        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);
        $user->setTwoFactorCode(null);
        $user->setTwoFactorCodeExpiresAt(null);

        $this->entityManager->flush();

        return $this->json(['message' => 'Password reset successfully'], JsonResponse::HTTP_OK);
    }
}
