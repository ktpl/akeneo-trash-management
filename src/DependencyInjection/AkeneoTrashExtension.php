<?php

namespace KTPL\AkeneoTrashBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * The akeneo trash bundle extension
 *
 * @author    Krishan Kant <krishan.kant@krishtechnolabs.com>
 * @copyright 2021 Krishtechnolabs (https://www.krishtechnolabs.com/)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class AkeneoTrashExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('adapters.yml');
        $loader->load('controllers.yml');
        $loader->load('commands.yml');
        $loader->load('datagrid_listeners.yml');
        $loader->load('datagrid_actions.yml');
        $loader->load('data_sources.yml');
        $loader->load('normalizers.yml');
        $loader->load('factories.yml');
        $loader->load('jobs.yml');
        $loader->load('job_constraints.yml');
        $loader->load('job_defaults.yml');
        $loader->load('managers.yml');
        $loader->load('mass_actions.yml');
        $loader->load('pagers.yml');
        $loader->load('repositories.yml');
        $loader->load('removers.yml');
        $loader->load('services.yml');
        $loader->load('steps.yml');
    }
}
