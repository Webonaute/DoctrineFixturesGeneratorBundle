<?php

namespace Webonaute\DoctrineFixturesGeneratorBundle\Entity;


use Doctrine\ORM\Mapping as ORM;

/**
 * TestRelated
 * @ORM\Table(name="wbnt_dfgb_testrelated")
 * @ORM\Entity()
 */
class TestRelated
{

    /**
     * @var int
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     * @ORM\Column(type="string", length=60, nullable=true)
     */
    protected $name;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return TestRelated
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return TestRelated
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

}
