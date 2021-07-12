<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
//Para obtener todos los vídeos de un usuario más fácil usamos estas librerías
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Usuarios
 *
 * @ORM\Table(name="users")
 * @ORM\Entity
 */
class User implements \JsonSerializable
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=50, nullable=false)
     */
    private $name;

    /**
     * @var string|null
     *
     * @ORM\Column(name="surname", type="string", length=150, nullable=true, options={"default"="NULL"})
     */
    private $surname = 'NULL';

    /**
     * @var string
     *
     * @ORM\Column(name="email", type="string", length=255, nullable=false)
     */
    private $email;

    /**
     * @var string
     *
     * @ORM\Column(name="password", type="string", length=255, nullable=false)
     */
    private $password;

    /**
     * @var string|null
     *
     * @ORM\Column(name="role", type="string", length=20, nullable=true, options={"default"="NULL"})
     */
    private $role = 'NULL';

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="created_at", type="datetime", nullable=true, options={"default"="current_timestamp()"})
     */
    private $createdAt = 'current_timestamp()';

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="updated_at", type="datetime", nullable=true, options={"default"="current_timestamp()"})
     */
    private $updatedAt = 'current_timestamp()';

    /**
     * @var
     * Para obtener todos los vídeos de un usuario hacemos una relación, donde "targetEntity"
     * apunta al modelo Video y "mappedBy" está mapeada por el modelo usuario
     * @ORM\OneToMany(targetEntity="App\Entity\Video", mappedBy="user")
     * 
     */
    private $videos;
   
    public function __construct($name, $surname, $email, $password, $role, $createdAt, $updatedAt) {
        $this->id=null;
        $this->name=$name;
        $this->surname=$surname;
        $this->email=$email;
        $this->password=$password;
        $this->role=$role;
        $this->createdAt=$createdAt;
        $this->updatedAt=$updatedAt;
        $this->videos = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getSurname(): ?string
    {
        return $this->surname;
    }

    public function setSurname(?string $surname): self
    {
        $this->surname = $surname;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Función que obtiene todos los videos
     * @return Collection|Video[]
     */
    public function getVideos(): Collection
    {
        return $this->videos;
    }

    /**
     * Función que sobreescribe una función de la interfaz "jsonSerializable" en la cual le indicamos
     * las propiedades que queremos serializar (la contraseña obviamente no para no mostrarla) 
     * @return array
     */
    public function jsonSerialize(): Array
    {
        return [
            'id'=>$this->id,
            'name'=>$this->name,
            'surname'=>$this->surname,
            'email'=>$this->email,
            'role'=>$this->role,
            'createdAt'=>$this->createdAt
        ];
    }

    /**
     * Función que ejecuta una consulta
     * @param $em
     * @param $user
     * @param $action
     */
    public function execute($em, User $user, $action): void
    {       
        if ($action=='insert'||$action=='update') {
            //Guardamos o modificamos el usuario en el ORM
            $em->persist($user);
        } else if ($action=='delete') {
            $em->remove($user);
        }
        //Ejecutamos la sentencia en la base de datos
        $em->flush();
    }
}
