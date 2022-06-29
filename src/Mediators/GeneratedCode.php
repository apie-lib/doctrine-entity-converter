<?php
namespace Apie\DoctrineEntityConverter\Mediators;

use Apie\Core\Dto\DtoInterface;
use Apie\DoctrineEntityConverter\Utils\Utils;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\Property;
use Nette\PhpGenerator\PsrPrinter;
use ReflectionProperty;

class GeneratedCode
{
    private PhpNamespace $namespace;
    private ClassType $classType;
    private Method $createFrom;
    private string $createFromCode = PHP_EOL;

    private Method $inject;
    private string $injectCode = PHP_EOL;

    public function __construct(string $namespace, string $className, string $originalClassName)
    {
        $this->namespace = new PhpNamespace($namespace);
        $this->namespace->addUse(DtoInterface::class);
        $this->namespace->addUse('Doctrine\ORM\Mapping', 'ORM');
        $this->namespace->addUse(Utils::class);
        $this->namespace->addUse($originalClassName, 'OriginalDomainObject');

        $this->classType = $this->namespace->addClass($className);
        $this->classType->addImplement(DtoInterface::class);
        $this->classType->addMethod('__construct')->setPrivate(true);

        $this->createFrom = $this->classType->addMethod('createFrom')->setStatic(true)->setPublic(true);
        $this->createFrom->addParameter('input')->setType($originalClassName);
        $this->createFrom->setReturnType('self');
        $this->createFrom->setBody('$instance = new self();' . PHP_EOL . 'return $instance;');

        $this->inject = $this->classType->addMethod('inject')->setPublic(true);
        $this->inject->setReturnType('void');
        $this->inject->addParameter('instance')->setType($originalClassName);
        $this->inject->addParameter('property')->setType(ReflectionProperty::class);
    }

    public function addUse(string $typehint)
    {
        return $this->namespace->addUse($typehint);
    }

    public function addProperty(string $typehint, string $propertyName): Property
    {
        $property = $this->classType->addProperty($propertyName)
            ->setType($typehint);
        return $property;
    }

    public function addCreateFromCode(string $code)
    {
        $this->createFromCode .= PHP_EOL . $code;
        $this->createFrom->setBody('$instance = new self();' . $this->createFromCode . PHP_EOL . 'return $instance;');
    }

    public function addInjectCode(string $code)
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
