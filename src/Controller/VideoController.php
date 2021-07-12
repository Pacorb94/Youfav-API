<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;
use Knp\Component\Pager\PaginatorInterface;
use App\Services\JwtAuth;
use App\Entity\User;
use App\Entity\Video;

class VideoController extends AbstractController
{
    /**
     * Función que crea un vídeo
     * @param $request
     * @param $jwtAuth
     * @return JsonResponse
     */
    public function create(Request $request, JwtAuth $jwtAuth): JsonResponse
    {
        try {
            /*Como para crear un vídeo el usuario debe estar logueado obtenemos el token
            que se manda por la cabecera*/
            $requestToken=$request->headers->get('Authorization');
            if(!$this->requestTokenValidation($requestToken)) 
                return $this->json(['message'=>'No has enviado el token'], 400);
            //Comprueba si se ha iniciado sesión
            if ($jwtAuth->checkLogin($requestToken)) {
                $videoRepo=$this->getDoctrine()->getRepository(Video::class);
                $request=$request->get('json', null);
                if ($request) {
                    $decodedRequest=json_decode($request);  
                    $validator=Validation::createValidator();
                    $title=$decodedRequest->title;
                    //Si no rellena algún campo opcional tendrá por defecto cadena vacía
                    $description=$decodedRequest->description??'';
                    $url=$decodedRequest->url;
                    $status=$decodedRequest->status??'';
                    $message=$this->wrongValidationMessage($validator, $title, $description, $url, $status);
                    //Si hay un mensaje
                    if ($message) return $message;
                    //Si no existe
                    if (!$videoRepo->findOneBy(['url'=>$decodedRequest->url])) {
                        $video=new Video();
                        $userRepo=$this->getDoctrine()->getRepository(User::class);
                        //Obtenemos el User identificado
                        $loggedInUser=$jwtAuth->checkLogin($requestToken, true);
                        $user=$userRepo->findOneBy(['id'=>$loggedInUser->id]);
                        $video->setUser($user);                 
                        if (count($this->titleValidation($validator, $title))==0)$video->setTitle($title);
                        if (count($this->descriptionValidation($validator, $description))==0) 
                            $video->setDescription($description);
                        if (count($this->urlValidation($validator, $url))==0) $video->setUrl($url);
                        if (count($this->statusValidation($validator, $status))==0) $video->setStatus($status);
                        $video->setCreatedAt(new \Datetime('now'));
                        $video->setUpdatedAt(new \Datetime('now'));
                        $em=$this->getDoctrine()->getManager();
                        $video->execute($em, $video, 'insert');
                        return $this->json(['message'=>'Vídeo creado', 'data'=>$video], 201);
                    }
                    return $this->json(['message'=>'No puedes crear un vídeo que ya existe'], 500);
                }
                return $this->json(['message'=>'JSON incorrecto vacío'], 400);  
            }
            return $this->json(['message'=>'No has iniciado sesión'], 500);
        } catch (\Throwable $e) {
            return $this->json(['message'=>$e->getMessage()], 500);
        }
    }

    /**
     * Función que obtiene los videos de un User de manera paginada
     * @param $id
     * @param $request
     * @param $jwtAuth
     * @return JsonResponse
     */
    public function getUserVideos($id, Request $request, JwtAuth $jwtAuth,
    PaginatorInterface $paginator): JsonResponse
    {
        try {
            /*Como para crear un vídeo el User debe estar logueado obtenemos el token
            que se manda por la cabecera*/
            $requestToken=$request->headers->get('Authorization');
            if(!$this->requestTokenValidation($requestToken)) 
                return $this->json(['message'=>'No has enviado el token'], 400);
            if(!$this->idValidation($id)) return $this->json(['message'=>'Id no válido'], 400);
            $userRepo=$this->getDoctrine()->getRepository(User::class);
            $user=$userRepo->findOneBy(['id'=>$id]);
            //Si existe
            if ($user) {
                //Comprobamos si inició sesión
                if ($jwtAuth->checkLogin($requestToken)) {
                    $loggedInUser=$jwtAuth->checkLogin($requestToken, true);
                    //Si el id del usuario identificado es igual al de la base de datos
                    if ($loggedInUser->id==$user->getId()) {
                        /*Como el parámetro página viene por GET usamos la propiedad "query" y por defecto si 
                        no viene nada tendrá el valor 1*/
                        $page=$request->query->getInt('page', 1);
                        $em=$this->getDoctrine()->getManager();       
                        //Paginator necesita sentencias en DQL
                        $dql="select v from App\Entity\Video v where v.user = $id order by v.id desc";
                        $query=$em->createQuery($dql);
                        //Los vídeos por página que se verán
                        define('VIDEOSPERPAGE', 5);
                        //Llamamos al servicio
                        $pagination=$paginator->paginate($query, $page, VIDEOSPERPAGE);
                        $totalVideos=$pagination->getTotalItemCount();
                        $data=[
                            'videosNumber'=>$totalVideos,
                            'currentPage'=>$page,
                            'videosPerPage'=>VIDEOSPERPAGE, 
                            'totalPages'=>ceil($totalVideos/VIDEOSPERPAGE),
                            'videos'=>$pagination,
                            'user'=>$id
                        ];
                        return $this->json($data);
                    }
                    return $this->json(['message'=>'No puedes obtener vídeos de otros usuarios'], 400);   
                }
                return $this->json(['message'=>'No has iniciado sesión'], 500);
            }
            return $this->json(['message'=>'Usuario/a no existe'], 404);
        } catch (\Throwable $e) {
            return $this->json(['message'=>$e->getMessage()], 500);
        }
    }

    /**
     * Función que obtiene los detalles de un vídeo
     * @param $id
     * @param $request
     * @param $jwtAuth
     * @return JsonResponse
     */
    public function getVideoDetails($id, Request $request, JwtAuth $jwtAuth): JsonResponse
    {
        try {
            /*Como para crear un vídeo el User debe estar logueado obtenemos el token
            que se manda por la cabecera*/
            $requestToken=$request->headers->get('Authorization');
            if(!$this->requestTokenValidation($requestToken)) 
                return $this->json(['message'=>'No has enviado el token'], 400);
            if(!$this->idValidation($id)) return $this->json(['message'=>'Id no válido'], 400);
            if ($jwtAuth->checkLogin($requestToken)) {
                $loggedInUser=$jwtAuth->checkLogin($requestToken, true);
                $videoRepo=$this->getDoctrine()->getRepository(Video::class);
                $video=$videoRepo->findOneBy(['id'=>$id]);
                if ($video) {
                    //Si el id del User identificado es igual al creador del vídeo
                    if ($loggedInUser->id==$video->getUser()->getId()) {
                        if ($video) return $this->json($video);
                    }
                    return $this->json(['message'=>'No puedes obtener vídeos de otros usuarios'], 400);
                }
                return $this->json(['message'=>'No existe ese vídeo'], 404);              
            }
            return $this->json(['message'=>'No has iniciado sesión'], 500);
        } catch (\Throwable $e) {
            return $this->json(['message'=>$e->getMessage()], 500);
        }
    }

    /**
     * Función que modifica un vídeo
     * @param $id
     * @param $request
     * @param $jwtAuth
     * @return JsonResponse
     */
    public function update($id, Request $request, JwtAuth $jwtAuth): JsonResponse
    {
        try {
            /*Como para crear un vídeo el User debe estar logueado obtenemos el token
            que se manda por la cabecera*/
            $requestToken=$request->headers->get('Authorization');
            if(!$this->requestTokenValidation($requestToken)) 
                return $this->json(['message'=>'No has enviado el token'], 400);
            if(!$this->idValidation($id)) return $this->json(['message'=>'Id no válido'], 400);
            $request=$request->get('json', null);
            if ($request) {
                $videoRepo=$this->getDoctrine()->getRepository(Video::class);
                $video=$videoRepo->findOneBy(['id'=>$id]);
                //Si existe
                if ($video) {  
                    $decodedRequest=json_decode($request);        
                    $validator=Validation::createValidator(); 
                    $title=$decodedRequest->title??$video->gettitle();
                    $description=$decodedRequest->description??$video->getDescription();
                    $url=$decodedRequest->url??$video->getUrl();
                    $status=$decodedRequest->status??$video->getStatus();
                    $message=$this->wrongValidationMessage($validator, $title, $description, $url, 
                        $status);
                    if ($message) return $message;
                    if ($jwtAuth->checkLogin($requestToken)) {
                        $loggedInUser=$jwtAuth->checkLogin($requestToken, true);
                        //Si el id del User identificado es igual al creador del vídeo
                        if ($loggedInUser->id==$video->getUser()->getId()) {
                            if (count($this->titleValidation($validator, $title))==0) 
                                $video->setTitle($title);

                            if (count($this->descriptionValidation($validator, $description))==0) 
                                $video->setDescription($description);
                        
                            if (count($this->urlValidation($validator, $url))==0) $video->setUrl($url);

                            if (count($this->statusValidation($validator, $status))==0) 
                                $video->setStatus($status);             
                            $video->setUpdatedAt(new \Datetime('now'));
                            $em=$this->getDoctrine()->getManager();
                            $video->execute($em, $video, 'update');
                            return $this->json(['message'=>'Vídeo modificado', 'data'=>$video]);
                        }
                        return $this->json(['message'=>'No puedes modificar vídeos de otros usuarios'], 400);                       
                    }
                    return $this->json(['message'=>'No has iniciado sesión'], 500);        
                }
                return $this->json(['message'=>'Ese vídeo no existe'], 404);
            }
            return $this->json(['message'=>'JSON incorrecto o vacío'], 400);
        } catch (\Throwable $e) {
            return $this->json(['message'=>$e->getMessage()], 500);
        }
    }

    /**
     * Función que elimina un vídeo
     * @param $id
     * @param $request
     * @param $jwtAuth
     * @return JsonResponse
     */
    public function delete($id, Request $request, JwtAuth $jwtAuth): JsonResponse
    {
        try {
            $requestToken=$request->headers->get('Authorization');
            if (!$this->requestTokenValidation($requestToken)) 
                return $this->json(['mensaje'=>'No has enviado el token'], 400);
            if (!$this->idValidation($id)) return $this->json(['message'=>'Id no válido'], 400);
            if ($jwtAuth->checkLogin($requestToken)) {
                $videoRepo=$this->getDoctrine()->getRepository(Video::class);
                $video=$videoRepo->findOneBy(['id'=>$id]);
                if ($video) {
                    $loggedInUser=$jwtAuth->checkLogin($requestToken, true);
                    //Si el id del User identificado es igual al creador del vídeo
                    if ($loggedInUser->id==$video->getUser()->getId()) {
                        $em=$this->getDoctrine()->getManager();
                        $video->execute($em, $video, 'delete');
                        return $this->json(['message'=>'Vídeo eliminado', 'data'=>$video]);
                    }
                    return $this->json(['message'=>'No puedes eliminar vídeos de otros usuarios'], 400);
                }
                return $this->json(['message'=>'No existe ese vídeo'], 400);      
            }
            return $this->json(['message'=>'No has iniciado sesión'], 500);
        } catch (\Throwable $e) {
            return $this->json(['message'=>$e->getMessage()], 500);
        }
    }

    /**
     * Función que valida el token de la petición
     * @param $requestToken
     * @return Bool
     */
    public function requestTokenValidation($requestToken): Bool
    {
        if ($requestToken) return true;
        return false;
    }

    /**
     * Función que valida el id
     * @param $id
     * @return Bool
     */
    public function idValidation($id): Bool
    {
        if (is_numeric($id)) return true;
        return false;
    }

    /**
     * Función que valida el título
     * @param $validator
     * @param $title
     */
    public function titleValidation($validator, $title)
    {
        $titleValidation=$validator->validate($title, 
            [
                new Assert\NotBlank(),
                new Assert\Length(
                    [
                        'max'=>255
                    ]
                )
            ]
        );
        return $titleValidation;
    }

    /**
     * Función que valida la descripción
     * @param $validator
     * @param $description
     */
    public function descriptionValidation($validator, $description)
    {
        $descriptionValidation=$validator->validate($description, 
            new Assert\Length(
                [
                    'max'=>65535
                ]        
            )   
        );
        return $descriptionValidation;          
    }

     /**
     * Función que valida la url
     * @param $validator
     * @param $url
     */
    public function urlValidation($validator, $url)
    {
        $urlValidation=$validator->validate($url, 
            [
                new Assert\NotBlank(),
                new Assert\Url()
            ]
        );
        return $urlValidation;
    }

    /**
     * Función que valida el status
     * @param $validator
     * @param $status
     */
    public function statusValidation($validator, $status)
    {
        $statusValidation=$validator->validate($status, 
            new Assert\Length(
                [
                    'max'=>50
                ]        
            )              
        );
        return $statusValidation;          
    }

    /**
     * Función que muestra un mensaje cuando hay algún campo incorrecto
     * @param $validator
     * @param $title
     * @param $description
     * @param $url
     * @param $status
     * @return
     */
    public function wrongValidationMessage($validator, $title, $description, $url, $status)
    {
        if (count($this->titleValidation($validator, $title))!=0
        ||count($this->descriptionValidation($validator, $description))!=0
        ||count($this->urlValidation($validator, $url))!=0||count($this->statusValidation($validator, $status))!=0) 
            return $this->json(['message'=>'Hay algún campo incorrecto'], 400);
    }
}
