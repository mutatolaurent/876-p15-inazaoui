<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\GuestType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class GuestController extends AbstractController
{
    /**
     * Constructeur de la classe GuestController.
     *
     * @param UserRepository              $userRepository     Le dépôt d'utilisateurs pour interagir avec la base de données
     * @param UserPasswordHasherInterface $userPasswordHasher Le service de hachage de mot de passe pour sécuriser les mots de passe
     * @param EntityManagerInterface      $em                 Le gestionnaire d'entités pour gérer les opérations sur la base de données
     */
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $userPasswordHasher,
        private EntityManagerInterface $em)
    {
    }

    /**
     * Affiche la liste des invités avec pagination.
     * Les cas d'erreur comme une valeur du paramètre page <= 0 ou > au nombre max de pages ou une valeur non entière
     * sont gérés en renvoyant la page 1 ou la dernière page.
     *
     * @param int|null $page Le numéro de la page à afficher (optionnel)
     *
     * @return Response La réponse HTTP contenant la vue des invités
     */
    #[Route('/admin/guest', name: 'admin_guest_index')]
    public function index(#[MapQueryParameter(filter: \FILTER_VALIDATE_INT, flags: \FILTER_NULL_ON_FAILURE)]
        ?int $page = null, ): Response
    {
        // Nombre d'invités par page
        $limit = max(1, (int) $this->getParameter('app.guests_per_page'));

        // Récupère le nombre total d'invités
        $total = $this->userRepository->countGuests();

        // Calcule le nombre total de pages
        $totalPages = max(1, (int) ceil($total / $limit));

        // Borne la page entre 1 et $totalPages
        $page = min(max(1, $page ?? 1), $totalPages);

        // Récupère les invités correspondant aux critères et à la page actuelle
        $guests = $this->userRepository->findGuests($limit, $limit * ($page - 1));

        // Rendu de la vue avec les médias, le nombre total, la page actuelle et le nombre total de pages
        return $this->render('admin/guest/index.html.twig', [
            'guests' => $guests,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    /**
     * Affiche le formulaire d'ajout d'un invité et traite la soumission du formulaire.
     *
     * @param Request $request La requête HTTP contenant les données du formulaire
     *
     * @return Response La réponse HTTP contenant la vue du formulaire ou une redirection après l'ajout réussi
     */
    #[Route('/admin/guest/add', name: 'admin_guest_add')]
    public function add(Request $request): Response
    {
        // Crée un nouvel objet User pour représenter l'invité à ajouter
        $guest = new User();

        // Crée le formulaire GuestType en associant l'objet User et en passant l'option is_edit à false
        $form = $this->createForm(GuestType::class, $guest);

        // Traite la soumission du formulaire avec les données de la requête
        $form->handleRequest($request);

        // Vérifie si le formulaire a été soumis et est valide
        if ($form->isSubmitted() && $form->isValid()) {
            // Hash le mot de passe saisi dans le formulaire et le définit pour l'invité
            $guest->setPassword(
                $this->userPasswordHasher->hashPassword(
                    $guest,
                    $form->get('password')->getData()
                )
            );
            $this->em->persist($guest);
            $this->em->flush();

            // Ajoute un message flash pour informer l'utilisateur du résultat de l'action
            $this->addFlash('success', 'L\'invité '.$guest->getName().' a été ajouté avec succès.');

            // Redirige vers la liste des invités après l'ajout réussi
            return $this->redirectToRoute('admin_guest_index');
        }

        // Rendu de la vue avec le formulaire d'ajout d'invité
        return $this->render('admin/guest/add.html.twig', ['form' => $form->createView()]);
    }

    /**
     * Affiche et traite le formulaire de modification d'un invité.
     *
     * @param int     $id      L'identifiant de l'invité à modifier
     * @param Request $request La requête HTTP contenant les données du formulaire
     *
     * @return Response La réponse HTTP contenant la vue du formulaire ou une redirection après la modification réussie
     */
    #[Route('/admin/guest/{id}/edit', name: 'admin_guest_edit', requirements: ['id' => '\d+'])]
    public function edit(int $id, Request $request): Response
    {
        // Récupère l'invité correspondant à l'identifiant fourni
        $guest = $this->userRepository->find($id);

        // Vérifie si l'invité existe et n'est pas un administrateur
        if (!$guest instanceof User || $guest->isAdmin()) {
            throw $this->createNotFoundException('Invité introuvable.');
        }

        // Crée le formulaire GuestType en associant l'objet User et en passant l'option is_edit à true
        $form = $this->createForm(GuestType::class, $guest, ['is_edit' => true]);

        // Traite la soumission du formulaire avec les données de la requête
        $form->handleRequest($request);

        // Vérifie si le formulaire a été soumis et est valide
        if ($form->isSubmitted() && $form->isValid()) {
            // Récupère le mot de passe saisi dans le formulaire
            $password = $form->get('password')->getData();

            // Si un mot de passe est fourni, le hache et le définit pour l'invité
            if (\is_string($password) && '' !== $password) {
                $guest->setPassword(
                    $this->userPasswordHasher->hashPassword($guest, $password)
                );
            }

            // Persiste les modifications de l'invité dans la base de données
            $this->em->persist($guest);
            $this->em->flush();

            // Ajoute un message flash pour informer l'utilisateur du résultat de l'action
            $this->addFlash('success', 'Les modifications de l\'invité '.$guest->getName().' ont été enregistrées.');

            // Redirige vers la liste des invités après la modification réussie
            return $this->redirectToRoute('admin_guest_index');
        }

        // Rendu de la vue avec le formulaire de modification d'invité
        return $this->render('admin/guest/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/guest/{id}/toggle-revoke', name: 'admin_guest_toggle_revoke', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleRevoke(int $id, Request $request): Response
    {
        // Récupère l'invité correspondant à l'identifiant fourni
        $guest = $this->userRepository->find($id);

        // Vérifie si l'invité existe et n'est pas un administrateur
        if (!$guest instanceof User || $guest->isAdmin()) {
            throw $this->createNotFoundException('Invité introuvable.');
        }

        // Vérifie la validité du jeton CSRF pour sécuriser l'action de révocation
        if (!$this->isCsrfTokenValid('toggle-revoke-guest-'.$guest->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        // Bascule l'état de révocation de l'invité (révoqué ou non révoqué)
        $nextRevokedState = !$guest->isRevoked();

        // Met à jour l'état de révocation de l'invité dans la base de données
        $guest->setIsRevoked($nextRevokedState);

        // Persiste les modifications de l'invité dans la base de données
        $this->em->persist($guest);
        $this->em->flush();

        // Ajoute un message flash pour informer l'utilisateur du résultat de l'action
        $this->addFlash(
            'success',
            $nextRevokedState
                ? 'L\'accès de l\'invité '.$guest->getName().' a été révoqué.'
                : 'La révocation de l\'invité '.$guest->getName().' a été annulée.'
        );

        // Redirige vers la liste des invités après la modification de l'état de révocation
        return $this->redirectToRoute('admin_guest_index');
    }

    /**
     * Supprime un invité et ses médias associés.
     * La suppression physique des médias est gérée par l'événement Doctrine MediaRemoveListener.
     *
     * @param int     $id      L'identifiant de l'invité à supprimer
     * @param Request $request La requête HTTP contenant les données du formulaire
     *
     * @return Response La réponse HTTP redirigeant vers la liste des invités après la suppression
     */
    #[Route('/admin/guest/{id}/delete', name: 'admin_guest_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        // Récupère l'invité correspondant à l'identifiant fourni
        $guest = $this->userRepository->find($id);

        // Vérifie si l'invité existe et n'est pas un administrateur
        if (!$guest instanceof User || $guest->isAdmin()) {
            throw $this->createNotFoundException('Invité introuvable.');
        }

        // Vérifie la validité du jeton CSRF pour sécuriser l'action de suppression
        if (!$this->isCsrfTokenValid('delete-guest-'.$guest->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        // Supprime l'invité de la base de données
        $this->em->remove($guest);
        $this->em->flush();

        // Ajoute un message flash pour informer l'utilisateur du résultat de l'action
        $this->addFlash('success', 'L\'invité '.$guest->getName().' et ses médias ont été supprimés.');

        // Redirige vers la liste des invités après la suppression
        return $this->redirectToRoute('admin_guest_index');
    }
}
