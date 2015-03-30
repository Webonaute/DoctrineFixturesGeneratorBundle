<?php
/* This file is part of the Webonaute package.
 *
 * (c) Mathieu Delisle <mdelisle@webonaute.ca>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webonaute\DoctrineFixturesGeneratorBundle\Tool;

use Doctrine\ORM\Mapping\ClassMetadataInfo;

/**
 * Generic class used to generate PHP5 fixture classes from existing data.
 *     [php]
 *     $classes = $em->getClassMetadataFactory()->getAllMetadata();
 *     $generator = new \Doctrine\ORM\Tools\EntityGenerator();
 *     $generator->setGenerateAnnotations(true);
 *     $generator->setGenerateStubMethods(true);
 *     $generator->setRegenerateEntityIfExists(false);
 *     $generator->setUpdateEntityIfExists(true);
 *     $generator->generate($classes, '/path/to/generate/entities');
 *
 * @author  Mathieu Delisle <mdelisle@webonaute.ca>
 */
class FixtureGenerator
{

    /**
     * @var string
     */
    protected static $classTemplate
        = '<?php

<namespace>

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\Mapping\ClassMetadata;
<use>

/**
 * Generated by Webonaute\DoctrineFixtureGenerator.
 *
 */
<fixtureClassName>
{

<spaces>/**
<spaces> * Set loading order.
<spaces> *
<spaces> * @return int
<spaces> */
<spaces>public function getOrder()
<spaces>{
<spaces><spaces>return <order>;
<spaces>}

<fixtureBody>
}
';

    /**
     * @var string
     */
    protected static $getItemFixtureTemplate
        = '
    <spaces>$item<itemCount> = new <entityName>();<itemStubs>
    <spaces>$manager->persist($item<itemCount>);
';

    /**
     * @var string
     */
    protected static $getLoadMethodTemplate
        = '
<spaces>/**
<spaces> * {@inheritDoc}
<spaces> */
<spaces>public function load(ObjectManager $manager)
<spaces>{
<spaces><spaces>$manager->getClassMetaData(get_class(new <entityName>()))->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
<spaces><fixtures>
<spaces>
<spaces><spaces>$manager->flush();
<spaces>}
';

    /**
     * @var bool
     */
    protected $backupExisting = true;

    /**
     * @var string
     */
    protected $bundleNameSpace = "";

    /**
     * The class all generated entities should extend.
     *
     * @var string
     */
    protected $classToExtend = "AbstractFixture implements OrderedFixtureInterface";

    /**
     * The extension to use for written php files.
     *
     * @var string
     */
    protected $extension = '.php';

    /**
     * @var string
     */
    protected $fixtureName = "";

    /**
     * Whether or not the current ClassMetadataInfo instance is new or old.
     *
     * @var boolean
     */
    protected $isNew = true;

    /**
     * Array of data to generate item stubs.
     *
     * @var array
     */
    protected $items = array();

    /**
     * @var ClassMetadataInfo
     * @return FixtureGenerator
     */
    protected $metadata = null;

    /**
     * Number of spaces to use for indention in generated code.
     */
    protected $numSpaces = 4;

    /**
     * The actual spaces to use for indention.
     *
     * @var string
     */
    protected $spaces = '    ';

    /**
     * @var array
     */
    protected $staticReflection = array();

    /**
     * Constructor.
     */
    public function __construct()
    {

    }

    /**
     * Generates and writes entity classes for the given array of ClassMetadataInfo instances.
     *
     * @param string $outputDirectory
     *
     * @return void
     */
    public function generate($outputDirectory)
    {
        $this->writeFixtureClass($outputDirectory);
    }

    /**
     * Generates a PHP5 Doctrine 2 entity class from the given ClassMetadataInfo instance.
     *
     * @return string
     */
    public function generateFixtureClass()
    {

        if (is_null($this->getMetadata())) {
            throw new \RuntimeException("No metadata set.");
        }

        $placeHolders = array(
            '<namespace>',
            '<fixtureClassName>',
            '<fixtureBody>',
            '<use>',
            '<order>',
        );

        $replacements = array(
            $this->generateFixtureNamespace(),
            $this->generateFixtureClassName(),
            $this->generateFixtureBody(),
            $this->generateUse(),
            $this->generateOrder(),
        );

        $code = str_replace($placeHolders, $replacements, self::$classTemplate);

        return str_replace('<spaces>', $this->spaces, $code);
    }

    /**
     * @param object $item
     *
     * @return string
     */
    public function generateFixtureItemStub($item)
    {
        $id = $item->getId();

        $code = "";
        $reflexion = new \ReflectionClass($item);
        $properties = $reflexion->getProperties();

        foreach ($properties as $property) {
            $property->setAccessible(true);
            $name = $property->getName();
            if (strpos($name, '_')) {
                $_names = explode('_', $property->getName());
                foreach ($_names as $k => $_name) {
                    $_names[$k] = ucfirst($_name);
                }
                $name = implode('', $_names);
            }
            $setter = "set" . ucfirst($name);
            $getter = "get" . ucfirst($name);
            $comment = "";
            if (method_exists($item, $setter)) {
                $value = $property->getValue($item);

                if (is_integer($value)) {
                    $setValue = $value;
                } elseif (is_bool($value)) {
                    if ($value === true) {
                        $setValue = 1;
                    } else {
                        $setValue = 0;
                    }
                } elseif ($value instanceof \DateTime) {
                    $setValue = "new \\DateTime(\"" . $value->format("Y-m-d H:i:s") . "\")";
                } elseif (is_object($value)) {
                    //check reference.
                    $setValue = "";
                    $comment = "//";
                } elseif (is_array($value)) {
                    $setValue = "unserialize('" . serialize($value) . "')";
                } else {
                    $setValue = '"' . $value . '"';
                }

                $code .= "\n<spaces><spaces>{$comment}\$item{$id}->{$setter}({$setValue});";
            }
        }

        return $code;
    }

    /**
     * @return string
     */
    public function getBundleNameSpace()
    {
        return $this->bundleNameSpace;
    }

    /**
     * @param $namespace
     *
     * @return FixtureGenerator
     */
    public function setBundleNameSpace($namespace)
    {
        $this->bundleNameSpace = $namespace;

        return $this;
    }

    /**
     * @return string
     */
    public function getFixtureName()
    {
        return $this->fixtureName;
    }

    /**
     * @param string $fixtureName
     *
     * @return FixtureGenerator
     */
    public function setFixtureName($fixtureName)
    {
        $this->fixtureName = $fixtureName;

        return $this;
    }

    /**
     * @return array
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * @param array $items
     */
    public function setItems(array $items)
    {
        $this->items = $items;
    }

    /**
     * @return ClassMetadataInfo
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    public function setMetadata(ClassMetadataInfo $metadata)
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Sets the extension to use when writing php files to disk.
     *
     * @param string $extension
     *
     * @return void
     */
    public function setExtension($extension)
    {
        $this->extension = $extension;
    }

    /**
     * Sets the number of spaces the exported class should have.
     *
     * @param integer $numSpaces
     *
     * @return void
     */
    public function setNumSpaces($numSpaces)
    {
        $this->spaces = str_repeat(' ', $numSpaces);
        $this->numSpaces = $numSpaces;
    }

    /**
     * Generates and writes entity class to disk for the given ClassMetadataInfo instance.
     *
     * @param string $outputDirectory
     *
     * @return void
     * @throws \RuntimeException
     */
    public function writeFixtureClass($outputDirectory)
    {
        $path = $outputDirectory . '/' . str_replace(
                '\\',
                DIRECTORY_SEPARATOR,
                $this->getFixtureName()
            ) . $this->extension;
        $dir = dirname($path);

        if ( ! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $this->isNew = ! file_exists($path) || (file_exists($path) && $this->regenerateEntityIfExists);

        if ($this->backupExisting && file_exists($path)) {
            $backupPath = dirname($path) . DIRECTORY_SEPARATOR . basename($path) . "~";
            if ( ! copy($path, $backupPath)) {
                throw new \RuntimeException("Attempt to backup overwritten entity file but copy operation failed.");
            }
        }

        file_put_contents($path, $this->generateFixtureClass());
    }

    /**
     * @param $item
     *
     * @return string
     */
    protected function generateFixture($item)
    {

        $placeHolders = array(
            '<itemCount>',
            '<entityName>',
            '<itemStubs>',
        );

        $reflexionClass = new \ReflectionClass($item);

        $replacements = array(
            $item->getId(),
            $reflexionClass->getShortName(),
            $this->generateFixtureItemStub($item)
        );

        $code = str_replace($placeHolders, $replacements, self::$getItemFixtureTemplate);

        return $code;
    }

    /**
     * @return string
     */
    protected function generateFixtureBody()
    {
        $code = self::$getLoadMethodTemplate;
        $classpath = $this->getMetadata()->getName();
        $pos = strrpos($classpath, "\\");

        $code = str_replace("<entityName>", substr($classpath, $pos + 1), $code);
        $code = str_replace("<fixtures>", $this->generateFixtures(), $code);

        return $code;
    }

    /**
     * @return string
     */
    protected function generateFixtureClassName()
    {
        return 'class ' . $this->getClassName() . ' extends ' . $this->getClassToExtend();
    }

    protected function generateFixtureLoadMethod(ClassMetadataInfo $metadata)
    {

    }

    /**
     * @return string
     */
    protected function generateFixtureNamespace()
    {
        return 'namespace ' . $this->getNamespace() . ';';
    }

    protected function generateFixtures()
    {
        $code = "";

        foreach ($this->items as $item) {
            $code .= $this->generateFixture($item);
        }

        return $code;
    }

    /**
     * @return int
     */
    protected function generateOrder()
    {
        return 1;
    }

    protected function generateUse()
    {
        return "use " . $this->getMetadata()->rootEntityName . ";";
    }

    /**
     * @return string
     */
    protected function getClassName()
    {
        return $this->fixtureName;
    }

    /**
     * @return string
     */
    protected function getClassToExtend()
    {
        return $this->classToExtend;
    }

    /**
     * Sets the name of the class the generated classes should extend from.
     *
     * @param string $classToExtend
     *
     * @return void
     */
    public function setClassToExtend($classToExtend)
    {
        $this->classToExtend = $classToExtend;
    }


    /**
     * @return string
     */
    protected function getNamespace()
    {
        return $this->getBundleNameSpace() . '\DataFixture\ORM;';
    }

}