<?php

namespace App\Service;

use Lcobucci\JWT\Configuration;
use App\Entity\User;
use Lcobucci\JWT\Signer\Key\InMemory;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

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
    }

    public function createToken(User $user): string
    {
        $now = new \DateTimeImmutable();
        $issuedBy = $this->params->get('issued_by');
        $permittedFor = $this->params->get('permitted_for');

        $token = $this->jwtConfig->builder()
            ->issuedBy($issuedBy)
            ->permittedFor($permittedFor)
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify('+2 hours'))
            ->withClaim('email', $user->getEmail())
            ->getToken($this->jwtConfig->signer(), $this->jwtConfig->signingKey());

        return $token->toString();
    }

    public function getJwtConfig(): Configuration
    {
        return $this->jwtConfig;
    }
}
