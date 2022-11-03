<?php

namespace App\Controller;

use mikehaertl\pdftk\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\Positive;

class DefaultController extends AbstractController
{
    #[Route('/', name: 'app_default')]
    public function index(): Response
    {
        return $this->render('default/index.html.twig');
    }

    #[Route('/ttiki', name: 'app_pdf_ttiki')]
    public function ttiki(Request $request): Response
    {
        $defaultData = ['message' => 'Aukeratu fitxategia'];
        $form = $this->createFormBuilder($defaultData)
            ->add('fitxategia', FileType::class, [
                'attr' => [
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\File(),

                ],
                'required' => true,
            ])
            ->add('resolution', NumberType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'style' => 'width: 200px'
                ],
                'constraints' => [
                    new Positive(message: 'Resoluzioak positiboa izan behar du')
                ],
                'data' => 80,
                'empty_data' => 80,
                'required' => true
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
            $resolution = $data['resolution'];
            if ($fitxategia->getMimeType() !== "application/pdf") {
                $this->addFlash('error', 'Aukeratutako fitxategia ez da PDF bat');
                return $this->redirectToRoute('app_pdf_ttiki');
            }

            $dest = "/usr/src/app/public/uploads/";
            $target_file = $dest . preg_replace("/[^a-z0-9\_\-\.]/i", '', $fitxategia->getClientOriginalName());


            $process = new Process(['/usr/local/bin/shrinkpdf.sh', $fitxategia->getRealPath(), $target_file , $resolution]);
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

    #[Route('/elkartu', name: 'app_pdf_elkartu')]
    public function elkartu(Request $request): Response
    {
        $defaultData = ['message' => 'Aukeratu fitxategia'];
        $form = $this->createFormBuilder($defaultData)
            ->add('fitxategia', FileType::class, [
                'attr' => [
                    'class' => 'form-control'
                ],
                'data_class' => null,
                'multiple' => true,
                'required' => true,
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
            $fitxategiak = $data['fitxategia'];
            $targetfile = "/usr/src/app/public/uploads/" . md5(date('Y-m-d H:i:s:u')) . ".pdf";
            $temp = "/tmp/" . md5(date('Y-m-d H:i:s:u')) ."/";
            $tempfile = 0;
            $files = [];

            $da = ['message' => 'kk'];
            $frm = $this->createFormBuilder($da);

            /** @var UploadedFile $fitxategia */
            foreach ($fitxategiak as $fitxategia)
            {
                if ($fitxategia->getMimeType() !== "application/pdf") {
                    $this->addFlash('error', 'Aukeratutako fitxategia ez da PDF bat');
                    return $this->redirectToRoute('app_pdf_ttiki');
                }

                ++$tempfile;
                $tempFileName = "$tempfile.pdf";
                $fitxategia->move($temp, $tempFileName);
                $files[] = $temp.$tempFileName;

                $frm->add("fitxategia$tempfile", TextType::class, [
                    'attr' => [
                        'class' => 'form-control col-md-6',

                    ],
                    'data' => $fitxategia->getClientOriginalName(),
                    'disabled' => true
                ]);

//                $frm->add("fitxategia$tempfile", FileType::class, [
//                    'data_class' => null,
//                    'mapped' => false,
//                    'attr' => [
//                        'class' => 'form-control'
//                    ]
//                ]);
                //$frm->get("fitxategia$tempfile")->setData($fitxategia->getClientOriginalName());
                //$frm["fitxategia$tempfile"]->setData($f);
            }



            return $this->render('default/elkartu2.html.twig', [
                'form' => $frm->getForm()->createView()
            ]);

//            $pdf = new Pdf($files);
//            $result = $pdf->cat()->saveAs($targetfile);
//            if ($result === false) {
//                $error = $pdf->getError();
//
//                $this->addFlash('error', $error);
//                return $this->redirectToRoute('app_pdf_elkartu');
//            }
//
//            $response = new BinaryFileResponse($targetfile);
//            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);
//
//            return $response;
        }

        return $this->render('default/elkartu.html.twig', [
            'form' => $form->createView()
        ]);
    }

}
