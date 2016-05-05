<?php
/*
* This file is part of the OrbitaleApiBundle package.
*
* (c) Alexandre Rock Ancelet <contact@orbitale.io>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Orbitale\Bundle\ApiBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * All entity normalization is made here because it's easier to do than using the Configuration class.
 * With the Configuration class, it would have needed nested prototypes which are a bit hard to handle...
 */
class OrbitaleApiExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        // Normalize all services' configuration
        $config = $this->normalizeServices($config);

        foreach ($config as $name => $value) {
            $container->setParameter('orbitale_api.' . $name, $value);
        }

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
    }

    /**
     * @param array $config
     *
     * @return array
     */
    private function normalizeServices(array $config = [])
    {
        if (!array_key_exists('services', $config)) {
            $config['services'] = [];
        }

        foreach ($config['services'] as $serviceName => $serviceConfig) {
            if (is_numeric($serviceName)) {
                throw new InvalidConfigurationException('Service names for the API cannot be numeric.');
            }

            $serviceConfig['name'] = $serviceName;

            // Normalize services one by one
            $serviceConfig = $this->normalizeOneService($serviceName, $serviceConfig);

            $config['services'][$serviceName] = $serviceConfig;
        }

        return $config;
    }

    /**
     * @param string $serviceName
     * @param array  $serviceConfig
     *
     * @return array
     */
    private function normalizeOneService($serviceName, array $serviceConfig = [])
    {
        $serviceConfig['entities'] = isset($serviceConfig['entities'])
            ? $this->normalizeServiceEntities($serviceName, $serviceConfig['entities'])
            : [];

        return $serviceConfig;
    }

    /**
     * @param string $serviceName
     * @param array  $serviceEntityConfig
     *
     * @return array
     */
    private function normalizeServiceEntities($serviceName, array $serviceEntityConfig = [])
    {
        $normalizedEntities = [];

        foreach ($serviceEntityConfig as $entityName => $entityConfig) {

            // Get entity name accordingly either from a string key or from the "name" parameter.
            if (is_numeric($entityName)) {
                if (!isset($entityConfig['name'])) {
                    throw new InvalidConfigurationException(sprintf(
                        'If you don\'t specify an entity name as key, you must then add the "%s" parameter to your entity config.',
                        'name'
                    ));
                }

                $entityName = $entityConfig['name'];
            }

            $entityConfig['name'] = $entityName;

            // Check that class is specified.
            if (!isset($entityConfig['class'])) {
                throw new InvalidConfigurationException(sprintf(
                    'Key "%s" must be set in service "%s" for entity %s',
                    'class', $serviceName, $entityName
                ));
            }

            if (!class_exists($entityConfig['class'])) {
                throw new InvalidConfigurationException(sprintf(
                    'Service entity "%s" must use a valid class, "%s" given.',
                    $entityName, $entityConfig['class']
                ));
            }

            // Check that the potentially specified form type is valid and extends Orbitale's one.
            // If there is no form_type, Orbitale will take care of creating one dynamically.
            if (array_key_exists('form_type', $entityConfig)) {
                if (!class_exists($entityConfig['form_type'])) {
                    throw new InvalidConfigurationException(sprintf(
                        'Service entity "%s" must use a valid form type class, "%s" given.',
                        $entityName, $entityConfig['form_type']
                    ));
                }

                $formTypeClass = 'Orbitale\Bundle\ApiBundle\Form\ApiFormType';
                if (!is_subclass_of($entityConfig['form_type'], $formTypeClass)) {
                    throw new InvalidConfigurationException(sprintf(
                        'Service entity "%s"\'s form type must extend "%s", "%s" given.',
                        $entityName, $formTypeClass, $entityConfig['form_type']
                    ));
                }
            } else {
                $entityConfig['form_type'] = null;
            }

            $normalizedEntities[$entityName] = $entityConfig;
        }

        return $normalizedEntities;
    }
}
