<?php

namespace Palladium\Service;

/**
 * Application logic for password reset handling
 */

use Palladium\Mapper as Mapper;
use Palladium\Entity as Entity;
use Palladium\Exception\IdentityNotFound;
use Palladium\Exception\IdentityNotVerified;

use Palladium\Contract\CanCreateMapper;
use Psr\Log\LoggerInterface;

class Recovery
{
    const DEFAULT_TOKEN_LIFESPAN = 28800; // 8 hours

    private $mapperFactory;
    private $logger;

    private $tokenLifespan;

    public function __construct(CanCreateMapper $mapperFactory, LoggerInterface $logger, $tokenLifespan = self::DEFAULT_TOKEN_LIFESPAN)
    {
        $this->mapperFactory = $mapperFactory;
        $this->logger = $logger;
        $this->tokenLifespan = $tokenLifespan;
    }


    /**
     * @throws Palladium\Exception\IdentityNotVerified if attempting to reset password for unverified identity
     *
     * @return string token, that can be use to reset password
     */
    public function markForReset(Entity\EmailIdentity $identity)
    {
        if ($identity->getStatus() === Entity\Identity::STATUS_NEW) {
            $this->logger->notice('identity not verified', [
                'input' => [
                    'email' => $identity->getEmailAddress(),
                ],
                'user' => [
                    'account' => $identity->getAccountId(),
                    'identity' => $identity->getId(),
                ],
            ]);

            throw new IdentityNotVerified;
        }

        $identity->generateToken();
        $identity->setTokenAction(Entity\Identity::ACTION_RESET);
        $identity->setTokenEndOfLife(time() + $this->tokenLifespan);

        $mapper = $this->mapperFactory->create(Mapper\EmailIdentity::class);
        $mapper->store($identity);

        $this->logger->info('request password reset', [
            'input' => [
                'email' => $identity->getEmailAddress(),
            ],
        ]);

        return $identity->getToken();
    }


    /**
     * @param string $password
     */
    public function resetIdentityPassword(Entity\EmailIdentity $identity, $password)
    {
        $token = $identity->getToken();

        $identity->setPassword($password);
        $identity->clearToken();

        $mapper = $this->mapperFactory->create(Mapper\EmailIdentity::class);
        $mapper->store($identity);

        $this->logger->info('password reset successful', [
            'input' => [
                'token' => $token,
            ],
        ]);
    }
}
