<img src="https://raw.githubusercontent.com/apie-lib/apie-lib-monorepo/main/docs/apie-logo.svg" width="100px" align="left" />
<h1>doctrine-entity-converter</h1>






 [![Latest Stable Version](https://poser.pugx.org/apie/doctrine-entity-converter/v)](https://packagist.org/packages/apie/doctrine-entity-converter) [![Total Downloads](https://poser.pugx.org/apie/doctrine-entity-converter/downloads)](https://packagist.org/packages/apie/doctrine-entity-converter) [![Latest Unstable Version](https://poser.pugx.org/apie/doctrine-entity-converter/v/unstable)](https://packagist.org/packages/apie/doctrine-entity-converter) [![License](https://poser.pugx.org/apie/doctrine-entity-converter/license)](https://packagist.org/packages/apie/doctrine-entity-converter) [![PHP Composer](https://apie-lib.github.io/projectCoverage/coverage-doctrine-entity-converter.svg)](https://apie-lib.github.io/projectCoverage/doctrine-entity-converter/index.html)  

[![PHP Composer](https://github.com/apie-lib/doctrine-entity-converter/actions/workflows/php.yml/badge.svg?event=push)](https://github.com/apie-lib/doctrine-entity-converter/actions/workflows/php.yml)

This package is part of the [Apie](https://github.com/apie-lib) library.
The code is maintained in a monorepo, so PR's need to be sent to the [monorepo](https://github.com/apie-lib/apie-lib-monorepo/pulls)

## Documentation
Generates Doctrine entity classes and mappings from Apie domain objects, so a bounded context can be persisted with Doctrine ORM without hand-writing entity/mapping code.

### Standalone usage
Install it with:
```bash
composer require apie/doctrine-entity-converter
```

Use `Apie\DoctrineEntityConverter\OrmBuilder` (built via `Apie\DoctrineEntityConverter\Factories\PersistenceLayerFactory`) to generate Doctrine entities and mappings for a `Apie\Core\BoundedContext\BoundedContextHashmap`. The generated PHP classes can be used by Doctrine ORM directly, independent of any framework.

### Symfony integration
Via `apie/apie-bundle`, `doctrine_entity_converter.yaml` is loaded automatically and registers `Apie\DoctrineEntityConverter\OrmBuilder`, wired to the bounded contexts and `%kernel.debug%`.

### Laravel integration
Via `apie/laravel-apie`, the generated `Apie\DoctrineEntityConverter\DoctrineEntityConverterProvider` is auto-registered and wires the same `OrmBuilder` and `PersistenceLayerFactory` services into the Laravel container.
