<?php
namespace Apie\DoctrineEntityConverter;

use Apie\ServiceProviderGenerator\UseGeneratedMethods;
use Illuminate\Support\ServiceProvider;

/**
 * This file is generated with apie/service-provider-generator from file: doctrine_entity_converter.yaml
 * @codecoverageIgnore
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
                    $app->make(\Apie\DoctrineEntityConverter\EntityBuilder::class),
                    $app->make(\Apie\Core\Persistence\PersistenceLayerFactory::class),
                    $app->make(\Apie\Core\BoundedContext\BoundedContextHashmap::class),
                    $this->parseArgument('%kernel.debug%')
                );
            }
        );
        $this->app->singleton(
            \Apie\DoctrineEntityConverter\EntityBuilder::class,
            function ($app) {
                return \Apie\DoctrineEntityConverter\EntityBuilder::create(
                    'Generated\\\\'
                );
                
            }
        );
        
    }
}
