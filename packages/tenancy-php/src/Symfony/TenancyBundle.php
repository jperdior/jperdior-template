<?php

declare(strict_types=1);

namespace Jperdior\Tenancy\Symfony;

use Jperdior\Tenancy\Domain\TenantResolverInterface;
use Jperdior\Tenancy\Infrastructure\Resolver\JwtClaimTenantResolver;
use Jperdior\Tenancy\Infrastructure\Resolver\SubdomainTenantResolver;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Wires {@see \Jperdior\Tenancy\Domain\TenantContext}, the chosen
 * {@see \Jperdior\Tenancy\Domain\TenantResolverInterface}, and
 * {@see \Jperdior\Tenancy\Symfony\EventListener\ResolveTenantListener}.
 *
 * Projects still register the Doctrine SQL filter themselves in `doctrine.yaml`:
 *
 *   doctrine:
 *     orm:
 *       filters:
 *         tenant:
 *           class: Jperdior\Tenancy\Infrastructure\Doctrine\TenantFilter
 *           enabled: false
 */
final class TenancyBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('resolver')
                    ->info('Which reference resolver to alias as TenantResolverInterface. Override by aliasing the interface to your own service.')
                    ->values(['jwt_claim', 'subdomain'])
                    ->defaultValue('jwt_claim')
                ->end()
                ->scalarNode('filter_name')
                    ->info('Name of the Doctrine SQL filter registered for tenant scoping.')
                    ->defaultValue('tenant')
                ->end()
                ->arrayNode('jwt_claim')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('claim_name')
                            ->info('JWT payload claim that carries the tenant UUID.')
                            ->defaultValue('tenant_id')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('subdomain')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('base_domain')
                            ->info('Domain suffix to strip; the remaining left label is the tenant UUID.')
                            ->defaultValue('example.com')
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array{
     *   resolver: 'jwt_claim'|'subdomain',
     *   filter_name: string,
     *   jwt_claim: array{claim_name: string},
     *   subdomain: array{base_domain: string},
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter('tenancy.filter_name', $config['filter_name']);
        $builder->setParameter('tenancy.jwt_claim.claim_name', $config['jwt_claim']['claim_name']);
        $builder->setParameter('tenancy.subdomain.base_domain', $config['subdomain']['base_domain']);

        $container->import(__DIR__.'/Resources/config/services.yaml');

        $resolverServiceId = match ($config['resolver']) {
            'jwt_claim' => JwtClaimTenantResolver::class,
            'subdomain' => SubdomainTenantResolver::class,
        };
        $container->services()->alias(TenantResolverInterface::class, $resolverServiceId);
    }
}
