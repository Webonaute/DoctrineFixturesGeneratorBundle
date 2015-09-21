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
use Sensio\Bundle\GeneratorBundle\Command\Validators;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
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

    public function writeSection(OutputInterface $output, $text, $style = 'bg=blue;fg=white')
    {
        $output->writeln(
            array(
                '',
                $this->getHelperSet()->get('formatter')->formatBlock($text, $style, true),
                '',
            )
        );
    }

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
            ->addOption('order', null, InputOption::VALUE_OPTIONAL, 'Give a specific order to the fixture')
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

    protected $confirmGeneration = true;

    /**
     * @throws \InvalidArgumentException When the bundle doesn't end with Bundle (Example: "Bundle/MySampleBundle")
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->confirmGeneration === false) {
            $output->writeln('<error>Command aborted</error>');

            return 1;
        }

        $entity = Validators::validateEntityName($input->getOption('entity'));
        list($bundle, $entity) = $this->parseShortcutNotation($entity);
        $name = $input->getOption('name');
        $ids = $this->parseIds($input->getOption('ids'));
        $order = $input->getOption('order');

        $this->writeSection($output, 'Entity generation');
        /** @var Kernel $kernel */
        $kernel = $this->getContainer()->get('kernel');
        $bundle = $kernel->getBundle($bundle);

        $generator = $this->getGenerator();
        $connectionName = $input->getOption('connectionName');
        $generator->generate($bundle, $entity, $name, array_values($ids), $order, $connectionName);

        $output->writeln('Generating the fixture code: <info>OK</info>');


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
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $this->writeSection($output, 'Welcome to the Doctrine2 fixture generator');

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

            $question = new Question(
                'The Entity shortcut name' . ($input->getOption('entity') != "" ?
                    " (" . $input->getOption('entity') . ")" : "") . ' : ', $input->getOption('entity')
            );
            $question->setValidator(array('Sensio\Bundle\GeneratorBundle\Command\Validators', 'validateEntityName'));
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
                        ->getRepository($bundle . ":" . $entity);
                    break;
                } catch (\Exception $e) {
                    print $e->getMessage() . "\n\n";
                    $output->writeln(sprintf('<bg=red>Entity "%s" does not exist.</>', $entity));
                }

            } catch (\Exception $e) {
                $output->writeln(sprintf('<bg=red>Bundle "%s" does not exist.</>', $bundle));
            }
        }
        $input->setOption('entity', $bundle . ':' . $entity);

        // ids
        $input->setOption('ids', $this->addIds($input, $output, $helper));

        // name
        $input->setOption('name', $this->getFixtureName($input, $output, $helper));

        // Order
        $input->setOption('order', $this->getFixtureOrder($input, $output, $helper));

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

        $this->confirmGeneration = false;
        $question = new ConfirmationQuestion('Do you confirm generation? ', false);
        $this->confirmGeneration = $helper->ask($input, $output, $question);

    }

    /**
     * Interactive mode to add IDs list.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param QuestionHelper  $helper
     *
     * @return array
     */
    private function addIds(InputInterface $input, OutputInterface $output, QuestionHelper $helper)
    {
        $ids = $this->parseIds($input->getOption('ids'));

        while (true) {
            $output->writeln('');

            $question = new Question(
                'New ID (press <return> to stop adding ids)' . (! empty($ids) ? " (" . implode(", ", $ids) . ")" : "")
                . ' : ', null
            );
            $question->setValidator(
                function ($id) use ($ids) {
                    if (in_array($id, $ids)) {
                        throw new \InvalidArgumentException(sprintf('Id "%s" is already defined.', $id));
                    }

                    return $id;
                }
            );
            $question->setMaxAttempts(5);

            $id = $helper->ask($input, $output, $question);

            if ( ! $id) {
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
     * @param QuestionHelper  $helper
     *
     * @return array
     */
    private function getFixtureName(InputInterface $input, OutputInterface $output, QuestionHelper $helper)
    {
        $name = $input->getOption('name');


        //should ask for the name.
        $output->writeln('');

        $question = new Question('Fixture name' . ($name != "" ? " (" . $name . ")" : "") . ' : ', $name);
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
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param QuestionHelper  $helper
     *
     * @return array
     */
    private function getFixtureOrder(InputInterface $input, OutputInterface $output, QuestionHelper $helper)
    {
        $order = $input->getOption('order');

        //should ask for the name.
        $output->writeln('');

        $question = new Question('Fixture order' . ($order != "" ? " (" . $order . ")" : "") . ' : ', $order);
        $question->setValidator(
            function ($order) {
                if (preg_match("/^[1-9][0-9]*$/", $order)) {
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
        $result = array();
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
}
