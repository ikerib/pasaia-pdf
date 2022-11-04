<?php

namespace App\Controller;

use mikehaertl\pdftk\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
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
            ->add('Elkartu', SubmitType::class, [
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
            $total = count($fitxategiak);


            /** @var UploadedFile $fitxategia */
            foreach (array_reverse($fitxategiak) as $fitxategia)
            {
                --$total;
                if ($fitxategia->getMimeType() !== "application/pdf") {
                    $this->addFlash('error', 'Aukeratutako fitxategia ez da PDF bat');
                    return $this->redirectToRoute('app_pdf_ttiki');
                }

                ++$tempfile;
                $tempFileName = "$tempfile.pdf";
                $fitxategia->move($temp, $tempFileName);
                $files[$tempfile] = $temp.$tempFileName;

            }

            $pdf = new Pdf($files);
            $result = $pdf->cat()->saveAs($targetfile);
            if ($result === false) {
                $error = $pdf->getError();

                $this->addFlash('error', $error);
                return $this->redirectToRoute('app_pdf_elkartu');
            }

            $response = new BinaryFileResponse($targetfile);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);

            return $response;
        }

        return $this->render('default/elkartu.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/gehitu', name: 'app_pdf_gehitu')]
    public function gehitu(Request $request): Response
    {
        $defaultDataGehitu = ['message' => 'Fitxategia gehitu'];
        $form = $this->createFormBuilder($defaultDataGehitu)
            ->add('fitxategia1', FileType::class, [
                'attr' => [
                    'class' => 'form-control'
                ],
                'data_class' => null,
                'label' => '1º PDF ',
                'multiple' => false,
                'required' => true,
            ])
            ->add('fitxategia2', FileType::class, [
                'attr' => [
                    'class' => 'form-control'
                ],
                'data_class' => null,
                'label' => '2º PDF ',
                'multiple' => false,
                'required' => true,
            ])
            ->add('Gehitu', SubmitType::class, [
                'attr' => [
                    'class' => 'form-controle btn btn-primary mt-10'
                ]
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // data is an array with "name", "email", and "message" keys
            $data = $form->getData();
            $fic1 = $fitxategiak = $data['fitxategia1'];
            $fic2 = $fitxategiak = $data['fitxategia2'];
//            $orria= $fitxategiak = $data['orria'];

            if ($fic1->getMimeType() !== "application/pdf") {
                $this->addFlash('error', '1º PDF  fitxategia ez da PDF bat');
                return $this->redirectToRoute('app_pdf_ttiki');
            }

            if ($fic1->getMimeType() !== "application/pdf") {
                $this->addFlash('error', '2º PDF  fitxategia ez da PDF bat');
                return $this->redirectToRoute('app_pdf_ttiki');
            }


            $temp = "/tmp/" . md5(date('Y-m-d H:i:s:u')) ."/";
            $fic1->move($temp, 'fic1.pdf');
            $tempfic1 = $temp . "fic1.pdf";
            $fic2->move($temp, 'fic2.pdf');
            $tempfic2 = $temp . "fic2.pdf";
            $targetfile = "/usr/src/app/public/uploads/" . md5(date('Y-m-d H:i:s:u')) . ".pdf";

            $pdf = new Pdf($tempfic1);
//            $result = $pdf->attachFiles([$tempfic2],2);
            $result = $pdf->addFile($tempfic2);
            if ($result === false) {
                $error = $pdf->getError();
                $this->addFlash('error', $error);

                return $this->redirectToRoute('app_pdf_gehitu');
            }
            $pdf->saveAs($targetfile);

            $response = new BinaryFileResponse($targetfile);
            $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);

            return $response;
        }

        return $this->render('default/gehitu.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/banatu', name: 'app_pdf_banatu')]
    public function banatu(Request $request): \ZipArchive|Response
    {
        $defaultDataBanatu = ['message' => 'Aukeratu fitxategia banatzeko'];
        $form = $this->createFormBuilder($defaultDataBanatu)
            ->add('fitxategia', FileType::class, [
                'attr' => [
                    'class' => 'form-control'
                ],
                'data_class' => null,
                'multiple' => false,
                'required' => true,
            ])
            ->add('Banatu', SubmitType::class, [
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

            $dest = "/usr/src/app/public/uploads/". md5(date('Y-m-d H:i:s:u'))."/";
            $filename = preg_replace("/[^a-z0-9\_\-\.]/i", '', $fitxategia->getClientOriginalName());
            $fitxategia->move($dest, $filename);
            $target_file = $dest.$filename ;


            $pdf = new Pdf($target_file);
            $files = $pdf->burst($dest."pg_%04d.pdf");
            if ($files === false) {
                $error = $pdf->getError();
                $this->addFlash('error', $error);
                $this->redirectToRoute('app_pdf_banatu');
            }

            // new zip
            $zip = new \ZipArchive();
            $zipName = $dest.preg_replace("/[^a-z0-9\_\-\.]/i", '', preg_replace('/(.*)\\.[^\\.]*/', '$1', $fitxategia->getClientOriginalName())).".zip";
            // get files
            $finder = new Finder();
            $finder->files()->name('pg_*')->in($dest);

            // loop files
            foreach ($finder as $file) {

                // open zip
                if ($zip->open($zipName, \ZipArchive::CREATE) !== true) {
                    throw new FileException('Zip file could not be created/opened.');
                }

                // add to zip
                $zip->addFile($file->getRealpath(), basename($file->getRealpath()));

                // close zip
                if(!$zip->close()) {
                    throw new FileException('Zip file could not be closed.');
                }
            }


//            return $zip;
            $response = new Response(file_get_contents($zipName));
            $response->headers->set('Content-Type', 'application/zip');
            $response->headers->set('Content-Disposition', 'attachment;filename="' . $zipName . '"');
            $response->headers->set('Content-length', filesize($zipName));

            @unlink($zipName);

            return $response;
        }

        return $this->render('default/elkartu.html.twig', [
            'form' => $form->createView()
        ]);
    }

}
