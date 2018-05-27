<?php

/*
 * This file is part of the Webonaute package.
 *
 * (c) Mathieu Delisle <mdelisle@webonaute.ca>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Webonaute\DoctrineFixturesGeneratorBundle\Command;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Webonaute\DoctrineFixturesGeneratorBundle\Command\GenerateDoctrineCommand;
use Webonaute\DoctrineFixturesGeneratorBundle\Command\Validators;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Webonaute\DoctrineFixturesGeneratorBundle\Annotation\FixtureSnapshot;
use Webonaute\DoctrineFixturesGeneratorBundle\Annotation\Property;
use Webonaute\DoctrineFixturesGeneratorBundle\Generator\DoctrineFixtureGenerator;
use Webonaute\DoctrineFixturesGeneratorBundle\Generator\Entity;

/**
 * Initializes a Doctrine entity fixture inside a bundle.
 *
 * @author Mathieu Delisle <mdelisle@webonaute.ca>
 */
class GenerateDoctrineFixtureCommand extends GenerateDoctrineCommand
{

    /**
     * @var bool
     */
    protected $confirmGeneration = true;

    /**
     * @var bool
     */
    protected $snapshot = false;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var EntityManager
     */
    protected $entityManager;


    protected function configure()
    {
        $this
            ->setName('doctrine:generate:fixture')
            ->setAliases(['generate:doctrine:fixture'])
            ->setDescription('Generates a new Doctrine entity fixture inside a bundle from existing data.')
            ->addOption(
                'entity',
                null,
                InputOption::VALUE_REQUIRED,
                'The entity class name to initialize (shortcut notation)'
            )
            ->addOption('snapshot', null, InputOption::VALUE_NONE, 'Create a full snapshot of DB.')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Overwrite entity fixture file if already exist.')
            ->addOption('ids', null, InputOption::VALUE_OPTIONAL, 'Only create fixture for this specific ID.')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL,
                'Give a specific name to the fixture or a prefix with snapshot option.')
            ->addOption('order', null, InputOption::VALUE_OPTIONAL, 'Give a specific order to the fixture.')
            ->addOption(
                'connectionName',
                null,
                InputOption::VALUE_OPTIONAL,
                'Give a specific connection name if you use multiple connectors '
            )
            ->setHelp(
                <<<EOT
                The <info>doctrine:generate:fixture</info> task generates a new Doctrine
entity fixture inside a bundle with existing data:

<info>php app/console doctrine:generate:fixture --entity=AcmeDemoBundle:Address</info>

The above command would initialize a new entity fixture in the following entity
namespace <info>Acme\BlogBundle\DataFixtures\ORM\LoadAddress</info>.

You can also optionally specify the id you want to generate in the new
entity fixture. (Helpful when you want to create a new Test case based on real data.):

<info>php app/console doctrine:generate:fixture --entity=AcmeDemoBundle:Address --ids="12"</info>

The above command would initialize a new entity fixture in the following entity
namespace <info>Acme\BlogBundle\DataFixtures\ORM\LoadAddress12</info>.

You can also optionally specify the fixture name of the new entity fixture.
(You can give for example the ticket number of what the fixture is for.):

<info>php app/console doctrine:generate:fixture --entity=AcmeDemoBundle:Address --ids="12 15-21" --name="ticket2224"</info>

The above command would initialize a new entity fixture in the following entity
namespace <info>Acme\BlogBundle\DataFixture\ORM\LoadTicket2224</info>.

If fixture name exist, it will NOT overwrite it.

To deactivate the interaction mode, simply use the `--no-interaction` option
without forgetting to pass all needed options:

<info>php app/console doctrine:generate:fixture --entity=AcmeDemoBundle:Address --ids="12" --no-interaction</info>
EOT
            );
    }

    /**
     * @return DoctrineFixtureGenerator
     */
    protected function createGenerator()
    {
        return new DoctrineFixtureGenerator(
            $this->getContainer()->get('filesystem'),
            $this->getContainer()->get('doctrine')
        );
    }

    /**
     * @throws \InvalidArgumentException When the bundle doesn't end with Bundle (Example: "Bundle/MySampleBundle")
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        $this->snapshot = $input->getOption("snapshot");

        if ($this->confirmGeneration === false && $this->snapshot === false) {
            $output->writeln('<error>Command aborted</error>');

            return 1;
        }

        $connectionName = $input->getOption('connectionName');
        /** @var EntityManager $em */
        $this->entityManager = $this->getContainer()->get('doctrine')->getManager($connectionName);

        $name = $input->getOption('name');
        $generator = $this->getGenerator();
        $overwrite = $input->getOption("overwrite");

        if ($this->snapshot === true) {
            $entitiesMetadata = $this->getEntitiesMetadata($connectionName);
            $entities = $this->getOrderedEntities($entitiesMetadata, $output);

            if (!empty($entities)) {
                $this->writeSection($output, 'Entities generation');
                foreach ($entities as $entity) {
                    $result = $generator->generate(
                        $entity->bundle,
                        $entity->name,
                        $generator->getFixtureNameFromEntityName($entity->name, [], $name),
                        [], //not applicable in snapshot mode.
                        $entity->level,
                        $connectionName,
                        $overwrite,
                        true,
                        true
                    );
                    if ($result){
                        $tag = "info";
                    }else{
                        $tag = "comment";
                    }
                    $this->output->writeln("<$tag>Generated fixture (lvl {$entity->level}) for {$entity->name}</$tag>");
                }
            }
        } else {
            $entity = Validators::validateEntityName($input->getOption('entity'));
            list($bundle, $entity) = $this->parseShortcutNotation($entity);
            $name = $input->getOption('name');
            $ids = $this->parseIds($input->getOption('ids'));
            $order = $input->getOption('order');

            $this->writeSection($output, 'Entity generation');
            /** @var Kernel $kernel */
            $kernel = $this->getContainer()->get('kernel');
//            $bundle = $kernel->getBundle($bundle);

            $generator->generate('src', $entity, $name, array_values($ids), $order, $connectionName, $overwrite);

            $output->writeln('Generating the fixture code: <info>OK</info>');
        }

        $output->writeln('<info>DONE!</info>');

        //all fine.
        return 0;
    }

    /**
     * @param BundleInterface $bundle
     *
     * @return DoctrineFixtureGenerator
     */
    protected function getGenerator(BundleInterface $bundle = null)
    {
        return parent::getGenerator($bundle);
    }

    /**
     * @param string $connectionName
     *
     * @return ClassMetadata[]
     */
    protected function getEntitiesMetadata($connectionName = "default")
    {
        $classes = [];

        $em = $this->getContainer()->get('doctrine')->getManager($connectionName);
        $mf = $em->getMetadataFactory();

        $metas = $mf->getAllMetadata();

        /** @var ClassMetadata $meta */
        foreach ($metas as $meta) {

            if ($meta->isMappedSuperclass
                //|| ($meta->isInheritanceTypeSingleTable() && $meta->name != $meta->rootEntityName)
            ) {
                $this->output->writeln("<comment>Skip mappedSuperClass entity ".$meta->getName()."</comment>");
                continue;
            }

            if ($this->skipEntity($meta)){
                $this->output->writeln("<comment>Skip entity ".$meta->getName()."</comment>");
                continue;
            }

            //ignore vendor entities.
            // @todo data for entities in vendor directory should be created in a specified bundle container
            // in src folder. Maybe add an options in the command who user can specify which bundle should store those.
            $class = $meta->getReflectionClass();
            if (strpos($class->getFileName(), "/vendor/")) {
                $this->output->writeln("<comment>Skip vendor entity ".$meta->getName()."</comment>");
                continue;
            }

            if ($meta->getReflectionClass()->isAbstract()) {
                $this->output->writeln("<comment>Skip abstract entity ".$meta->getName()."</comment>");
                continue;
            }

            $classes[] = $meta;
        }

        return $classes;
    }

    /**
     * @param ClassMetadata $meta
     *
     * @return bool
     */
    protected function skipEntity($meta)
    {
        if ($meta->isMappedSuperclass
            //|| ($meta->isInheritanceTypeSingleTable() && $meta->name != $meta->rootEntityName)
        ) {
            return true;
        }

        //ignore vendor entities.
        // @todo data for entities in vendor directory should be created in a specified bundle container
        // in src folder. Maybe add an options in the command who user can specify which bundle should store those.
        $class = $meta->getReflectionClass();
        if (strpos($class->getFileName(), "/vendor/")) {
            return true;
        }

        return false;
    }

    /**
     *
     * @param array $metadatas
     *
     * @return Entity[]
     */
    protected function getOrderedEntities(array $metadatas, OutputInterface $output)
    {
        $level = 1;
        $countMeta = count($metadatas);
        $entities = [];

        $namespaces = $this->entityManager->getConfiguration()->getEntityNamespaces();

        do {
            //reset current level entities list
            $entitiesCurrentOrder = [];
            //for each meta,
            /**
             * @var int $mkey
             * @var ClassMetadata $meta
             */
            foreach ($metadatas as $mkey => $meta) {
                //check against last orders entities.
                if ($this->isEntityLevelReached($meta, $entities)) {
                    if ($this->isIgnoredEntity($meta) === false) {
                        $entity = new Entity();
                        $entity->level = $level;
                        $entity->name = $meta->getName();
                        $entity->bundle = $this->findBundleInterface($namespaces, $meta->namespace);;
                        $entity->meta = $meta;
                        //add to temporary group of entities.
                        $entitiesCurrentOrder[] = $entity;
                    }
                    //remove from meta to process.
                    unset($metadatas[$mkey]);
                }
            }

            if (!empty($entitiesCurrentOrder)) {
                $entities = array_merge($entities, $entitiesCurrentOrder);
            }

            //repeat until all metadata are processed.
            //it can't have more level than number of entities so break if $level is superior to $countMeta.
            $level++;
        } while (!empty($metadatas) && $level <= $countMeta);

        //show entity who could not be ordered and get ignored.
        if (!empty($metadatas)){
            foreach ($metadatas as $meta) {
                $output->writeln("<comment>Could not get ordered {$meta->getName()}</comment>");
            }
        }

        return $entities;
    }

    /**
     * @param ClassMetadata $meta
     * @param array $entities
     *
     * @return bool
     */
    protected function isEntityLevelReached(ClassMetadata $meta, array $entities)
    {
        $mappings = $meta->getAssociationMappings();
        $reader = new AnnotationReader();

        //if there is association, check if entity is already included to satisfy the requirement.
        if (count($mappings) > 0) {
            foreach ($mappings as $mapping) {
                $propertyReflection = $meta->getReflectionProperty($mapping['fieldName']);
                /** @var Property $propertyAnnotation */
                $propertyAnnotation = $reader->getPropertyAnnotation(
                    $propertyReflection,
                    'Webonaute\DoctrineFixturesGeneratorBundle\Annotation\Property'
                );
                $annotations = $reader->getPropertyAnnotations($propertyReflection);

                if ($propertyAnnotation !== null && $propertyAnnotation->ignoreInSnapshot === true) {
                    //ignore this mapping. (data will not be exported for that field.)
                    continue;
                }

                //prevent self mapping loop.
                if ($mapping['targetEntity'] === $mapping['sourceEntity']) {
                    continue;
                }

                if ($mapping['isOwningSide'] === true && $this->mappingSatisfied($mapping, $entities) === false) {
                    // if mapping is made on abstract class with discriminator. ensure those are include before.
                    if ($this->discriminatorSatisfied($mapping['targetEntity'], $entities)){
                        continue;
                    }

                    return false;
                }
            }
        }

        return true;

    }

    protected function discriminatorSatisfied($entity, $entities){
        $entityMapping = $this->entityManager->getClassMetadata($entity);
        $reflectionClass = $entityMapping->getReflectionClass();

        if ($reflectionClass->isAbstract()){
           if (!empty($entityMapping->discriminatorMap)){
               foreach ($entityMapping->discriminatorMap as $discriminator){
                   $found = false;
                   if (!empty($entities)){
                       foreach ($entities as $checkEntity) {
                           if ($checkEntity->name === $discriminator) {
                               $found = true;
                               break;
                           }
                       }
                   }

                   if (!$found){
                       return false;
                   }
               }
           }else{
               return false;
           }
        }else{
            return false;
        }

        return true;
    }

    protected function mappingSatisfied($mapping, $entities)
    {
        foreach ($entities as $entity) {
            if ($entity->name === $mapping['targetEntity']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the entity should generate fixtures.
     *
     * @param ClassMetadata $meta
     *
     * @return bool
     */
    protected function isIgnoredEntity(ClassMetadata $meta)
    {
        $result = false;

        $reader = new AnnotationReader();
        $reflectionClass = $meta->getReflectionClass();
        /** @var FixtureSnapshot $fixtureSnapshotAnnotation */
        $fixtureSnapshotAnnotation = $reader->getClassAnnotation(
            $reflectionClass,
            'Webonaute\DoctrineFixturesGeneratorBundle\Annotation\FixtureSnapshot'
        );

        if ($fixtureSnapshotAnnotation !== null) {
            $result = $fixtureSnapshotAnnotation->ignore;
        }

        return $result;

    }

    /**
     * Return bundle name of entity namespace.
     *
     * @param $namespaces
     * @param $metaNamespace
     *
     * @return mixed
     */
    protected function findBundleInterface($namespaces, $metaNamespace)
    {
        $namespaceParts = explode("\\", $metaNamespace);
        $bundle = null;

        if (count($namespaceParts) > 0) {
            /** @var Kernel $kernel */
            $kernel = $this->getContainer()->get('kernel');

            do {
                try {
                    $find = array_search(implode("\\", $namespaceParts), $namespaces);
                    if ($find !== false) {
                        $bundle = $kernel->getBundle($find);
                    } else {
                        array_pop($namespaceParts);
                    }
                } catch (\InvalidArgumentException $e) {
                    array_pop($namespaceParts);
                }
            } while ($bundle == null && count($namespaceParts) > 1);

        }

        if ($bundle === null) {
            throw new \LogicException("No bundle found for entity namespace ".$metaNamespace);
        }

        return $bundle;
    }

    /**
     * Parse Ids string list into an array.
     *
     * @param $input
     *
     * @return array
     */
    private function parseIds($input)
    {
        $ids = [];

        if (is_array($input)) {
            return $input;
        }

        //check if the input is not empty.
        if (strlen($input) > 0) {
            $values = explode(' ', $input);
            //extract ids for each value found.
            foreach ($values as $value) {
                //filter any extra space.
                $value = trim($value);
                //filter empty values.
                if (strlen($value) > 0) {
                    //check if the value is a range ids.
                    if ($this->isRangeIds($value)) {
                        $ids = array_merge($ids, $this->extractRangeIds($value));
                    } else {
                        //make sure id is an integer.
                        $value = intval($value);
                        //make sure id are bigger than 0.
                        if ($value > 0) {
                            $ids[] = $value;
                        }
                    }
                }
            }
        }

        //make sure ids are unique.
        $ids = array_unique($ids);

        return $ids;
    }

    /**
     * Check if a string contain a range ids string.
     *
     * @param $string
     *
     * @return bool
     */
    private function isRangeIds($string)
    {
        return (false !== strpos($string, '-'));
    }

    /**
     * extract ids from ranges.
     *
     * @param $string
     *
     * @return array
     */
    private function extractRangeIds($string)
    {
        $rangesIds = explode('-', $string);
        $result = [];
        //validate array should have 2 values and those 2 values are integer.
        if (count($rangesIds) == 2) {
            $begin = intval($rangesIds[0]);
            $end = intval($rangesIds[1]);
            if ($begin > 0 && $end > 0) {
                $result = range($begin, $end);
            }
        }

        return $result;
    }

    public function writeSection(OutputInterface $output, $text, $style = 'bg=blue;fg=white')
    {
        $output->writeln(
            [
                '',
                $this->getHelperSet()->get('formatter')->formatBlock($text, $style, true),
                '',
            ]
        );
    }

    /**
     * Interactive mode.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $this->writeSection($output, 'Welcome to the Doctrine2 fixture generator');

        // namespace
        $output->writeln(
            [
                '',
                'This command helps you generate Doctrine2 fixture.',
                '',
            ]
        );

        $question = new ConfirmationQuestion('Do you want to create a full snapshot ? (y/N)', false);
        $snapshot = $helper->ask($input, $output, $question);

        if ($snapshot === false) {
            $bundle = '';
            $entity = '';

            /** @var Kernel $kernel */
            $kernel = $this->getContainer()->get('kernel');
            $bundleNames = array_keys($kernel->getBundles());
            while (true) {

                $output->writeln(
                    [
                        'First, you need to give the entity name you want to generate fixture from.',
                        'You must use the shortcut notation like <comment>AcmeBlogBundle:Post</comment>.',
                        '',
                    ]
                );

                $question = new Question(
                    'The Entity shortcut name'.($input->getOption('entity') != "" ?
                        " (".$input->getOption('entity').")" : "").' : ', $input->getOption('entity')
                );
                $question->setValidator(['Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateEntityName']);
                $question->setMaxAttempts(5);
                $question->setAutocompleterValues($bundleNames);
                $entity = $helper->ask($input, $output, $question);

                list($bundle, $entity) = $this->parseShortcutNotation($entity);

                try {
                    /** @var Kernel $kernel */
                    $kernel = $this->getContainer()->get('kernel');
                    //check if bundle exist.
                    $kernel->getBundle($bundle);
                    try {
                        $connectionName = $input->getOption('connectionName');
                        //check if entity exist in the selected bundle.
                        $this->getContainer()
                            ->get("doctrine")->getManager($connectionName)
                            ->getRepository($bundle.":".$entity);
                        break;
                    } catch (\Exception $e) {
                        print $e->getMessage()."\n\n";
                        $output->writeln(sprintf('<bg=red>Entity "%s" does not exist.</>', $entity));
                    }

                } catch (\Exception $e) {
                    $output->writeln(sprintf('<bg=red>Bundle "%s" does not exist.</>', $bundle));
                }
            }
            $input->setOption('entity', $bundle.':'.$entity);

            // ids
            $input->setOption('ids', $this->addIds($input, $output, $helper));

            // name
            $input->setOption('name', $this->getFixtureName($input, $output, $helper));

            // Order
            $input->setOption('order', $this->getFixtureOrder($input, $output, $helper));

            $count = count($input->getOption('ids'));

            // summary
            $output->writeln(
                [
                    '',
                    $this->getHelper('formatter')->formatBlock('Summary before generation', 'bg=blue;fg=white', true),
                    '',
                    sprintf("You are going to generate  \"<info>%s:%s</info>\" fixtures", $bundle, $entity),
                    sprintf("using the \"<info>%s</info>\" ids.", $count),
                    '',
                ]
            );

            $this->confirmGeneration = false;
            $question = new ConfirmationQuestion('Do you confirm generation ? (y/N)', false);
            $this->confirmGeneration = $helper->ask($input, $output, $question);
        } else {
            $this->snapshot = true;
        }

    }

    /**
     * Interactive mode to add IDs list.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param QuestionHelper $helper
     *
     * @return array
     */
    private function addIds(InputInterface $input, OutputInterface $output, QuestionHelper $helper)
    {
        $ids = $this->parseIds($input->getOption('ids'));

        while (true) {
            $output->writeln('');
            $question = new Question(
                'New ID (press <return> to stop adding ids)'.(!empty($ids) ? " (".implode(", ", $ids).")" : "")
                .' : ', null
            );
            $question->setValidator(
                function ($id) use ($ids) {
                    $inputIds = $this->parseIds($id);

                    // If given id or range of ids are already present in defined range
                    if ($duplicateIds = array_intersect($inputIds, $ids)) {
                        // If input for example "5-9"
                        if ($this->isRangeIds($id)) {
                            // whether there is only one or more duplicate numbers from given range
                            $idsWord = count($duplicateIds) > 1 ? 'Ids' : 'Id';
                            $duplicateIdsString = implode(', ', $duplicateIds);
                            $msg = sprintf($idsWord.' "%s" from given range "%s" is already defined.',
                                $duplicateIdsString, $id);
                        } else {
                            $msg = sprintf('Id "%s" is already defined.', $id);
                        }
                        throw new \InvalidArgumentException($msg);
                    }

                    return $inputIds;
                }
            );

            $question->setMaxAttempts(5);
            $inputIds = $helper->ask($input, $output, $question);

            $id = $helper->ask($input, $output, $question);

            if (!$id) {
                break;
            }

            $ids = array_merge($ids, $inputIds);
        }

        return $ids;
    }

    /**
     * Interactive mode to add IDs list.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param QuestionHelper $helper
     *
     * @return array
     */
    private function getFixtureName(InputInterface $input, OutputInterface $output, QuestionHelper $helper)
    {
        $name = $input->getOption('name');

        //should ask for the name.
        $output->writeln('');

        $question = new Question('Fixture name'.($name != "" ? " (".$name.")" : "").' : ', $name);
        $question->setValidator(
            function ($name) use ($input) {
                if ($name == "" && count($input->getOption('ids')) > 1) {
                    throw new \InvalidArgumentException('Name is require when using multiple IDs.');
                }

                return $name;
            }
        );
        $question->setMaxAttempts(5);
        $name = $helper->ask($input, $output, $question);

        if ($name == "") {
            //use default name.
            $name = null;
        }

        return $name;
    }

    /**
     * Interactive mode to add IDs list.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param QuestionHelper $helper
     *
     * @return array
     */
    private function getFixtureOrder(InputInterface $input, OutputInterface $output, QuestionHelper $helper)
    {
        $order = $input->getOption('order');

        //should ask for the name.
        $output->writeln('');

        $question = new Question('Fixture order'.($order != "" ? " (".$order.")" : "").' : ', $order);
        $question->setValidator(
            function ($order) {
                //allow numeric number including 0. but not 01 for example.
                if (!preg_match("/^[1-9][0-9]*|0$/", $order)) {
                    throw new \InvalidArgumentException('Order should be an integer >= 0.');
                }

                //ensure it return number.
                return intval($order);
            }
        );
        $question->setMaxAttempts(5);
        $name = $helper->ask($input, $output, $question);

        if ($name == "") {
            //use default name.
            $name = 1;
        }

        return $name;
    }
}
