<?php

namespace App\Controller;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur pour gérer les pages publiques du site.
 */
class HomeController extends AbstractController
{
    /**
     * Constructeur de la classe HomeController.
     *
     * @param EntityManagerInterface $em             Le gestionnaire d'entités pour gérer les opérations sur la base de données
     * @param UserRepository         $userRepository Le dépôt d'utilisateurs pour interagir avec la base de données
     */
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
    ) {
    }

    /**
     * Affiche la page d'accueil du site.
     *
     * @return Response La réponse HTTP contenant la vue de la page d'accueil
     */
    #[Route('', name: 'home')]
    public function home(): Response
    {
        // Rendu de la vue de la page d'accueil
        return $this->render('front/home.html.twig');
    }

    /**
     * Affiche la liste des invités (non révoqués) sur la page publique.
     *
     * @return Response La réponse HTTP contenant la vue des invités
     */
    #[Route('/guests', name: 'guests')]
    public function guests(): Response
    {
        // Rendu de la vue avec la liste des invités non révoqués
        return $this->render('front/guests.html.twig', [
            'guests' => $this->userRepository->findGuests(excludeRevoked: true),
        ]);
    }

    /**
     * Affiche les détails d'un invité (non révoqué) sur la page publique.
     *
     * @param int $id L'identifiant de l'invité à afficher
     *
     * @return Response La réponse HTTP contenant la vue de l'invité
     */
    #[Route('/guest/{id}', name: 'guest')]
    public function guest(int $id): Response
    {
        // Récupère l'invité correspondant à l'identifiant fourni
        $guest = $this->em->getRepository(User::class)->find($id);

        // Vérifie si l'invité existe, n'est pas révoqué et n'est pas un administrateur
        if (!$guest instanceof User || $guest->isRevoked() || $guest->isAdmin()) {
            throw $this->createNotFoundException('Invité introuvable.');
        }

        // Rendu de la vue de l'invité avec les informations correspondantes
        return $this->render('front/guest.html.twig', [
            'guest' => $guest,
        ]);
    }

    /**
     * Affiche le portfolio du propriétaire du site (l'administrateur).
     *
     * @param int|null $id L'identifiant de l'album à afficher (optionnel)
     *
     * @return Response La réponse HTTP contenant la vue du portfolio
     */
    #[Route('/portfolio/{id}', name: 'portfolio')]
    public function portfolio(?int $id = null): Response
    {
        // Récupère tous les albums disponibles
        $albums = $this->em->getRepository(Album::class)->findAll();

        // Récupère l'album correspondant à l'identifiant fourni, ou null si aucun identifiant n'est fourni
        $album = $id ? $this->em->getRepository(Album::class)->find($id) : null;

        // Récupère l'utilisateur administrateur qui est le propriétaire du site
        $user = $this->userRepository->findOneAdmin();

        // Récupère les médias associés à l'album sélectionné ou à l'utilisateur administrateur
        $medias = $album
            ? $this->em->getRepository(Media::class)->findByAlbum($album)
            : $this->em->getRepository(Media::class)->findByUser($user);

        // Rendu de la vue du portfolio avec les albums, l'album sélectionné et les médias correspondants
        return $this->render('front/portfolio.html.twig', [
            'albums' => $albums,
            'album' => $album,
            'medias' => $medias,
        ]);
    }

    /**
     * Affiche la page "À propos" du site.
     *
     * @return Response La réponse HTTP contenant la vue de la page "À propos"
     */
    #[Route('/about', name: 'about')]
    public function about(): Response
    {
        return $this->render('front/about.html.twig');
    }
}
