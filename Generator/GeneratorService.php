<?php

namespace A5sys\ApiPlatformTypescriptGeneratorBundle\Generator;

use ApiPlatform\Core\Metadata\Property\Factory\AnnotationPropertyMetadataFactory;
use ApiPlatform\Core\Util\ReflectionClassRecursiveIterator;
use function GuzzleHttp\Promise\unwrap;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Mapping\AttributeMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

class GeneratorService
{
    private $apiPlatformPaths;
    private $prefixToRemoves;
    private $classMetadataFactory;
    private $propertyMetadataFactory;

    public function __construct(
        $apiPlatformPaths,
        array $prefixToRemoves,
        ClassMetadataFactoryInterface $classMetadataFactory,
        AnnotationPropertyMetadataFactory $propertyMetadataFactory
    ) {
        $this->prefixToRemoves = $prefixToRemoves;
        $this->apiPlatformPaths = $apiPlatformPaths;
        $this->classMetadataFactory = $classMetadataFactory;
        $this->propertyMetadataFactory = $propertyMetadataFactory;
    }

    public function generate(string $rootFolder): void
    {
        $entities = [];
        foreach (ReflectionClassRecursiveIterator::getReflectionClassesFromDirectories($this->apiPlatformPaths) as $metas) {
            $className = $metas->getName();
            $metadata = $this->classMetadataFactory->getMetadataFor($className);
            $metas = $metadata->getAttributesMetadata();

            /** @var \ReflectionClass $metas */
            /** @var AttributeMetadataInterface $meta */
            foreach ($metas as $attributeName => $meta) {
                $propertyMetadata = $this->propertyMetadataFactory->create($className, $attributeName);

                $entities[$className][$attributeName] = [
                    'type' => $propertyMetadata->getType(),
                ];
            }
        }

        foreach ($entities as $className => $attributes) {
            $reflection = new \ReflectionClass($className);
            $parent = $reflection->getParentClass();
            if ($parent) {
                $parentClassName = $parent->getName();
                if (key_exists($parentClassName, $entities)) {
                    foreach ($attributes as $attributeName => $attribute) {
                        // do not overwrite parent values
                        if (!key_exists($attributeName, $entities[$parentClassName])) {
                            $entities[$parentClassName][$attributeName] = $attribute;
                        }
                    }
                }
            }
        }

        if (!\file_exists($rootFolder)) {
            mkdir($rootFolder);
        }

        foreach ($entities as $className => $attributes) {
            $subfolder = $this->getFolderByClassname($className);
            $filepath = $rootFolder.'/'.$this->getFileByClassname($className).'.ts';

            $entityFolder = $rootFolder.'/'.$subfolder;
            if (!\file_exists($entityFolder)) {
                mkdir($entityFolder, 755, true);
            }

            $content = $this->getTsContent($className, $attributes);
            file_put_contents($filepath, $content);
        }
    }

    private function fromCamelCase($input) {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $input, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }
        return implode('_', $ret);
    }

    private function getTsContent(string $entityName, array $attributes): string
    {
        $attributesImportStrings = [];
        foreach ($attributes as $name => $attribute) {
            $type = $attribute['type'];
            if ($type === null) {
                throw new \LogicException('The type is null for '.$name.' element of the entity:'.$entityName);
            }
            $import = $this->getImportContent($type, $entityName);
            if ($import) {
                $attributesImportStrings[] = $import;
            }
        }
        $attributesImportUnique = array_unique($attributesImportStrings);
        $attributesImport = join('', $attributesImportUnique);

        $attributesContent = '';
        foreach ($attributes as $attributeName => $attribute) {
            $attributesContent .= $this->getAttributeContent($attributeName, $attribute['type']);
        }

        return
$attributesImport.'
export class '.$this->getFileNameByClassname($entityName).' {
'.$attributesContent.'}
';
    }

    private function getAttributeContent($attributeName, Type $type) : string
    {
        $entity = $type->getCollectionValueType();
        $entityClass = $type->getClassName();
        $builtinType = $type->getBuiltinType();
        $strType = 'string';

        if ($entity) {
            $strType = ': '.$this->getFileNameByClassname($entity->getClassName()).'[]';
        } else if ($entityClass) {
            $strType = ': '.$this->getFileNameByClassname($entityClass);
        } else if ($builtinType) {
            $strType = $this->convertBuiltinTypeToTypescript($builtinType);
        }

        if ($strType === ': DateTime') {
            $strType = ': string';
        }

        return  '  public '.$attributeName.' '.$strType.';'."\n";
    }

    private function convertBuiltinTypeToTypescript(string $type): string
    {
        switch ($type) {
            case 'int':
            case 'float':
                return ': number';
                break;
            case 'bool':
                return ': boolean';
                break;
            case 'array':
                return '= []';
                break;
            default:
                return ': '.$type;
        }
    }

    private function getImportContent(Type $type, string $classname) : ?string
    {
        $entity = $type->getCollectionValueType();
        $entityClass = $type->getClassName();
        $strType = null;
        if ($entity) {
            if ($classname === $entity->getClassName()) {
                return null;
            }
            $strType = $this->getFileNameByClassname($entity->getClassName());
            $strTypePath = $this->getFileByClassname($entity->getClassName(), $classname);
        } else if ($entityClass) {
            if ($classname === $entityClass) {
                return null;
            }
            $strType = $this->getFileNameByClassname($entityClass);
            $strTypePath = $this->getFileByClassname($entityClass, $classname);
        }

        if (!$strType || $strType === 'DateTime') {
            return null;
        }

        return 'import { '.$strType.' } from \'./'.$strTypePath.'\';'."\n";
    }


    private function getLocalPath(string $classname): string {
        return $this->cleanName($classname);
    }

    private function cleanName(string $className): string
    {
        $cleanName = str_replace('\\', '', $className);

        foreach ($this->prefixToRemoves as $prefixToRemove) {
            $cleanName = str_replace($prefixToRemove, '', $cleanName);
        }

        return $cleanName;
    }

    private function getFolderByClassname(string $className, $popLast = true): string
    {
        $splitted = explode('\\', $className);
        $splittedKekabCase = [];
        foreach ($splitted as $split) {
            $splittedKekabCase[] = $this->fromCamelCase($split);
        }
        if ($popLast) {
            // remove last element
            array_pop($splittedKekabCase);
        }

        return join('/', $splittedKekabCase);
    }

    private function getFileByClassname(string $className, ?string $entityName = null): string
    {
        $previousPath = './';
        if ($entityName) {
            $splitted = explode('\\', $entityName);
            $countPrevious = count($splitted) - 1;
            $previousPath .= str_repeat('../', $countPrevious);
        }
        return $previousPath.$this->getFolderByClassname($className, false);
    }

    private function getFileNameByClassname(string $className): string
    {
        $splitted = explode('\\', $className);

        return array_pop($splitted);
    }
}
