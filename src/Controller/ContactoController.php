<?php

namespace App\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Contacto;
use App\Entity\Provincia;
use App\Form\ContactoType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;



class ContactoController extends AbstractController
{
    private $contactos = [
        1 => ["nombre" => "Juan Pérez", "telefono" => "524142432", "email" => "juanp@ieselcaminas.org"],
        2 => ["nombre" => "Ana López", "telefono" => "58958448", "email" => "anita@ieselcaminas.org"],
        5 => ["nombre" => "Mario Montero", "telefono" => "5326824", "email" => "mario.mont@ieselcaminas.org"],
        7 => ["nombre" => "Laura Martínez", "telefono" => "42898966", "email" => "lm2000@ieselcaminas.org"],
        9 => ["nombre" => "Nora Jover", "telefono" => "54565859", "email" => "norajover@ieselcaminas.org"]
    ];

    /*============================================================================================== */
    #[Route('/contacto/nuevo', name: 'nuevo_contacto')]
    public function nuevo(ManagerRegistry $doctrine, SluggerInterface $slugger, Request $request, SessionInterface $session): Response
    {
        if ($this->getUser()) {
        $contacto = new Contacto();

        $formulario = $this->createForm(ContactoType::class, $contacto);
        $formulario->handleRequest($request);

        if ($formulario->isSubmitted() && $formulario->isValid()) {
            $contacto = $formulario->getData();
            $entityManager = $doctrine->getManager();
            $entityManager->persist($contacto);
            $entityManager->flush();
            $file = $formulario->get('file')->getData();
                    if ($file) {
                        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                        // This is needed to safely include the file name as part of the URL
                        $safeFilename = $slugger->slug($originalFilename);
                        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
    
                        // Move the file to the directory where images are stored
                        try {
                            $file->move(
                                $this->getParameter('images_directory'),
                                $newFilename
                            );
                            
                            $filesystem = new Filesystem();
                            $filesystem->copy(
                                $this->getParameter('images_directory') . '/' . $newFilename,
                                true
                            );
                        } catch (FileException $e) {
                            // Handle the exception if something happens during file upload
                        }
    
                        // Update the 'file$filename' property to store the PDF file name
                        // instead of its contents
                        $contacto->setFile($newFilename);
                    }
    
                    // Flush the changes to the database
                    $entityManager->flush();
    
                    return $this->redirectToRoute('ficha_contacto', ["codigo" => $contacto->getId()]);
                }
            } else {
                $session->set('redirect_to', 'ficha_contacto');
                return $this->redirectToRoute("app_login");
            }

        return $this->render('nuevo.html.twig', array('formulario' => $formulario->createView()));
    }
    /*============================================================================================== */

    /*============================================================================================== */
    #[Route('/contacto/editar/{codigo}', name: 'editar_contacto')]
    public function editar(ManagerRegistry $doctrine, Request $request, SluggerInterface $slugger, $codigo, SessionInterface $session)
    {
        if ($this->getUser()) {
            $repositorio = $doctrine->getRepository(Contacto::class);
    
            $contacto = $repositorio->find($codigo);
            if ($contacto) {
                $formulario = $this->createForm(ContactoType::class, $contacto);
    
                $formulario->handleRequest($request);
    
                if ($formulario->isSubmitted() && $formulario->isValid()) {
                    $contacto = $formulario->getData();
                    $entityManager = $doctrine->getManager();
                    $entityManager->persist($contacto);
                    
                    $file = $formulario->get('file')->getData();
                    if ($file) {
                        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                        // This is needed to safely include the file name as part of the URL
                        $safeFilename = $slugger->slug($originalFilename);
                        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();
    
                        // Move the file to the directory where images are stored
                        try {
                            $file->move(
                                $this->getParameter('images_directory'),
                                $newFilename
                            );
                            
                            $filesystem = new Filesystem();
                            $filesystem->copy(
                                $this->getParameter('images_directory') . '/' . $newFilename,
                                true
                            );
                        } catch (FileException $e) {
                            // Handle the exception if something happens during file upload
                        }
    
                        // Update the 'file$filename' property to store the PDF file name
                        // instead of its contents
                        $contacto->setFile($newFilename);
                    }
    
                    // Flush the changes to the database
                    $entityManager->flush();
    
                    return $this->redirectToRoute('ficha_contacto', ["codigo" => $contacto->getId()]);
                }
    
                return $this->render('editar.html.twig', [
                    'formulario' => $formulario->createView(),
                    'images' => $contacto,
                ]);
            } else {
                return $this->render('ficha_contacto.html.twig', [
                    'contacto' => null,
                ]);
            }
        } else {
            $session->set('redirect_to', 'editar_contacto');
            $session->set('codigo', $codigo);
            return $this->redirectToRoute("app_login");
        }
    }
    /*============================================================================================== */

    /*============================================================================================== */
    #[Route('/contacto/insertar', name: 'insertar_contacto')]
    public function insertar(ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        foreach ($this->contactos as $c) {
            $contacto = new Contacto();
            $contacto->setNombre($c["nombre"]);
            $contacto->setTelefono($c["telefono"]);
            $contacto->setEmail($c["email"]);
            $entityManager->persist($contacto);
        }

        try {
            $entityManager->flush();
            return new Response("Contactos insertados");
        } catch (\Exception $e) {
            return new Response("Error insertando objetos");
        }
    }
    /*============================================================================================== */

    /*============================================================================================== */
    #[Route('/contactos', name: 'app_listado_contactos')]
    public function index(ManagerRegistry $doctrine, SessionInterface $session): Response
    {
        if ($this->getUser()) {

            $repositorio = $doctrine->getRepository(Contacto::class);
            $contactos = $repositorio->findAll();

            return $this->render('lista_contactos.html.twig', [
                'controller_name' => 'ListadoController', "contactos" => $contactos
            ]);
        } else {
            $session->set('redirect_to', 'app_listado_contactos');
            return $this->redirectToRoute("app_login");
        }
    }
    /*============================================================================================== */

    /*============================================================================================== */
    #[Route('/contacto/{codigo}', name: 'ficha_contacto')]
    public function ficha(ManagerRegistry $doctrine, $codigo, SessionInterface $session): Response
    {
        if ($this->getUser()) {
            $repositorio = $doctrine->getRepository(Contacto::class);
            $contacto = $repositorio->find($codigo);

            return $this->render('ficha_contacto.html.twig', ['contacto' => $contacto]);
        } else {
            $session->set('redirect_to', 'ficha_contacto');
            $session->set('codigo', $codigo);
            return $this->redirectToRoute("app_login");
        }
    }
    /*============================================================================================== */

    /*============================================================================================== */
    #[Route('/contacto/buscar/{texto}', name: 'app_contacto_buscar')]
    public function buscar(ManagerRegistry $doctrine, $texto): Response
    {
        $repositorio = $doctrine->getRepository(Contacto::class);
        $contactos = $repositorio->findByName($texto);

        return $this->render('lista_contactos.html.twig', ['contactos' => $contactos]);
    }
    /*============================================================================================== */

    /*============================================================================================== */
    #[Route('/contacto/update/{id}/{nombre}', name: 'app_contacto_modificar')]
    public function update(ManagerRegistry $doctrine, $id, $nombre): Response
    {
        $entityManager = $doctrine->getManager();
        $repositorio = $doctrine->getRepository(Contacto::class);
        $contacto = $repositorio->find($id);
        if ($contacto) {
            $contacto->setNombre($nombre);
            try {
                $entityManager->flush();
                return $this->render('ficha_contacto.html.twig', ['contacto' => $contacto]);
            } catch (\Exception $e) {
                return new Response("Error insertando objetos");
            }
        } else {
            return $this->render('ficha_contacto.html.twig', ['contacto' => null]);
        }
    }
    /*============================================================================================== */

    /*============================================================================================== */
    #[Route('/contacto/delete/{id}', name: 'app_contacto_modificar')]
    public function delete(ManagerRegistry $doctrine, $id, SessionInterface $session): Response
    {
        if ($this->getUser()) {
            $entityManager = $doctrine->getManager();
            $repositorio = $doctrine->getRepository(Contacto::class);
            $contacto = $repositorio->find($id);
            if ($contacto) {
                try {
                    $entityManager->remove($contacto);
                    $entityManager->flush();
                    return $this->redirectToRoute("app_listado_contactos");
                } catch (\Exception $e) {
                    return new Response("Error eliminando contacto");
                }
            } else {
                return $this->render('ficha_contacto.html.twig', ['contacto' => $contacto]);
            }
        } else {
            $session->set('redirect_to', 'ficha_contacto');
            return $this->redirectToRoute("app_login");
        }
    }
    /*============================================================================================== */

    /*============================================================================================== */
    #[Route('/contacto/insertarConProvincia', name: 'app_contacto_insertar_con_provincia')]
    public function insertarConProvincia(ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $provincia = new Provincia();

        $provincia->setNombre("Alicante");
        $contacto = new Contacto();

        $contacto->setNombre("Inserción de prueba con provincia");
        $contacto->setTelefono("900220022");
        $contacto->setEmail("Inserción de prueba provincia@contacto.es");
        $contacto->setProvincia($provincia);

        $entityManager->persist($provincia);
        $entityManager->persist($contacto);

        $entityManager->flush();
        return $this->render('ficha_contacto.html.twig', ['contacto' => $contacto]);
    }
    /*============================================================================================== */

    /*============================================================================================== */
    #[Route('/contacto/insertarSinProvincia', name: 'app_contacto_insertar_con_provincia')]
    public function insertarSinProvincia(ManagerRegistry $doctrine): Response
    {
        $entityManager = $doctrine->getManager();
        $repositorio = $doctrine->getRepository(Provincia::class);

        $provincia = $repositorio->findOneBy(["nombre" => "Alicante"]);

        $contacto = new Contacto();

        $contacto->setNombre("Inserción de prueba sin provincia");
        $contacto->setTelefono("900220022");
        $contacto->setEmail("Inserción de prueba provincia@contacto.es");
        $contacto->setProvincia($provincia);

        $entityManager->persist($contacto);

        $entityManager->flush();
        return $this->render('ficha_contacto.html.twig', ['contacto' => $contacto]);
    }
    /*============================================================================================== */
}
