<?php

namespace Webonaute\DoctrineFixturesGeneratorBundle\Entity;


use Doctrine\ORM\Mapping as ORM;

/**
 * Test
 * @ORM\Table(name="wbnt_dfgb_test")
 * @ORM\Entity()
 */
class Test
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
     * @var TestRelated
     * @ORM\ManyToOne(targetEntity="Webonaute\DoctrineFixturesGeneratorBundle\Entity\TestRelated")
     * @ORM\JoinColumn(name="testrelated_id", referencedColumnName="id", onDelete="cascade")
     */
    protected $testRelated;

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
     * @return Test
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
     * @return Test
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return TestRelated
     */
    public function getTestRelated()
    {
        return $this->testRelated;
    }

    /**
     * @param TestRelated $testRelated
     *
     * @return Test
     */
    public function setTestRelated($testRelated)
    {
        $this->testRelated = $testRelated;

        return $this;
    }

}
