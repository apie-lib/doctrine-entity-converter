<?php
namespace Apie\DoctrineEntityConverter;

use Apie\ServiceProviderGenerator\UseGeneratedMethods;
use Illuminate\Support\ServiceProvider;

/**
 * This file is generated with apie/service-provider-generator from file: doctrine_entity_converter.yaml
 * @codeCoverageIgnore
 */
class DoctrineEntityConverterProvider extends ServiceProvider
{
    use UseGeneratedMethods;

    public function register()
    {
        $this->app->singleton(
            \Apie\DoctrineEntityConverter\OrmBuilder::class,
            function ($app) {
                return new \Apie\DoctrineEntityConverter\OrmBuilder(
                    $app->make(\Apie\DoctrineEntityConverter\Factories\PersistenceLayerFactory::class),
                    $app->make(\Apie\Core\BoundedContext\BoundedContextHashmap::class),
                    $this->parseArgument('%kernel.debug%')
                );
            }
        );
        $this->app->singleton(
            \Apie\DoctrineEntityConverter\Factories\PersistenceLayerFactory::class,
            function ($app) {
                return new \Apie\DoctrineEntityConverter\Factories\PersistenceLayerFactory(
                
                );
            }
        );
    }
}
