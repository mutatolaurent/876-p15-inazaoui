<?php

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\User;
use App\Form\MediaType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class MediaController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /**
     * Affiche la liste des médias avec pagination.
     * Les cas d'erreur comme une valeur du paramètre page <= 0 ou > au nombre max de pages ou une valeur non entière
     * sont gérés en renvoyant la page 1 ou la dernière page.
     *
     * @param int|null $page   Le numéro de la page à afficher (optionnel, par défaut 1)
     * @param int|null $userId L'identifiant de l'utilisateur pour filtrer les médias (optionnel, par défaut null)
     *
     * @return Response La réponse HTTP contenant la vue de la liste des médias
     */
    #[Route('/admin/media', name: 'admin_media_index')]
    public function index(
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, flags: \FILTER_NULL_ON_FAILURE)]
        ?int $page = null,
        #[MapQueryParameter(filter: \FILTER_VALIDATE_INT, flags: \FILTER_NULL_ON_FAILURE)]
        ?int $userId = null,
    ): Response {
        // Nombre de médias par page, défini dans les paramètres de l'application (app.media_per_page)
        $limit = max(1, (int) $this->getParameter('app.media_per_page'));

        // Critères de recherche des médias
        $criteria = [];
        $guest = null;
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        // Si l'utilisateur n'est pas admin, l'utilisateur est utilisé comme critère de recherche pour ne récupérer que ses médias
        if (!$isAdmin) {
            // Récupère l'utilisateur courant
            $currentUser = $this->getUser();

            // Si l'utilisateur courant est un utilisateur valide, on l'ajoute aux critères de recherche
            $criteria['user'] = $currentUser;

            // Si l'utilisateur courant n'est pas un administrateur, on le considère comme un invité
            if ($currentUser instanceof User && !$currentUser->isAdmin()) {
                $guest = $currentUser;
            }

            // Si l'utilisateur courant n'est pas un administrateur, on ignore le paramètre userId pour ne pas permettre de filtrer par un autre utilisateur
            $userId = null;
        } elseif (null !== $userId) {
            // Si l'utilisateur est admin et que le paramètre userId est fourni, on récupère l'utilisateur correspondant à cet identifiant
            $user = $this->em->getRepository(User::class)->find($userId);
            if (!$user instanceof User) {
                throw $this->createNotFoundException('Utilisateur introuvable.');
            }

            // On ajoute l'utilisateur aux critères de recherche pour ne récupérer que ses médias
            $criteria['user'] = $user;
            if (!$user->isAdmin()) {
                $guest = $user;
            }
        }

        // Récupère le nombre total de médias correspondant aux critères (ceux de l'utilisateur ou tous si l'utilisateur est admin)
        $total = $this->em->getRepository(Media::class)->count($criteria);
        $totalPages = max(1, (int) ceil($total / $limit));

        // Borne la page entre 1 et $totalPages
        $page = min(max(1, $page ?? 1), $totalPages);

        // Récupère les médias correspondant aux critères et à la page actuelle
        $medias = $this->em->getRepository(Media::class)->findBy(
            $criteria,
            ['id' => 'ASC'],
            $limit,
            $limit * ($page - 1)
        );

        // Rendu de la vue avec les médias, le nombre total, la page actuelle et le nombre total de pages
        return $this->render('admin/media/index.html.twig', [
            'medias' => $medias,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'userId' => $userId,
            'guest' => $guest,
        ]);
    }

    /**
     * Ajoute un nouveau média à la base de données.
     * Si l'utilisateur n'est pas admin, le média est associé à l'utilisateur courant.
     * Le fichier du média est déplacé vers le dossier "uploads" avec un nom unique généré.
     *
     * @param Request $request La requête HTTP contenant les données du formulaire
     *
     * @return Response La réponse HTTP après l'ajout du média
     */
    #[Route('/admin/media/add', name: 'admin_media_add')]
    public function add(Request $request): Response
    {
        $media = new Media();
        $form = $this->createForm(MediaType::class, $media, ['is_admin' => $this->isGranted('ROLE_ADMIN')]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->isGranted('ROLE_ADMIN')) {
                $user = $this->getUser();
                $media->setUser($user instanceof User ? $user : null);
            }
            $media->setPath('uploads/'.md5(uniqid()).'.'.$media->getFile()->guessExtension());
            $media->getFile()->move('uploads/', $media->getPath());
            $this->em->persist($media);
            $this->em->flush();

            // Ajoute un message flash pour informer l'utilisateur du résultat de l'action
            $this->addFlash('success', 'Le média a été ajouté avec succès.');

            return $this->redirectToRoute('admin_media_index');
        }

        return $this->render('admin/media/add.html.twig', ['form' => $form->createView()]);
    }

    /**
     * Supprime un média de la base de données et du système de fichiers.
     * La suppression est sécurisée par un jeton CSRF pour éviter les attaques de type CSRF.
     * La suppression physique du fichier est réalisée via un EventListener qui écoute l'événement postRemove de l'entité Media.
     *
     * @param int     $id      L'identifiant du média à supprimer
     * @param Request $request La requête HTTP
     *
     * @return Response La réponse HTTP après la suppression
     */
    #[Route('/admin/media/delete/{id}', name: 'admin_media_delete')]
    public function delete(int $id, Request $request): Response
    {
        // Récupère le média correspondant à l'identifiant fourni
        $media = $this->em->getRepository(Media::class)->find($id);

        // Vérifie la validité du jeton CSRF pour sécuriser l'action de suppression
        if (!$this->isCsrfTokenValid('delete-media-'.$media->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $this->em->remove($media);
        $this->em->flush();

        // La suppression physique du fichier est gérée par un EventListener qui écoute l'événement postRemove de l'entité Media.
        // unlink($media->getPath());

        // Ajoute un message flash pour informer l'utilisateur du résultat de l'action
        $this->addFlash('success', 'Le média a été supprimé avec succès.');

        return $this->redirectToRoute('admin_media_index');
    }
}
