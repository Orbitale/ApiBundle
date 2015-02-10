<?php

namespace Pierstoval\Bundle\ApiBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class PierstovalApiExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        if (!isset($config['format']) || (isset($config['format']) && !$config['format'])) {
            $config['format'] = 'json';
        }

        if (isset($config['services'])) {
            foreach ($config['services'] as $name => $v) {
                $config['services'][$name]['name'] = $name;
            }
        }

        if (strpos($container->getParameter('kernel.environment'), 'dev') === 0) {
            // If we're in dev environment, automatically allows localhost
            $config['allowed_origins'][] = '127.0.0.1';
            $config['allowed_origins'][] = 'localhost';
            $config['allowed_origins'][] = '::1';
        }
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $config['allowed_origins'][] = $_SERVER['REMOTE_ADDR'];
        }
        if (isset($_SERVER['SERVER_ADDR'])) {
            $config['allowed_origins'][] = $_SERVER['SERVER_ADDR'];
        }

        // Remove duplicates, in case remote and server are the same as your dev environment
        $config['allowed_origins'] = array_unique($config['allowed_origins']);

        foreach ($config as $name => $value) {
            $container->setParameter('pierstoval_api.'.$name, $value);
        }

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }
}
