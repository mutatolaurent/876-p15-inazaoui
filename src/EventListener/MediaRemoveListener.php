<?php

namespace App\EventListener;

use App\Entity\Media;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Écouteur d'événements pour la suppression des entités Media.
 *
 * Cet écouteur est déclenché avant la suppression d'une entité Media.
 * Il supprime le fichier média correspondant du disque si celui-ci existe.
 */
#[AsEntityListener(event: Events::preRemove, entity: Media::class)]
final readonly class MediaRemoveListener
{
    /**
     * Constructeur de la classe MediaRemoveListener.
     *
     * @param string          $publicDir  Le répertoire public du projet
     * @param Filesystem      $filesystem Le service Filesystem pour gérer les opérations sur le système de fichiers
     * @param LoggerInterface $logger     Le service Logger pour enregistrer les erreurs et les informations
     */
    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private string $publicDir,
        private Filesystem $filesystem,
        private LoggerInterface $logger,
    ) {
    }

    public function preRemove(Media $media): void
    {
        // Récupère le chemin relatif du fichier média à partir de l'entité Media
        $relativePath = $media->getPath();

        // Si le chemin relatif est vide, il n'y a rien à supprimer, donc on quitte la méthode
        if ('' === $relativePath) {
            return;
        }

        // Construit le chemin absolu du fichier média en combinant le répertoire public et le chemin relatif
        $filePath = rtrim($this->publicDir, '/\\').\DIRECTORY_SEPARATOR.ltrim($relativePath, '/\\');

        // Vérifie si le fichier existe avant de tenter sa suppression
        if (!$this->filesystem->exists($filePath)) {
            return;
        }

        try {
            // Supprime le fichier média du disque
            $this->filesystem->remove($filePath);
        } catch (\Throwable $exception) {
            // Log l'erreur si la suppression échoue, mais ne bloque pas le processus de suppression de l'entité Media
            $this->logger->error('Impossible de supprimer le fichier média sur le disque.', [
                'media_id' => $media->getId(),
                'file_path' => $filePath,
                'error' => $exception->getMessage(),
                'exception' => $exception,
            ]);
        }
    }
}
