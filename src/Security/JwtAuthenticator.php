<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Lcobucci\Clock\SystemClock;
use App\Service\JwtService;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthenticator extends AbstractAuthenticator
{
    private $jwtConfig;
    private $params;
    private $jwtService;
    private $userRepository;

    public function __construct(ParameterBagInterface $params, JwtService $jwtService, UserRepository $userRepository)
    {
        $this->params = $params;
        $secret = $this->params->get('jwt_secret');
        $this->jwtConfig = Configuration::forSymmetricSigner(
            new \Lcobucci\JWT\Signer\Hmac\Sha256(),
            InMemory::plainText($secret)
        );
        $this->jwtService = $jwtService;
        $this->userRepository = $userRepository;
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization');
    }

    public function authenticate(Request $request): Passport
    {
        $jwt = str_replace('Bearer ', '', $request->headers->get('Authorization'));
        try {
            $token = $this->jwtConfig->parser()->parse($jwt);
            $this->jwtConfig->validator()->assert($token, ...[
                new \Lcobucci\JWT\Validation\Constraint\SignedWith(
                    $this->jwtConfig->signer(),
                    $this->jwtConfig->signingKey()
                ),
                new \Lcobucci\JWT\Validation\Constraint\StrictValidAt(
                    new SystemClock(new \DateTimeZone('UTC'))
                )
            ]);
            $claims = $token->claims();
            $email = $claims->get('email');
            $user = $this->userRepository->findOneBy(['email' => $email]);
            if (!$user) {
                throw new AuthenticationException('User not found');
            }
            if (!$user->isVerified()) {
                throw new AuthenticationException('User is not verified');
            }
            return new SelfValidatingPassport(new UserBadge($email));
        } catch (\Lcobucci\JWT\Validation\RequiredConstraintsViolated $e) {
            throw new AuthenticationException('Token is expired or invalid');
        } catch (\Exception $e) {
            error_log('Authentication failed: ' . $e->getMessage());
            throw new AuthenticationException('Authentication error: ' . $e->getMessage());
        }
    }

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?\Symfony\Component\HttpFoundation\Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        try {
            if (str_contains($exception->getMessage(), 'Token is expired')) {
                $oldToken = str_replace('Bearer ', '', $request->headers->get('Authorization'));
                try {
                    $token = $this->jwtConfig->parser()->parse($oldToken);
                    $email = $token->claims()->get('email');
                    $newToken = $this->jwtService->createToken($email);
                    return new JsonResponse([
                        'tokenExpired' => true,
                        'token' => $newToken
                    ], Response::HTTP_UNAUTHORIZED);
                } catch (\Exception $e) {
                    error_log('Token renewal failed: ' . $e->getMessage());
                }
            }
            return new JsonResponse([
                'error' => 'Authentication failed: ' . $exception->getMessage()
            ], Response::HTTP_UNAUTHORIZED);
        } catch (\Exception $e) {
            error_log('Authentication error: ' . $e->getMessage());
            return new JsonResponse([
                'error' => 'Authentication error'
            ], Response::HTTP_UNAUTHORIZED);
        }
    }
}
