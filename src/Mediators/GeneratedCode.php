<?php
namespace Apie\DoctrineEntityConverter\Mediators;

use Apie\Core\Dto\DtoInterface;
use Apie\DoctrineEntityConverter\Concerns\HasGeneralDoctrineFields;
use Apie\DoctrineEntityConverter\Interfaces\GeneratedDoctrineEntityInterface;
use Apie\DoctrineEntityConverter\Utils\Utils;
use Doctrine\ORM\Mapping\Entity;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\Property;
use Nette\PhpGenerator\PsrPrinter;

class GeneratedCode
{
    private PhpNamespace $namespace;
    private ClassType $classType;
    private Method $createFrom;
    private Method $updateFrom;
    private string $createFromCode = PHP_EOL;

    private Method $inject;
    private string $injectCode = PHP_EOL;

    public function __construct(string $namespace, string $className, ?string $originalClassName)
    {
        $this->namespace = new PhpNamespace($namespace);
        $this->namespace->addUse(DtoInterface::class);
        $this->namespace->addUse('Doctrine\ORM\Mapping', 'ORM');
        $this->namespace->addUse(GeneratedDoctrineEntityInterface::class);
        $this->namespace->addUse(HasGeneralDoctrineFields::class);
        $this->namespace->addUse(Utils::class);
        if ($originalClassName) {
            $this->namespace->addUse($originalClassName, 'OriginalDomainObject');
        }

        $this->classType = $this->namespace->addClass($className);
        $this->classType->addTrait(HasGeneralDoctrineFields::class);
        $this->classType->addImplement(DtoInterface::class);
        $this->classType->addImplement(GeneratedDoctrineEntityInterface::class);
        $this->classType->addAttribute(Entity::class);

        $method = $this->classType->addMethod('getOriginalClassName')->setStatic(true)->setPublic();
        $method->setComment(
            'Return original domain object class.'
             . PHP_EOL
             . PHP_EOL
             . '@return class-string<OriginalDomainObject>'
        );
        $method->setReturnType('?string');
        $method->setBody($originalClassName ? 'return OriginalDomainObject::class;' : 'return null;');

        $this->createFrom = $this->classType->addMethod('createFrom')->setStatic(true)->setPublic();
        $this->createFrom->addParameter('input')->setType($originalClassName);
        $this->createFrom->setComment('Creates a doctrine entity from a domain class.');
        $this->createFrom->setReturnType('self');
        $this->createFrom->setBody('$instance = new self();' . PHP_EOL . 'return $instance;');

        $this->updateFrom = $this->classType->addMethod('updateFrom')->setPublic();
        $this->updateFrom->addParameter('input')->setType($originalClassName);
        $this->updateFrom->setComment('Updates a doctrine entity from the domain class.');
        $this->updateFrom->setReturnType('self');
        $this->updateFrom->setBody('$instance = $this;' . PHP_EOL . 'return $instance;');

        $this->inject = $this->classType->addMethod('inject')->setPublic();
        $this->inject->setComment('Overwrite the properties of the domain object with what is found in the entity.');
        $this->inject->setReturnType('void');
        $this->inject->addParameter('instance')->setType($originalClassName);
    }

    public function getNamespace(): string
    {
        return $this->namespace->getName();
    }

    public function addUse(string $typehint): self
    {
        $this->namespace->addUse($typehint);

        return $this;
    }

    public function addProperty(string $typehint, string $propertyName): Property
    {
        if (str_starts_with($typehint, 'apie_')) {
            $typehint = $this->namespace->getName() . '\\' . $typehint;
        }
        $property = $this->classType->addProperty($propertyName)
            ->setType($typehint);
        return $property;
    }

    public function addCreateFromCode(string $code): void
    {
        $this->createFromCode .= PHP_EOL . $code;
        $this->createFrom->setBody('$instance = new self();' . $this->createFromCode . PHP_EOL . 'return $instance;');
        $this->updateFrom->setBody('$instance = $this;' . $this->createFromCode . PHP_EOL . 'return $instance;');
    }

    public function addInjectCode(string $code): void
    {
        $this->injectCode .= PHP_EOL . $code;
        $this->inject->setBody($this->injectCode);
    }

    public function toCode(): string
    {
        $printer = new PsrPrinter();
        return '<?php' . PHP_EOL . $printer->printNamespace($this->namespace);
    }
}
