<?php

/*
 * This file is part of the Webonaute package.
 *
 * (c) Mathieu Delisle <mdelisle@webonaute.ca>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webonaute\DoctrineFixturesGeneratorBundle\Generator;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Sensio\Bundle\GeneratorBundle\Generator\Generator;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Webonaute\DoctrineFixturesGeneratorBundle\Tool\FixtureGenerator;

/**
 * Generates a Doctrine Fixture class based on entity name, ids and custom name.
 *
 * @author Mathieu Delisle <mdelisle@webonaute.ca>
 */
class DoctrineFixtureGenerator extends Generator
{

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var RegistryInterface
     */
    private $registry;

    /**
     * Constructor
     *
     * @param Filesystem $filesystem
     * @param RegistryInterface $registry
     */
    public function __construct(Filesystem $filesystem, RegistryInterface $registry)
    {
        $this->filesystem = $filesystem;
        $this->registry = $registry;
    }

    /**
     * Generate Fixture from bundle name, entity name, fixture name and ids
     *
     * @param BundleInterface $bundle
     * @param string $entity
     * @param string $name
     * @param array $ids
     * @param string|null $connectionName
     */
    public function generate(BundleInterface $bundle, $entity, $name, array $ids, $order, $connectionName = null, $overwrite = false, $isFqcnEntity = false)
    {
        // configure the bundle (needed if the bundle does not contain any Entities yet)
        $config = $this->registry->getManager($connectionName)->getConfiguration();
        $config->setEntityNamespaces(
            array_merge(
                array($bundle->getName() => $bundle->getNamespace() . '\\Entity'),
                $config->getEntityNamespaces()
            )
        );

        $fixtureFileName = $this->getFixtureFileName($entity, $name, $ids);
        $entityClass = $this->getFqcnEntityClass($entity, $bundle, $isFqcnEntity);

        $fixturePath = $bundle->getPath() . '/DataFixtures/ORM/' . $fixtureFileName . '.php';
        $bundleNameSpace = $bundle->getNamespace();
        if ($overwrite === false && file_exists($fixturePath)) {
            throw new \RuntimeException(sprintf('Fixture "%s" already exists.', $fixtureFileName));
        }

        /** @var EntityManager $entityManager */
        $entityManager = $this->registry->getEntityManager($connectionName);
        $class = $entityManager->getClassMetadata($entityClass);

        $fixtureGenerator = $this->getFixtureGenerator();
        $fixtureGenerator->setFixtureName($fixtureFileName);
        $fixtureGenerator->setBundleNameSpace($bundleNameSpace);
        $fixtureGenerator->setMetadata($class);
        $fixtureGenerator->setFixtureOrder($order);
        $fixtureGenerator->setEntityManager($entityManager);

        /** @var EntityManager $em */
        $em = $this->registry->getManager($connectionName);

        /** @var EntityRepository $repo */
        $repo = $em->getRepository($class->rootEntityName);
        if (empty($ids)) {
            $items = $repo->findAll();
        } else {
            //@todo here we assume that you use `id` as primary key. Need to change it to reflect the primary property used by the entity.
            $items = $repo->findById($ids);
        }

        $fixtureGenerator->setItems($items);

        $fixtureCode = $fixtureGenerator->generateFixtureClass($class);

        $this->filesystem->mkdir(dirname($fixturePath));
        file_put_contents($fixturePath, $fixtureCode);

    }

    /**
     * Return fixture file name
     *
     * @param       $entity
     * @param       $name
     * @param array $ids
     *
     * @return string
     */
    public function getFixtureFileName($entity, $name, array $ids)
    {

        $fixtureFileName = "Load";
        //if name params is set.
        if (strlen($name) > 0) {
            $fixtureFileName .= ucfirst($name);
        } else {
            //esle use entity name

            //ids with more than one entry should have --name set.
            if (count($ids) > 1) {
                throw new \RuntimeException('Fixture with multiple IDs should have the --name set.');
            }

            $fixtureFileName = $this->getFixtureNameFromEntityName($entity, $ids);
        }

        return $fixtureFileName;
    }

    /**
     * Transform Entity name into a compatible filename.
     *
     * @param string $entity
     * @param array $ids
     * @param string $prefix
     *
     * @throws \nvalidArgumentException if the $entity or $prefix argument is not of type string
     * @return string
     */
    public function getFixtureNameFromEntityName($entity, array $ids = [], $prefix = null)
    {
        // Throw error if $entity is not a string
        if (!is_string($entity)) {
            throw new \InvalidArgumentException(
                'The parameter $entity is expected to be a string, "'.gettype($entity).'" given.'
            );
        }

        // Throw error if $prefix is not a string
        if ($prefix && !is_string($prefix)) {
            throw new \InvalidArgumentException(
                'The parameter $prefix is expected to be a string, "'.gettype($prefix).'" given.'
            );
        }

        //noBackSlash
        $name = str_replace('\\', '', $entity);

        //add prefix.
        if (strlen($prefix) > 0) {
            $name = $prefix . ucfirst($name);
        }

        //add first ID in the name.
        if (isset($ids[0])) {
            $name .= $ids[0];
        }

        return $name;
    }

    protected function getFqcnEntityClass($entity, BundleInterface $bundle, $isFqcnEntity = false)
    {
        if ($isFqcnEntity) {
            return $entity;
        } else {
            return $this->registry->getAliasNamespace($bundle->getName()) . '\\' . $entity;
        }
    }

    /**
     * Return the fixture generator object
     *
     * @return FixtureGenerator
     */
    protected function getFixtureGenerator()
    {
        $fixtureGenerator = new FixtureGenerator();
        $fixtureGenerator->setNumSpaces(4);

        return $fixtureGenerator;
    }

}
