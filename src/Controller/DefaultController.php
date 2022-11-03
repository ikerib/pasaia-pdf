<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\FileType;

class DefaultController extends AbstractController
{
    #[Route('/', name: 'app_default')]
    public function index(): Response
    {
        return $this->render('default/index.html.twig');
    }

    #[Route('/pdfttiki', name: 'app_pdf_ttiki')]
    public function ttiki(Request $request): Response
    {
        $defaultData = ['message' => 'Aukeratu fitxategia'];
        $form = $this->createFormBuilder($defaultData)
            ->add('fitxategia', FileType::class, [
                'attr' => [
                    'class' => 'form-control'
                ],
                'required' => true,
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\File(),

                ]
            ])
            ->add('Txikitu', SubmitType::class, [
                'attr' => [
                    'class' => 'form-controle btn btn-primary mt-10'
                ]
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // data is an array with "name", "email", and "message" keys
            $data = $form->getData();
            $fitxategia = $data['fitxategia'];
            if ($fitxategia->getMimeType() !== "application/pdf") {
                $this->addFlash('error', 'Aukeratutako fitxategia ez da PDF bat');
                return $this->redirectToRoute('app_pdf_ttiki');
            }

            $dest = "/usr/src/app/public/uploads/";
            $target_file = $dest . preg_replace("/[^a-z0-9\_\-\.]/i", '', $fitxategia->getClientOriginalName());


            $process = new Process(['/usr/local/bin/shrinkpdf.sh', $fitxategia->getRealPath(), $target_file , 80]);
            $process->run();

            // executes after the command finishes
            if (!$process->isSuccessful()) {
                $this->addFlash('error', $process->getErrorOutput());
                return $this->redirectToRoute('app_pdf_ttiki');
            }

            $response = new BinaryFileResponse($target_file);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);

            return $response;
        }

        return $this->render('default/ttiki.html.twig', [
            'form' => $form->createView()
        ]);
    }
}
