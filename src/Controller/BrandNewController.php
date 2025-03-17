<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\Type\TaskType;
use App\Service\MessageGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\String\Slugger\SluggerInterface;

final class BrandNewController extends AbstractController
{
    protected $messageGenerator;
    protected $adminEmail;
    public function __construct(MessageGenerator $messageGenerator, string $adminEmail)
    {
        $this->messageGenerator = $messageGenerator;
        $this->adminEmail = $adminEmail;
    }

    public function list(
        EntityManagerInterface $entityManager,
        Request $request,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads/brochures')] string $brochuresDirectory
    ): Response {
        $users = $entityManager->getRepository(User::class)->findAll();
        $user = new User();
        $form = $form = $this->createForm(TaskType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $task = $form->getData();
            /** @var UploadedFile $brochureFile */
            $brochureFile = $form->get('profile_image')->getData();

            // this condition is needed because the 'brochure' field is not required
            // so the PDF file must be processed only when a file is uploaded
            if ($brochureFile) {
                $originalFilename = pathinfo($brochureFile->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $brochureFile->guessExtension();

                // Move the file to the directory where brochures are stored
                try {
                    $brochureFile->move($brochuresDirectory, $newFilename);
                } catch (Exception $e) {
                    // ... handle exception if something happens during file upload
                }

                // updates the 'brochureFilename' property to store the PDF file name
                // instead of its contents
                $task->setProfileImage($newFilename);
            }

            $entityManager->persist($task);
            $entityManager->flush();
            return $this->redirectToRoute('blog_list');
        }
        return $this->render('brand_new/index.html.twig', [
            'controller_name' => $this->messageGenerator->getHappyMessage() . " " . $this->adminEmail,
            'form' => $form,
            'users' => $users,
        ]);
    }
}
