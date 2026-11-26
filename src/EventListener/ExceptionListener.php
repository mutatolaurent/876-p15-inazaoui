<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Environment;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
final readonly class ExceptionListener
{
    public function __construct(
        private Environment $twig,
        private LoggerInterface $logger,
        private TokenStorageInterface $tokenStorage,
        private bool $debug = false,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        // Si le mode debug est activé, on laisse Symfony gérer l'exception pour afficher la page d'erreur détaillée
        if ($this->debug) {
            return;
        }

        // Récupère l'exception et la requête associée à l'événement
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Restaure le token de sécurité à partir de la session si nécessaire
        $this->restoreSecurityTokenFromSession($request);

        // Détermine le code de statut HTTP et les en-têtes à partir de l'exception
        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $headers = $exception->getHeaders();
        } else {
            $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
            $headers = [];
            $this->logger->error('Exception non gérée interceptée.', [
                'path' => $request->getPathInfo(),
                'exception' => $exception,
            ]);
        }

        // Détermine le template à utiliser en fonction du chemin de la requête et de l'état d'authentification de l'utilisateur
        $isAdminPath = '/admin' === $request->getPathInfo()
            || str_starts_with($request->getPathInfo(), '/admin/');

        // Vérifie si l'utilisateur est authentifié et est une instance de UserInterface
        $hasAuthenticatedUser = $this->tokenStorage->getToken()?->getUser() instanceof UserInterface;

        // Choisit le template d'erreur approprié en fonction du contexte (admin ou front) et de l'état d'authentification
        $template = ($isAdminPath && $hasAuthenticatedUser)
            ? 'error/admin.html.twig'
            : 'error/front.html.twig';

        // Rend le template d'erreur avec les informations pertinentes
        $content = $this->twig->render($template, [
            'status_code' => $statusCode,
            'status_text' => Response::$statusTexts[$statusCode] ?? 'Erreur',
            'message' => $this->resolvePublicMessage($statusCode, $exception),
        ]);

        // Crée une réponse HTTP avec le contenu rendu, le code de statut et les en-têtes appropriés
        $response = new Response($content, $statusCode, $headers);

        // Définit la réponse de l'événement pour remplacer la réponse par défaut de Symfony
        $event->setResponse($response);
    }

    /**
     * Restaure le token de sécurité à partir de la session si nécessaire.
     *
     * @param Request $request La requête HTTP en cours
     */
    private function restoreSecurityTokenFromSession(Request $request): void
    {
        // Vérifie si un token de sécurité est déjà présent et si l'utilisateur est authentifié
        $currentToken = $this->tokenStorage->getToken();
        if (null !== $currentToken && $currentToken->getUser() instanceof UserInterface) {
            return;
        }

        // Vérifie si la requête a une session active
        if (!$request->hasSession()) {
            return;
        }

        // Démarre la session si elle n'est pas déjà démarrée
        $session = $request->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        // Parcourt tous les attributs de la session
        foreach ($session->all() as $key => $value) {
            // Vérifie si l'attribut correspond à un token de sécurité valide
            if (!str_starts_with($key, '_security_') || !\is_string($value) || '' === $value) {
                continue;
            }

            try {
                // Tente de désérialiser la valeur pour obtenir le token de sécurité
                $token = @unserialize($value, ['allowed_classes' => true]);
            } catch (\Throwable) {
                // Ignore les exceptions de désérialisation et continue à la prochaine itération
                continue;
            }

            // Vérifie si le token est une instance de TokenInterface et si l'utilisateur associé est une instance de UserInterface
            if ($token instanceof TokenInterface && $token->getUser() instanceof UserInterface) {
                // Si le token est valide, le définit dans le stockage de sécurité pour restaurer l'état d'authentification
                $this->tokenStorage->setToken($token);

                return;
            }
        }
    }

    /**
     * Résout le message public à afficher en fonction du code de statut HTTP et de l'exception.
     *
     * @param int        $statusCode L'état HTTP de la réponse
     * @param \Throwable $exception  L'exception interceptée
     *
     * @return string Le message public à afficher
     */
    private function resolvePublicMessage(int $statusCode, \Throwable $exception): string
    {
        // Utilise une expression match pour déterminer le message public en fonction du code de statut HTTP et de l'exception
        return match ($statusCode) {
            Response::HTTP_NOT_FOUND => 'La page demandée est introuvable.',
            Response::HTTP_FORBIDDEN => 'Vous n\'avez pas les droits nécessaires pour accéder à cette ressource.',
            default => $exception instanceof HttpExceptionInterface && '' !== $exception->getMessage()
                ? $exception->getMessage()
                : 'Une erreur inattendue s\'est produite.',
        };
    }
}
