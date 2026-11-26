<?php

namespace App\Controller\Admin;

use App\Entity\Album;
use App\Form\AlbumType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AlbumController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[Route('/admin/album', name: 'admin_album_index')]
    public function index(): Response
    {
        $albums = $this->em->getRepository(Album::class)->findAll();

        return $this->render('admin/album/index.html.twig', ['albums' => $albums]);
    }

    #[Route('/admin/album/add', name: 'admin_album_add')]
    public function add(Request $request): Response
    {
        $album = new Album();
        $form = $this->createForm(AlbumType::class, $album);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($album);
            $this->em->flush();

            // Ajoute un message flash pour informer l'utilisateur du résultat de l'action
            $this->addFlash('success', 'L\'album '.$album->getName().' a été ajouté avec succès.');

            return $this->redirectToRoute('admin_album_index');
        }

        return $this->render('admin/album/add.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/admin/album/update/{id}', name: 'admin_album_update')]
    public function update(Request $request, int $id): Response
    {
        $album = $this->em->getRepository(Album::class)->find($id);
        $form = $this->createForm(AlbumType::class, $album);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            // Ajoute un message flash pour informer l'utilisateur du résultat de l'action
            $this->addFlash('success', 'L\'album '.$album->getName().' a été mis à jour avec succès.');

            return $this->redirectToRoute('admin_album_index');
        }

        return $this->render('admin/album/update.html.twig', ['form' => $form->createView()]);
    }

    #[Route('/admin/album/delete/{id}', name: 'admin_album_delete')]
    public function delete(int $id, Request $request): Response
    {
        $media = $this->em->getRepository(Album::class)->find($id);

        // Vérifie la validité du jeton CSRF pour sécuriser l'action de suppression
        if (!$this->isCsrfTokenValid('delete-album-'.$media->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $this->em->remove($media);
        $this->em->flush();

        // Ajoute un message flash pour informer l'utilisateur du résultat de l'action
        $this->addFlash('success', 'L\'album a été supprimé avec succès.');

        return $this->redirectToRoute('admin_album_index');
    }
}
