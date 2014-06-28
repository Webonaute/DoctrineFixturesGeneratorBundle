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

use Sensio\Bundle\GeneratorBundle\Command\GenerateDoctrineCommand;
use Sensio\Bundle\GeneratorBundle\Command\Helper\DialogHelper;
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Webonaute\DoctrineFixturesGeneratorBundle\Generator\DoctrineFixtureGenerator;

/**
 * Initializes a Doctrine entity fixture inside a bundle.
 *
 * @author Mathieu Delisle <mdelisle@webonaute.ca>
 */
class GenerateDoctrineFixtureCommand extends GenerateDoctrineCommand
{

    protected function configure()
    {
        $this
            ->setName('doctrine:generate:fixture')
            ->setAliases(array('generate:doctrine:fixture'))
            ->setDescription('Generates a new Doctrine entity fixture inside a bundle from existing data.')
            ->addOption(
                'entity',
                null,
                InputOption::VALUE_REQUIRED,
                'The entity class name to initialize (shortcut notation)'
            )
            ->addOption('ids', null, InputOption::VALUE_OPTIONAL, 'Only create fixture for this specific ID.')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Give a specific name to the fixture')
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

<info>php app/console doctrine:generate:fixture --entity=AcmeDemoBundle:Address --ids="12" --name="ticket2224"</info>

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
        $dialog = $this->getDialogHelper();

        if ($input->isInteractive()) {
            if (!$dialog->askConfirmation(
                $output,
                $dialog->getQuestion('Do you confirm generation', 'yes', '?'),
                true
            )
            ) {
                $output->writeln('<error>Command aborted</error>');

                return 1;
            }
        }

        $entity = Validators::validateEntityName($input->getOption('entity'));
        list($bundle, $entity) = $this->parseShortcutNotation($entity);
        $name = $input->getOption('name');
        $ids = $this->parseIds($input->getOption('ids'));

        $dialog->writeSection($output, 'Entity generation');
        /** @var Kernel $kernel */
        $kernel = $this->getContainer()->get('kernel');
        $bundle = $kernel->getBundle($bundle);

        $generator = $this->getGenerator();
        $generator->generate($bundle, $entity, $name, array_values($ids));

        $output->writeln('Generating the fixture code: <info>OK</info>');

        $dialog->writeGeneratorSummary($output, array());

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
     * Interactive mode.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $dialog = $this->getDialogHelper();
        $dialog->writeSection($output, 'Welcome to the Doctrine2 fixture generator');

        // namespace
        $output->writeln(
            array(
                '',
                'This command helps you generate Doctrine2 fixture.',
                '',
                'First, you need to give the entity name you want to generate fixture from.',
                'You must use the shortcut notation like <comment>AcmeBlogBundle:Post</comment>.',
                ''
            )
        );

        /** @var Kernel $kernel */
        $kernel = $this->getContainer()->get('kernel');
        $bundleNames = array_keys($kernel->getBundles());

        while (true) {
            $entity = $dialog->askAndValidate(
                $output,
                $dialog->getQuestion('The Entity shortcut name', $input->getOption('entity')),
                array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateEntityName'),
                false,
                $input->getOption('entity'),
                $bundleNames
            );

            list($bundle, $entity) = $this->parseShortcutNotation($entity);


            try {
                /** @var Kernel $kernel */
                $kernel = $this->getContainer()->get('kernel');
                //check if bundle exist.
                $b = $kernel->getBundle($bundle);
                try {
                    //check if entity exist in the selected bundle.
                    $entityObject = $this->getContainer()->get("doctrine")->getManager()->getRepository($bundle . ":" . $entity);
                    break;
                } catch (\Exception $e) {
                    $output->writeln(sprintf('<bg=red>Entity "%s" does not exist.</>', $entity));
                }

            } catch (\Exception $e) {
                $output->writeln(sprintf('<bg=red>Bundle "%s" does not exist.</>', $bundle));
            }
        }
        $input->setOption('entity', $bundle . ':' . $entity);

        // ids
        $input->setOption('ids', $this->addIds($input, $output, $dialog));

        // name
        $input->setOption('name', $this->getFixtureName($input, $output, $dialog));

        $count = count($input->getOption('ids'));

        // summary
        $output->writeln(
            array(
                '',
                $this->getHelper('formatter')->formatBlock('Summary before generation', 'bg=blue;fg=white', true),
                '',
                sprintf("You are going to generate  \"<info>%s:%s</info>\" fixtures", $bundle, $entity),
                sprintf("using the \"<info>%s</info>\" ids.", $count),
                '',
            )
        );
    }

    /**
     * Interactive mode to add IDs list.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param DialogHelper    $dialog
     *
     * @return array
     */
    private function addIds(InputInterface $input, OutputInterface $output, DialogHelper $dialog)
    {
        $ids = $this->parseIds($input->getOption('ids'));

        while (true) {
            $output->writeln('');

            $id = $dialog->askAndValidate(
                $output,
                $dialog->getQuestion('New ID (press <return> to stop adding ids)', null),
                function ($id) use ($ids) {
                    if (in_array($id, $ids)) {
                        throw new \InvalidArgumentException(sprintf('Id "%s" is already defined.', $id));
                    }

                    //@todo validate id exist.

                    return $id;
                }
            );
            if (!$id) {
                break;
            }

            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * Interactive mode to add IDs list.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param DialogHelper    $dialog
     *
     * @return array
     */
    private function getFixtureName(InputInterface $input, OutputInterface $output, DialogHelper $dialog)
    {
        $name = $input->getOption('name');


        //should ask for the name.
        $output->writeln('');

        $name = $dialog->askAndValidate(
            $output,
            $dialog->getQuestion('Fixture name', null),
            function ($name) use ($input) {
                if ($name == "" && count($input->getOption('ids')) > 1) {
                    throw new \InvalidArgumentException('Name is require when using multiple IDs.');
                }

                return $name;
            }
        );

        if ($name == ""){
            //use default name.
            $name = null;
        }

        return $name;
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
        $ids = array();

        if (is_array($input)) {
            return $input;
        }

        if (strlen($input) > 0) {
            foreach (explode(' ', $input) as $value) {
                $value = trim($value);
                if (strlen($value) > 0) {
                    $ids[] = trim($value);
                }
            }
        }

        return $ids;
    }
}
