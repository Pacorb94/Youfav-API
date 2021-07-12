<?php 
    namespace App\Services;

    use Firebase\JWT\JWT;
    use App\Entity\User;

    /**
     * Clase que almacena el servicio de autenticación
     */
    class JwtAuth{
        
        //Es el entity manager pasado en "config/services.yaml"
        private $manager;
        //Esta clave le podemos dar cualquier valor
        private $key;

        public function __construct($manager) {
            $this->manager = $manager;
            $this->key='claveJWT';
        }

        /**
         * Función que identifica al usuario
         * @param $email
         * @param $password
         * @param $getToken
         * @return
         */
        public function identify($email, $password, $getToken=null)
        {
            try {         
                $userRepo=$this->manager->getRepository(User::class);
                $user=$userRepo->findOneBy(['email'=>$email, 'password'=>$password]);  
                //Si existe el usuario
                if ($user) {                
                    /*Creamos el token, "iat" es el tiempo en el que se creó el token
                    y "exp" es en el que expira el token */
                    $token=[
                        'id'=>$user->getId(),
                        'name'=>$user->getName(),
                        'surname'=>$user->getSurname(),
                        'email'=>$user->getEmail(),
                        'iat'=>time(),
                        'exp'=>time()+(7*24*60*60)
                    ];
                    //Codificamos el token
                    $encodedToken=JWT::encode($token, $this->key, 'HS256');
                    //Si nos llega por parámetros getToken sino devolvemos el usuario logueado
                    if ($getToken) return ['token'=>$encodedToken];            
                    $decodedToken=JWT::decode($encodedToken, $this->key, ['HS256']);
                    return ['user'=>$decodedToken];
                }
                return ['message'=>'Login incorrecto'];
            } catch (\Throwable $e) {
                return ['message'=>$e->getMessage()];
            }
        }

        /**
         * Función que comprueba si se ha logueado el usuario
         * @param $token
         * @param $identity
         * @return
         */
        public function checkLogin($token, $identity=false)
        {
            try {
                $loggedIn=false;
                $loggedInDecodedUser=JWT::decode($token, $this->key, ['HS256']);
                if ($loggedInDecodedUser) $loggedIn=true;  
                //Si la identidad es correcta devuelve los datos del usuario
                if ($identity) return $loggedInDecodedUser;  
                return $loggedIn;
            }catch(\DomainException $e){
                $loggedIn=false;
                return $loggedIn;
            } catch (\Throwable $e2) {
                return ['message'=>$e2->getMessage()];
            }
        }
    }
?>