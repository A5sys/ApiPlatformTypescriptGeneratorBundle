<?php

namespace A5sys\ApiPlatformTypescriptGeneratorBundle\Generator;

use ApiPlatform\Core\Metadata\Property\Factory\AnnotationPropertyMetadataFactory;
use ApiPlatform\Core\Util\ReflectionClassRecursiveIterator;
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

    public function generate(string $rootFolder): Response
    {
        $groupData = [];
        foreach (ReflectionClassRecursiveIterator::getReflectionClassesFromDirectories($this->apiPlatformPaths) as $metas) {
            $className = $metas->getName();
            $metadata = $this->classMetadataFactory->getMetadataFor($className);
            $metas = $metadata->getAttributesMetadata();

            /** @var \ReflectionClass $metas */
            /** @var AttributeMetadataInterface $meta */
            foreach ($metas as $attributeName => $meta) {
                $propertyMetadata = $this->propertyMetadataFactory->create($className, $attributeName);

                $groups = $meta->getGroups();
                foreach ($groups as $group) {
                    $groupData[$group][$className][$attributeName] = [
                        'type' => $propertyMetadata->getType(),
                    ];
                }
            }
        }

        foreach ($groupData as $group => $data) {
            foreach ($data as $className => $attributes) {
                $reflection = new \ReflectionClass($className);
                $parent = $reflection->getParentClass();
                if ($parent) {
                    $parentClassName = $parent->getName();
                    if (key_exists($parentClassName, $groupData[$group])) {
                        foreach ($attributes as $attributeName => $attribute) {
                            $groupData[$group][$parentClassName][$attributeName] = $attribute;
                        }
                    }
                }
            }
        }

        if (!\file_exists($rootFolder)) {
            mkdir($rootFolder);
        }

        foreach ($groupData as $groupName => $entities) {
            $groupFolder = $rootFolder.'/ws_'.$groupName;

            if (!\file_exists($groupFolder)) {
                mkdir($groupFolder);
            }

            foreach ($entities as $entityName => $attributes) {
                $filepath = $groupFolder.'/'.$this->cleanName($entityName).'.ts';
                $content = $this->getTsContent($entityName, $attributes);
                file_put_contents($filepath, $content);
            }
        }

        return new Response();
    }

    private function getTsContent(string $entityName, array $attributes): string
    {
        $attributesImportStrings = [];
        foreach ($attributes as $attribute) {
            $import = $this->getImportContent($attribute['type']);
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
export class '.$this->cleanName($entityName).' {
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
            $strType = $this->getLocalPath($entity->getClassName()).'[]';
        } else if ($entityClass) {
            $strType = $this->getLocalPath($entityClass);
        } else if ($builtinType) {
            $strType = $this->convertBuiltinTypeToTypescript($builtinType);
        }

        if ($strType === 'DateTime') {
            $strType = 'string';
        }

        return  '  public '.$attributeName.': '.$strType.';'."\n";
    }

    private function convertBuiltinTypeToTypescript(string $type): string
    {
        switch ($type) {
            case 'int':
            case 'float':
                return 'number';
                break;
            case 'bool':
                return 'boolean';
                break;
            default:
                return $type;
        }
    }

    private function getImportContent(Type $type) : ?string
    {
        $entity = $type->getCollectionValueType();
        $entityClass = $type->getClassName();
        $strType = null;
        if ($entity) {
            $strType = $this->getLocalPath($entity->getClassName());
        } else if ($entityClass) {
            $strType = $this->getLocalPath($entityClass);
        }

        if (!$strType || $strType === 'DateTime') {
            return null;
        }

        return 'import { '.$strType.' } from \'./'.$strType.'\';'."\n";
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
}
