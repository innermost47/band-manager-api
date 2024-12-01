<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/password', name: 'password_')]
class ForgotPasswordController extends AbstractController
{
    private $entityManager;
    private $userRepository;
    private $mailer;

    public function __construct(EntityManagerInterface $entityManager, UserRepository $userRepository, MailerInterface $mailer)
    {
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->mailer = $mailer;
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

        $resetToken = Uuid::v4()->toRfc4122();
        $user->setTwoFactorCode($resetToken);
        $user->setTwoFactorCodeExpiresAt(new \DateTimeImmutable('+1 hour'));

        $this->entityManager->flush();

        $resetUrl = $this->generateUrl('password_reset', ['token' => $resetToken], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new Email())
            ->from($_ENV['MAILER_FROM'])
            ->to($user->getEmail())
            ->subject('Password Reset Request')
            ->html(sprintf('<p>Click <a href="%s">here</a> to reset your password. This link will expire in 1 hour.</p>', $resetUrl));

        $this->mailer->send($email);

        return $this->json(['message' => 'Password reset email sent'], JsonResponse::HTTP_OK);
    }

    #[Route('/reset/{token}', name: 'reset', methods: ['POST'])]
    public function resetPassword(Request $request, string $token, UserPasswordHasherInterface $passwordHasher, ValidatorInterface $validator): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['password']) || empty($data['password'])) {
            return $this->json(['error' => 'Password is required'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['twoFactorCode' => $token]);

        if (!$user) {
            return $this->json(['error' => 'Invalid or expired token'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if ($user->getTwoFactorCodeExpiresAt() < new \DateTimeImmutable()) {
            return $this->json(['error' => 'Token has expired'], JsonResponse::HTTP_BAD_REQUEST);
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
