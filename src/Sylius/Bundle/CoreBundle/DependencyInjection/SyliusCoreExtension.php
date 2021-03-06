<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\Bundle\CoreBundle\DependencyInjection;

use Sylius\Bundle\CoreBundle\Routing\Matcher\Dumper\PhpMatcherDumper;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\Kernel;

final class SyliusCoreExtension extends AbstractResourceExtension implements PrependExtensionInterface, CompilerPassInterface
{
    /** @var array */
    private static $bundles = [
        'sylius_addressing',
        'sylius_attribute',
        'sylius_channel',
        'sylius_currency',
        'sylius_customer',
        'sylius_inventory',
        'sylius_locale',
        'sylius_order',
        'sylius_payment',
        'sylius_payum',
        'sylius_product',
        'sylius_promotion',
        'sylius_review',
        'sylius_shipping',
        'sylius_taxation',
        'sylius_taxonomy',
        'sylius_user',
        'sylius_variation',
    ];

    /**
     * {@inheritdoc}
     */
    public function load(array $config, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration($this->getConfiguration([], $container), $config);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        $this->registerResources('sylius', $config['driver'], $config['resources'], $container);

        $loader->load('services.xml');

        $env = $container->getParameter('kernel.environment');
        if ('test' === $env || 'test_cached' === $env) {
            $loader->load('test_services.xml');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function prepend(ContainerBuilder $container): void
    {
        $config = $container->getExtensionConfig($this->getAlias());
        $config = $this->processConfiguration($this->getConfiguration([], $container), $config);

        foreach ($container->getExtensions() as $name => $extension) {
            if (in_array($name, self::$bundles, true)) {
                $container->prependExtensionConfig($name, ['driver' => $config['driver']]);
            }
        }

        $container->prependExtensionConfig('sylius_theme', ['context' => 'sylius.theme.context.channel_based']);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $this->prependHwiOauth($container, $loader);
    }

    private function prependHwiOauth(ContainerBuilder $container, LoaderInterface $loader): void
    {
        if (!$container->hasExtension('hwi_oauth')) {
            return;
        }

        $loader->load('services/integrations/hwi_oauth.xml');
    }

    public function process(ContainerBuilder $container): void
    {
        // Should be removed after PhpMatcherDumper is fixed in an another Symfony patch release
        if (!in_array(Kernel::VERSION, ['4.1.8', '4.1.9'], true)) {
            return;
        }

        $routerDefinition = $container->findDefinition('router.default');
        $routerDefinition->replaceArgument(2, array_merge(
            $routerDefinition->getArgument(2),
            ['matcher_dumper_class' => PhpMatcherDumper::class]
        ));
    }
}
