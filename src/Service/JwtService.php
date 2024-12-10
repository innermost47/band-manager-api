<?php

namespace App\Service;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;

class JwtService
{
    private $jwtConfig;
    private $params;


    public function __construct(ParameterBagInterface $params,)
    {
        $this->params = $params;
        $secret = $this->params->get('jwt_secret');
        $this->jwtConfig = Configuration::forSymmetricSigner(
            new \Lcobucci\JWT\Signer\Hmac\Sha256(),
            InMemory::plainText($secret)
        );

        $this->jwtConfig->setValidationConstraints(
            new SignedWith($this->jwtConfig->signer(), $this->jwtConfig->signingKey()),
            new StrictValidAt(new \Lcobucci\Clock\SystemClock(new \DateTimeZone('UTC')))
        );
    }

    public function createToken($email): string
    {
        $now = new \DateTimeImmutable();
        $issuedBy = $this->params->get('issued_by');
        $permittedFor = $this->params->get('permitted_for');

        $token = $this->jwtConfig->builder()
            ->issuedBy($issuedBy)
            ->permittedFor($permittedFor)
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify('+48 hours'))
            ->withClaim('email', $email)
            ->getToken($this->jwtConfig->signer(), $this->jwtConfig->signingKey());

        return $token->toString();
    }

    public function getJwtConfig(): Configuration
    {
        return $this->jwtConfig;
    }

    public function getEmailFromToken(string $token): ?string
    {
        try {
            $jwt = $this->jwtConfig->parser()->parse($token);
            $this->jwtConfig->validator()->assert($jwt, ...$this->jwtConfig->validationConstraints());
            if (!$jwt instanceof Plain) {
                throw new \InvalidArgumentException('The token is not of type Plain');
            }
            $email = $jwt->claims()->get('email');
            if (!$email) {
                throw new \InvalidArgumentException('Email claim not found in token');
            }
            return $email;
        } catch (\Lcobucci\JWT\Validation\RequiredConstraintsViolated $e) {
            throw new \App\Exception\JwtException('JWT Constraint violation: ' . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            throw new \App\Exception\JwtException('JWT Error: ' . $e->getMessage(), 0, $e);
        }
    }
}
