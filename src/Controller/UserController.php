<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/api/users', name: 'user_')]
class UserController extends AbstractController
{
    private $entityManager;
    private $userRepository;
    private $passwordHasher;
    private $validator;
    private $params;
    private $mailer;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator,
        ParameterBagInterface $params,
        MailerInterface $mailer
    ) {
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
        $this->validator = $validator;
        $this->params = $params;
        $this->mailer = $mailer;
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $users = $this->userRepository->findAll();
        return $this->json($users, JsonResponse::HTTP_OK, [], ['groups' => 'user']);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->json(['error' => 'User not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json($user, JsonResponse::HTTP_OK, [], ['groups' => 'user']);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return $this->json(['error' => 'Invalid JSON format'], JsonResponse::HTTP_BAD_REQUEST);
        }
        if (!isset($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Invalid email format'], JsonResponse::HTTP_BAD_REQUEST);
        }
        if (!isset($data['roles']) || !is_array($data['roles'])) {
            return $this->json(['error' => 'Roles must be an array'], JsonResponse::HTTP_BAD_REQUEST);
        }
        $plainPassword = $data['password'] ?? $this->generateSecurePassword();
        if (strlen($plainPassword) < 8) {
            return $this->json(['error' => 'Password must be at least 8 characters long'], JsonResponse::HTTP_BAD_REQUEST);
        }
        $user = new User();
        $user->setUsername($data['username'] ?? $data['email']);
        $user->setEmail($data['email']);
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);
        $user->setRoles($data['roles']);
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $from = $this->params->get('mailer_from');
        $email = (new Email())
            ->from($from)
            ->to($user->getEmail())
            ->subject('Your Account Details')
            ->text(
                "Your account has been created successfully.\n\n" .
                    "Email: {$user->getEmail()}\n" .
                    "Password: $plainPassword\n\n" .
                    "Please change your password after logging in."
            );
        $this->mailer->send($email);
        return $this->json(
            ['message' => 'User created successfully. Login details sent to email.'],
            JsonResponse::HTTP_CREATED
        );
    }

    private function generateSecurePassword(): string
    {
        $length = 12;
        $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lower = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $special = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        $password = $upper[random_int(0, strlen($upper) - 1)] .
            $lower[random_int(0, strlen($lower) - 1)] .
            $numbers[random_int(0, strlen($numbers) - 1)] .
            $special[random_int(0, strlen($special) - 1)];
        $all = $upper . $lower . $numbers . $special;
        for ($i = 4; $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }
        return str_shuffle($password);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->json(['error' => 'User not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json(['error' => 'Invalid JSON format'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Invalid email format'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['password']) && strlen($data['password']) < 8) {
            return $this->json(['error' => 'Password must be at least 8 characters long'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (isset($data['roles']) && !is_array($data['roles'])) {
            return $this->json(['error' => 'Roles must be an array'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user->setUsername($data['username'] ?? $user->getUsername());
        $user->setEmail($data['email'] ?? $user->getEmail());

        if (isset($data['password'])) {
            $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
            $user->setPassword($hashedPassword);
        }

        if (isset($data['roles'])) {
            $user->setRoles($data['roles']);
        }

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json($user, JsonResponse::HTTP_OK, [], ['groups' => 'user']);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return $this->json(['error' => 'User not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return $this->json(['message' => 'User deleted successfully'], JsonResponse::HTTP_OK);
    }
}
