<?php

namespace KTPL\AkeneoTrashBundle\Commands;

use Akeneo\Tool\Bundle\BatchBundle\Job\JobInstanceRepository;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Command\Command;

/**
 * The connector installation commnand
 *
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
class InstallationCommand extends Command
{
    /** @var string Default command name */
    protected static $defaultName = 'ktpl:install:akeneotrash';

    /** @var array */
    protected $massActionJobs = [
        'delete_products_product_models_from_trash' => [
            'connector' => 'Akeneo Trash Mass Edit Connector',
            'type' => 'mass_delete',
            'code' => 'delete_products_product_models_from_trash',
            'config' => '{}',
            'label' => 'Mass delete products from akeneo trash'
        ],
        'restore_products_product_models_from_trash' => [
            'connector' => 'Akeneo Trash Mass Edit Connector',
            'type' => 'mass_restore',
            'code' => 'restore_products_product_models_from_trash',
            'config' => '{}',
            'label' => 'Mass restore products from akeneo trash'
        ],
        'delete_categories_from_trash' => [
            'connector' => 'Akeneo Trash Mass Edit Connector',
            'type' => 'mass_delete',
            'code' => 'delete_categories_from_trash',
            'config' => '{}',
            'label' => 'Mass delete categories from akeneo trash'
        ],
        'restore_categories_from_trash' => [
            'connector' => 'Akeneo Trash Mass Edit Connector',
            'type' => 'mass_restore',
            'code' => 'restore_categories_from_trash',
            'config' => '{}',
            'label' => 'Mass restore categories from akeneo trash'
        ],
        'delete_families_from_trash' => [
            'connector' => 'Akeneo Trash Mass Edit Connector',
            'type' => 'mass_delete',
            'code' => 'delete_families_from_trash',
            'config' => '{}',
            'label' => 'Mass delete families from akeneo trash'
        ],
        'restore_families_from_trash' => [
            'connector' => 'Akeneo Trash Mass Edit Connector',
            'type' => 'mass_restore',
            'code' => 'restore_families_from_trash',
            'config' => '{}',
            'label' => 'Mass restore families from akeneo trash'
        ],
        'delete_family_variants_from_trash' => [
            'connector' => 'Akeneo Trash Mass Edit Connector',
            'type' => 'mass_delete',
            'code' => 'delete_family_variants_from_trash',
            'config' => '{}',
            'label' => 'Mass delete family variants from akeneo trash'
        ],
        'restore_family_variants_from_trash' => [
            'connector' => 'Akeneo Trash Mass Edit Connector',
            'type' => 'mass_restore',
            'code' => 'restore_family_variants_from_trash',
            'config' => '{}',
            'label' => 'Mass restore family variants from akeneo trash'
        ],
    ];

    /** @var JobInstanceRepository */
    private $jobInstanceRepository;

    public function __construct(
        JobInstanceRepository $jobInstanceRepository
    ) {
        parent::__construct();

        $this->jobInstanceRepository = $jobInstanceRepository;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('ktpl:install:akeneotrash')
            ->setDescription('Install Akeneo Trash')
            ->setHelp('setup akeneo trash installation');
    }
    
    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->runInstallationCommand($input, $output);
        $this->createMassActionJob($input, $output);
    }

    /**
     * Run installation command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function runInstallationCommand(InputInterface $input, OutputInterface $output)
    {
        shell_exec('rm -rf ./var/cache/ && php bin/console cache:warmup;');
        shell_exec('rm -rf public/bundles public/js');
        $this->runCommand(
            'pim:installer:assets',
            [
                '--clean' => null,
                '--symlink'  => null,
            ],
            $output
        );

        $yarn_pkg = preg_replace("/\r|\n/", "", shell_exec('which yarnpkg || which yarn || echo "yarn"'));

        shell_exec('rm -rf public/css');
        shell_exec($yarn_pkg . ' run less');

        shell_exec('rm -rf public/dist-dev');
        shell_exec($yarn_pkg . ' run webpack-dev');

        $this->runCommand(
            'doctrine:schema:update',
            [
                '--force' => null,
            ],
            $output
        );
    }

    /**
     * Create mass action jobs
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function createMassActionJob(InputInterface $input, OutputInterface $output)
    {
        foreach ($this->massActionJobs as $massActionJobCode => $massActionJob) {
            $jobInstance = $this->jobInstanceRepository->findOneByIdentifier($massActionJob['code']);
            if ($jobInstance) {
                continue;
            }

            $this->runCommand(
                'akeneo:batch:create-job',
                [
                    'connector' => $massActionJob['connector'],
                    'job' => $massActionJobCode,
                    'type' => $massActionJob['type'],
                    'code' => $massActionJob['code'],
                    'config' => $massActionJob['config'],
                    'label' => $massActionJob['label'],
                ],
                $output
            );
        }
    }

    /**
     * Run commnd
     *
     * @param string          $name
     * @param array           $args
     * @param OutputInterface $output
     */
    protected function runCommand($name, array $args, $output)
    {
        $command = $this->getApplication()->find($name);
        $commandInput = new ArrayInput(
            $args
        );
        $command->run($commandInput, $output);
    }
}
