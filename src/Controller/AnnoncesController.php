<?php

namespace App\Controller;
use App\Entity\Images;
use App\Entity\Comment;
use App\Entity\Annonces;
use App\Form\AnnoncesType;
use App\Form\CommentType;
use App\Form\SearchAnnonceType;
use App\Repository\AnnoncesRepository;
use App\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Knp\Component\Pager\PaginatorInterface;
use DateTime;
/**
 * @Route("/annonces")
 */
class AnnoncesController extends AbstractController
{
    /**
     * @Route("/", name="annonces_index")
     */
    public function index(AnnoncesRepository $annoncesRepository,Request $request,PaginatorInterface $paginator): Response
    {
        $repo = $this->getDoctrine()->getRepository(Annonces::class);
        $annonces = $repo->findAll(Annonces::class);
        $form=$this->createForm(SearchAnnonceType::class);
        $search =$form->handleRequest($request);
        if($form->isSubmitted()&& $form->isValid()){
         
            //on recherche les annonce correspondant aux mots
            $annonces = $annoncesRepository->search(
                $search->get('mots')->getData(),
                $search->get('categorie')->getData()
            );
       
          
        }
        $annonces = $paginator->paginate(
            $annonces, /* query NOT result */
            $request->query->getInt('page', 1),
            3
        );
      
        return $this->render('annonces/index.html.twig', [
            'annonces' => $annonces,
            'form'=>$form->createView()
        ]);
    }

    /**
     * @Route("/new", name="annonces_new")
     */
    public function new(Request $request, EntityManagerInterface $entityManager,CommentRepository $commentRepository): Response
    {
        
        $annonce = new Annonces();
        $form = $this->createForm(AnnoncesType::class, $annonce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
           
             // On récupère les images transmises
             $images = $form->get('images')->getData();

             // On boucle sur les images
             foreach($images as $image){
                 // On génère un nouveau nom de fichier
                 $fichier = md5(uniqid()) . '.' . $image->guessExtension();
 
                 // On copie le fichier dans le dossier uploads
                 $image->move(
                     $this->getParameter('images_directory'),
                     $fichier
                 );
 
                 // On stocke l'image dans la base de données (son nom)
                 $img = new Images();
                 $img->setName($fichier);
                 $annonce->addImage($img);
             }
 
            $entityManager->persist($annonce);
            $entityManager->flush();
         
       

            return $this->redirectToRoute('annonces_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('annonces/new.html.twig', [
            'annonce' => $annonce,
            
            'form' => $form->createView(),
          
        ]);
    }

    /**
     * @Route("/{id}", name="annonces_show", )
     */
    public function show(Annonces $annonce, Request $request,EntityManagerInterface $entityManager): Response
    {
        $comment = new Comment();
        $form=$this->createForm(CommentType::class, $comment);
        $form =$form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
          
            $comment->setAnnonces($annonce);
            
            $entityManager=$this->getDoctrine()->getManager();
           
           

            $entityManager->persist($comment);
            $entityManager->flush();
          
        }
      

        return $this->render('annonces/show.html.twig', [
            'annonce' => $annonce,
            'form' => $form->createView(),
           
        ]);
    }

    /**
     * @Route("/{id}/edit", name="annonces_edit", methods={"GET", "POST"})
     */
    public function edit(Request $request, Annonces $annonce, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AnnoncesType::class, $annonce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
             // On récupère les images transmises
             $images = $form->get('images')->getData();

             // On boucle sur les images
             foreach($images as $image){
                 // On génère un nouveau nom de fichier
                 $fichier = md5(uniqid()) . '.' . $image->guessExtension();
 
                 // On copie le fichier dans le dossier uploads
                 $image->move(
                     $this->getParameter('images_directory'),
                     $fichier
                 );
 
                 // On stocke l'image dans la base de données (son nom)
                 $img = new Images();
                 $img->setName($fichier);
                 $annonce->addImage($img);
             }
 
            $entityManager->flush();

            return $this->redirectToRoute('annonces_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('annonces/edit.html.twig', [
            'annonce' => $annonce,
          
            'form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="annonces_delete")
     */
    public function delete(Request $request, Annonces $annonce, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$annonce->getId(), $request->request->get('_token'))) {
            $entityManager->remove($annonce);
            $entityManager->flush();
        }

        return $this->redirectToRoute('annonces_index', [], Response::HTTP_SEE_OTHER);
    }
    /**
     * @Route("/supprime/image/{id}", name="annonces_delete_image", methods={"DELETE"})
     */
    public function deleteImage(Images $image, Request $request){
        $data = json_decode($request->getContent(), true);

        // On vérifie si le token est valide
        if($this->isCsrfTokenValid('delete'.$image->getId(), $data['_token'])){
            // On récupère le nom de l'image
            $nom = $image->getName();
            // On supprime le fichier
            unlink($this->getParameter('images_directory').'/'.$nom);

            // On supprime l'entrée de la base
            $em = $this->getDoctrine()->getManager();
            $em->remove($image);
            $em->flush();

            // On répond en json
            return new JsonResponse(['success' => 1]);
        }else{
            return new JsonResponse(['error' => 'Token Invalide'], 400);
        }
    }
}
