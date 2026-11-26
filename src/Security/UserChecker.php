<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

// Vérifie l'état de l'utilisateur avant et après l'authentification
final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Vérifie si l'utilisateur a été révoqué avant l'authentification
        if ($user->isRevoked()) {
            throw new CustomUserMessageAccountStatusException('Votre accès a été révoqué, contactez l\'administrateur du site.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof User) {
            return;
        }

        // Vérifie si l'utilisateur a été révoqué après l'authentification
        if ($user->isRevoked()) {
            throw new CustomUserMessageAccountStatusException('Votre accès a été révoqué, contactez l\'administrateur du site.');
        }
    }
}
