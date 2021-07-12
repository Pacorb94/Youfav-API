<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;
use App\Services\JwtAuth;
use App\Entity\User;
use App\Entity\Video;

class UserController extends AbstractController
{

    /**
     * Función que registra un usuario
     * @param $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        try {
            //Almacenamos los datos de la petición y sino tiene nada le damos valor nulo
            $request=$request->get('json', null);
            //Si tiene datos
            if ($request){
                $decodedRequest=json_decode($request);
                if ($this->dataValidation($decodedRequest, 'register')) {
                    $userRepo=$this->getDoctrine()->getRepository(User::class);
                    //Si no existe
                    if (!$userRepo->findOneBy(['email'=>$decodedRequest->email])){
                        $user=new User($decodedRequest->name, $decodedRequest->surname, 
                            $decodedRequest->email, hash('sha256', $decodedRequest->password), 'User', 
                            new \Datetime('now'), new \Datetime('now'));                                     
                        //Obtenemos el Entity Manager
                        $em=$this->getDoctrine()->getManager();
                        $user->execute($em, $user, 'insert');
                        /*Como sólo devuelve un objeto json nos basta devolver la respuesta así pero si fueran
                        más de 1 habría que llamar a "resjson"*/
                        return $this->json(['message'=>'Usuario/a registrado/a', 'data'=>$user], 201);
                    }
                    return $this->json(['message'=>'No puedes registrar un/a usuario/a que ya existe'], 500);                            
                }
                return $this->json(['message'=>'Hay algún campo erróneo'], 400);      
            }
            return $this->json(['message'=>'JSON incorrecto o vacío'], 400);
        } catch (\Throwable $e) {
            return $this->json(['message'=>$e->getMessage()], 500);
        } 
    }

    /**
     * Función que inicia sesión
     * @param $request
     * @param $jwtAuth
     * @return JsonResponse
     */
    public function login(Request $request, JwtAuth $jwtAuth): JsonResponse
    {
        try {
            //Almacenamos los datos de la petición y sino tiene nada le damos valor nulo
            $request=$request->get('json', null);
            //Si tiene datos
            if ($request) {
                $decodedRequest=json_decode($request);
                if ($this->dataValidation($decodedRequest, 'login')) {
                    $encryptedPassword=hash('sha256', $decodedRequest->password);
                    //Si nos llega tendrá ese valor sino nulo
                    $getToken=$decodedRequest->getToken??null;               
                    $token=null;
                    //Si tiene valor devuelve el token sino el usuario
                    if ($getToken) {
                        $token=$jwtAuth->identify($decodedRequest->email, $encryptedPassword, 
                            $decodedRequest->getToken);
                    }else{
                        $token=$jwtAuth->identify($decodedRequest->email, $encryptedPassword);
                    }
                    return $this->json($token);   
                }
                return $this->json(['message'=>'Hay algún campo erróneo'], 400);      
            }
            return $this->json(['message'=>'JSON incorrecto o vacío'], 400);
        } catch (\Throwable $e) {
            return $this->json(['message'=>$e->getMessage()], 500);
        }
    }

    /**
     * Funión que modifica un usuario
     * @param $id
     * @param $request
     * @param $jwtAuth
     * @return JsonResponse
     */
    public function update($id, Request $request, JwtAuth $jwtAuth): JsonResponse
    {
        try {
            /*Como para crear un vídeo el usuario debe estar logueado obtenemos el token
            que se manda por la cabecera*/
            $requestToken=$request->headers->get('Authorization');
            if(!$this->requestTokenValidation($requestToken)) 
                return $this->json(['message'=>'No has enviado el token'], 400);
            if(!$this->idValidation($id)) return $this->json(['message'=>'Id no válido'], 400);
            $request=$request->get('json', null);
            if ($request) {   
                $userRepo=$this->getDoctrine()->getRepository(User::class);
                $user=$userRepo->findOneBy(['id'=>$id]);
                //Si existe
                if ($user) {       
                    $decodedRequest=json_decode($request);
                    $validator=Validation::createValidator();
                    //Si no modifica algún campo tendrá el valor de la base de datos
                    $name=$decodedRequest->name??$user->getName();
                    $surname=$decodedRequest->surname??$user->getSurname();
                    $email=$decodedRequest->email??$user->getEmail();
                    $message=$this->wrongValidationMessage($validator, $name, $surname, 
                        $email);
                    if ($message) return $message;
                    if ($jwtAuth->checkLogin($requestToken)) {
                        $loggedInUser=$jwtAuth->checkLogin($requestToken, true);
                        //Si el id de la url es igual al del usuario identificado
                        if ($id==$loggedInUser->id) {
                            if (count($this->nameValidation($validator, $name))==0) 
                                $user->setName($name);  
                            if(count($this->surnameValidation($validator, $surname))==0) 
                                $user->setSurname($surname);             
                            if(count($this->emailValidation($validator, $email))==0) $user->setEmail($email);                    
                            $user->setUpdatedAt(new \Datetime ('now'));
                            $em=$this->getDoctrine()->getManager();
                            $user->execute($em, $user, 'update');
                            return $this->json(['message'=>'Usuario/a modificado/a', 'data'=>$user]); 
                        }
                        return $this->json(['message'=>'No puedes modificar datos de otros usuarios'], 400);      
                    }
                    return $this->json(['message'=>'No has iniciado sesión'], 500);       
                }   
                return $this->json(['message'=>'No existe ese/a usuario/a'], 404);                         
            }
            return $this->json(['message'=>'JSON incorrecto o vacío'], 400); 
        } catch (\Throwable $e) {
            return $this->json(['message'=>$e->getMessage()], 500);
        }
    }

    /**
     * Función que elimina un usuario
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
                return $this->json(['message'=>'No has enviado el token'], 400);
            if (!$this->idValidation($id)) return $this->json(['message'=>'Id no válido'], 400);
            if ($jwtAuth->checkLogin($requestToken)) {
                $userRepo=$this->getDoctrine()->getRepository(User::class);
                $user=$userRepo->findOneBy(['id'=>$id]);
                if ($user) {
                    $loggedInUser=$jwtAuth->checkLogin($requestToken, true);
                    if ($loggedInUser->id==$user->getId()) {
                        $em=$this->getDoctrine()->getManager();
                        $videoRepo=$this->getDoctrine()->getRepository(Video::class);
                        //Para borrar antes el usuario hay que borrar sus vídeos
                        $videos=$videoRepo->findBy(['user'=>$loggedInUser->id]);
                        foreach ($videos as $video) $video->execute($em, $video, 'delete');
                        $user->execute($em, $user, 'delete');
                        return $this->json(['message'=>'Usuario/a borrado/a', 'data'=>$user]);
                    }
                    return $this->json(['message'=>'No puedes borrar otros usuarios'], 500);
                }
                return $this->json(['message'=>'No existe ese/a usuario/a'], 404);
            }
            return $this->json(['message'=>'No has iniciado sesión'], 500);
        } catch (\Throwable $e) {
            return $this->json(['message'=>$e->getMessage()], 500);
        }
    }

    /**
     * Función que convierte unos datos a json y devuelve una 
     * respuesta con esos datos
     * @param $data
     * @return Response
     */
    private function resjson($data): Response
    {
        //Usamos el servicio "serialize" con la función "get" y le decimos en qué formato queremos
        $jsonData=$this->get('serializer')->serialize($data, 'json');
        $response=new Response();
        //Damos valor a la respuesta
        $response->setContent($jsonData);
        //Configuramos la respuesta
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    /**
     * Función que agrupa los validadores de las funciones registrar e iniciar sesión
     * @param $decodedRequest
     * @param $action
     * @return
     */
    public function dataValidation($decodedRequest, $action)
    {
        try {
            //Instanciamos el validador
            $validator=Validation::createValidator();
            if ($action=='register') {
                //Si no hay errores
                if (count($this->nameValidation($validator, $decodedRequest->name))==0
                &&count($this->surnameValidation($validator, $decodedRequest->surname))==0
                &&count($this->emailValidation($validator, $decodedRequest->email))==0
                &&count($this->passwordValidation($validator, $decodedRequest->password))==0)
                    return true;
                return false;
            }else if($action=='login'){
                if (count($this->emailValidation($validator, $decodedRequest->email))==0
                &&count($this->passwordValidation($validator, $decodedRequest->password))==0) 
                    return true;
                return false; 
            }
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
     * Función que valida el nombre
     * @param $validator
     * @param $name
     * @return 
     */
    public function nameValidation($validator, $name)
    {  
        $nameValidation=$validator->validate($name, 
            [
                new Assert\NotBlank(),
                new Assert\Regex(
                    [
                        'pattern'=>'/[\d]+/',
                        'match'=>false
                    ]
                ),
                new Assert\Length(
                    [
                        'max'=>50
                    ]
                )
            ]
        );
        return $nameValidation;
    }

    /**
     * Función que valida los apellidos
     * @param $validator
     * @param $surname
     * @return 
     */
    public function surnameValidation($validator, $surname)
    {
        $surnameValidation=$validator->validate($surname, 
            [
                new Assert\NotBlank(),
                new Assert\Regex(
                    [
                        'pattern'=>'/[\d]+/',
                        'match'=>false
                    ]
                ),
                new Assert\Length(
                    [
                        'max'=>150
                    ]
                )
            ]
        );
        return $surnameValidation;
    }

    /**
     * Función que valida el email
     * @param $validator
     * @param $email
     * @return 
     */
    public function emailValidation($validator, $email)
    {
        $emailValidation=$validator->validate($email, 
            [
                new Assert\NotBlank(),
                new Assert\Email(),
                new Assert\Length(
                    [
                        'max'=>255
                    ]
                )
            ]
        );
        return $emailValidation;
    }

    /**
     * Función que valida la contraseña
     * @param $validator
     * @param $password
     * @return 
     */
    public function passwordValidation($validator, $password)
    {
        $passwordValidation=$validator->validate($password, 
            [
                new Assert\NotBlank(),
                new Assert\Regex(
                    [
                        'pattern'=>'/^[\s]+$/',
                        'match'=>false
                    ]
                ),
                new Assert\Length(
                    [
                        'max'=>255
                    ]
                ) 
            ]
        );
        return $passwordValidation;
    }

    /**
     * Función que muestra un mensaje cuando hay algún campo incorrecto
     * @param $validator
     * @param $name
     * @param $surname
     * @param $email
     * @return
     */
    public function wrongValidationMessage($validator, $name, $surname, $email)
    {
        if (count($this->nameValidation($validator, $name))!=0
        ||count($this->surnameValidation($validator, $surname))!=0
        ||count($this->emailValidation($validator, $email))!=0) 
            return $this->json(['message'=>'Hay algún campo incorrecto'], 400);
    }
}
