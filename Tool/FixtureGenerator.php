<?php
/* This file is part of the Webonaute package.
 *
 * (c) Mathieu Delisle <mdelisle@webonaute.ca>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webonaute\DoctrineFixturesGeneratorBundle\Tool;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\PersistentCollection;
use Webonaute\DoctrineFixturesGeneratorBundle\Annotation\Property;

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

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\Mapping\ClassMetadata;
<use>

/**
 * Generated by Webonaute\DoctrineFixtureGenerator.
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
    <spaces>$item<itemCount> = new <entityName>(<entityParams>);<itemStubs>
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
<spaces><spaces>$manager->getClassMetadata(<entityName>::class)->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_NONE);
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
    protected $classToExtend = "Fixture implements OrderedFixtureInterface";

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
     * Order of the fixture execution.
     */
    protected $fixtureorder = 1;

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
     * @var string
     */
    protected $referencePrefix = '_reference_';
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

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

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $this->isNew = !file_exists($path) || (file_exists($path) && $this->regenerateEntityIfExists);

        if ($this->backupExisting && file_exists($path)) {
            $backupPath = dirname($path) . DIRECTORY_SEPARATOR . basename($path) . "~";
            if (!copy($path, $backupPath)) {
                throw new \RuntimeException("Attempt to backup overwritten entity file but copy operation failed.");
            }
        }

        file_put_contents($path, $this->generateFixtureClass());
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
     * @param object $item
     *
     * @return string
     */
    public function generateFixtureItemStub($item)
    {
        $class = get_class($item);
        $ids = $this->getRelatedIdsForReference($class, $item);

        $code = "";
        $reflexion = new \ReflectionClass($item);
        $properties = $this->getRecursiveProperties($reflexion);
        $newInstance = $this->getNewInstance($item,$reflexion);

        $code .= "\n<spaces><spaces>\$this->addReference('{$this->referencePrefix}{$this->getEntityNameForRef($class)}{$ids}', \$item{$ids});";

        foreach ($properties as $property) {
            $setValue = null;
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
                $defaultValue = $property->getValue($newInstance);
                if ($value === $defaultValue) {
                    continue;
                } elseif (is_integer($value)) {
                    $setValue = $value;
                } elseif ($value === false || $value === true) {
                    if ($value === true) {
                        $setValue = "true";
                    } else {
                        $setValue = "false";
                    }
                } elseif ($value instanceof \DateTime) {
                    $setValue = "new \\DateTime(\"" . $value->format("Y-m-d H:i:s") . "\")";
                } elseif (is_object($value) && get_class($value) != "Doctrine\\ORM\\PersistentCollection") {
                    if ($this->hasIgnoreProperty($property) === false) {
                        //check reference.
                        $relatedClass = get_class($value);
                        $relatedEntity = ClassUtils::getRealClass($relatedClass);
                        $identifiersIdsString = $this->getRelatedIdsForReference($relatedEntity, $value);
                        $setValue = "\$this->getReference('{$this->referencePrefix}{$this->getEntityNameForRef($relatedEntity)}$identifiersIdsString')";
                        $comment = "";

                    } else {
                        //ignore data for this property.
                        continue;
                    }
                } elseif (is_object($value) && get_class($value) == "Doctrine\\ORM\\PersistentCollection") {
                    /** @var PersistentCollection $value */
                    $meta = $this->metadata->getAssociationMapping($property->getName());

                    if ($meta['isOwningSide'] === true && $value->isEmpty() === false) {
                        $setValue = "[\n";
                        foreach ($value as $object) {
                            $relatedClass = get_class($object);
                            $relatedEntity = ClassUtils::getRealClass($relatedClass);
                            $identifiersIdsString = $this->getRelatedIdsForReference($relatedEntity, $object);
                            $setValue .= $this->spaces.$this->spaces.$this->spaces."\$this->getReference('{$this->referencePrefix}{$this->getEntityNameForRef($relatedEntity)}$identifiersIdsString'),\n";
                            $comment = "";
                        }
                        $setValue .= $this->spaces.$this->spaces."]";
                    }else{
                        //nothing to add.
                        continue;
                    }

                } elseif (is_array($value)) {
                    $setValue = "unserialize('" . str_replace(['\''], ['\\\''], serialize($value)) . "')";
                } elseif (is_null($value)) {
                    $setValue = "NULL";
                } else {
                    $setValue = '"' . str_replace(['"', '$'], ['\"', '\$'], $value) . '"';
                }

                $code .= "\n<spaces><spaces>{$comment}\$item{$ids}->{$setter}({$setValue});";
            }
        }

        $code .= "\n";

        return $code;
    }

    protected function getEntityNameForRef($entityFQN){
        return str_replace("\\", "", $entityFQN);
    }

    protected function getRecursiveProperties(\ReflectionClass $reflection){
        $properties = $reflection->getProperties();
        $parentReflection = $reflection->getParentClass();
        if ($parentReflection !== false){
            $parentProperties = $this->getRecursiveProperties($parentReflection);
            //only get private property.
            $parentProperties = array_filter($parentProperties, function(\ReflectionProperty $property){
                if ($property->isPrivate()){
                    return true;
                }else{
                    return false;
                }
            });
            $properties = array_merge($properties, $parentProperties);
        }

        return $properties;
    }

    /**
     * @return string
     */
    public function getFixtureOrder()
    {
        return $this->fixtureorder;
    }

    /**
     * @param string $fixtureOrder
     *
     * @return FixtureGenerator
     */
    public function setFixtureOrder($fixtureOrder)
    {
        $this->fixtureorder = $fixtureOrder;

        return $this;
    }

    /**
     * @param EntityManagerInterface $entityManager
     *
     * @return FixtureGenerator
     */
    public function setEntityManager(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;

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
     * @return string
     */
    protected function generateFixtureNamespace()
    {
        $namespace = 'namespace '.$this->getNamespace();
        if (strpos($namespace, ';') === false) {
            $namespace.';';
        }

        return $namespace;
    }

    /**
     * @return string
     */
    protected function getNamespace()
    {
        return $this->getBundleNameSpace().'\DataFixtures\ORM;';
    }

    /**
     * @return string
     */
    protected function generateFixtureClassName()
    {
        return 'class '.$this->getClassName().' extends '.$this->getClassToExtend();
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
    protected function generateFixtureBody()
    {
        $code = self::$getLoadMethodTemplate;
        $classpath = $this->getMetadata()->getName();
        $pos = strrpos($classpath, "\\");

        $code = str_replace("<entityName>", substr($classpath, $pos + 1), $code);
        $code = str_replace("<fixtures>", $this->generateFixtures(), $code);

        return $code;
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
     * @param $item
     *
     * @return string
     */
    protected function generateFixture($item)
    {

        $placeHolders = [
            '<itemCount>',
            '<entityName>',
            '<itemStubs>',
            '<entityParams>',
        ];

        $reflexionClass = new \ReflectionClass($item);

        $constructorParams = $this->getConstructorParams($item, $reflexionClass);
        $constructorParamString = '';
        if (!empty($constructorParams)) {
            $constructorParamString = "'".implode("', '", $constructorParams)."'";
        }

        $replacements = [
            $this->getRelatedIdsForReference(get_class($item), $item),
            $reflexionClass->getShortName(),
            $this->generateFixtureItemStub($item),
            $constructorParamString
        ];

        $code = str_replace($placeHolders, $replacements, self::$getItemFixtureTemplate);

        return $code;
    }

    /**
     * @param string $fqcn
     *
     * @return string
     */
    protected function getRelatedIdsForReference(string $fqcn, $value)
    {
        $relatedClassMeta = $this->entityManager->getClassMetadata($fqcn);
        $identifiers = $relatedClassMeta->getIdentifier();
        $ret = "";
        if (!empty($identifiers)) {
            foreach ($identifiers as $identifier) {
                $method = "get".ucfirst($identifier);
                if (method_exists($value, $method)){
                    //change all - for _ in case identifier use UUID as '-' is not a permitted symbol
                    // $ret .= $this->sanitizeSuspiciousSymbols($value->$method()); // 20180531_pfv_2
                }else{
                    // $ret .= $this->sanitizeSuspiciousSymbols($value->$identifier); // 20180531_pfv_2
                }
            }
        }

        return $ret;
    }

    protected function hasIgnoreProperty($propertyReflection)
    {
        $reader = new AnnotationReader();

        /** @var Property $propertyAnnotation */
        $propertyAnnotation = $reader->getPropertyAnnotation(
            $propertyReflection,
            'Webonaute\DoctrineFixturesGeneratorBundle\Annotation\Property'
        );

        if ($propertyAnnotation !== null && $propertyAnnotation->ignoreInSnapshot === true) {
            //ignore this mapping. (data will not be exported for that field.)
            return true;
        } else {
            return false;
        }
    }

    protected function generateUse()
    {
        return "use ".$this->getMetadata()->name.";";
    }

    /**
     * @return int
     */
    protected function generateOrder()
    {
        return $this->fixtureorder;
    }

    protected function generateFixtureLoadMethod(ClassMetadataInfo $metadata)
    {

    }

    /**
     * sanitize illegal symbols in variable name suffix
     * @param string $string
     * @return string
     */
    private function sanitizeSuspiciousSymbols($string)
    {
        if(!is_string($string)) return $string; // pfv
        $sanitizedString = preg_replace('/[^a-zA-Z0-9_]/', '_', $string);

        return $sanitizedString;
    }

    /**
     * @param             $item
     * @param \ReflectionClass $reflexion
     *
     * @return mixed
     */
    private function getNewInstance($item, \ReflectionClass $reflexion)
    {
        if (null === $reflexion->getConstructor()) {
            return $reflexion->newInstance();
        }
        $constructorParams = $this->getConstructorParams($item, $reflexion);
        if (empty($constructorParams)) {
            return $reflexion->newInstance();
        }

        return call_user_func_array(array($reflexion, 'newInstance'), $constructorParams);
    }

    /**
     * @param             $item
     * @param \ReflectionClass $reflexion
     *
     * @return array
     */
    private function getConstructorParams($item, \ReflectionClass $reflexion): array
    {
        $constructorParams = [];
        if($reflexion->getConstructor()===null) return array(); // 20180531_pfv_3
        foreach ($reflexion->getConstructor()->getParameters() as $parameter) {
            if ($parameter->isDefaultValueAvailable()) {
                continue;
            }
            $paramName = $parameter->getName();
            if ($reflexion->hasMethod('get'.ucfirst($paramName))) {
                $constructorParams[] = $item->{'get'.ucfirst($paramName)}();
            } elseif ($reflexion->hasProperty($paramName)) {
                $reflectionProperty = $reflexion->getProperty($paramName);
                $reflectionProperty->setAccessible(true);
                $constructorParams[] = $reflectionProperty->getValue($item);
            }
        }

        return $constructorParams;
    }
}
