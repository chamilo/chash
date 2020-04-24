<?php

namespace Chash\DoctrineExtensions;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;

class TablePrefix
{
    protected $prefix = '';

    public function __construct($prefix)
    {
        $this->prefix = (string) $prefix;
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs)
    {
        $classMetadata = $eventArgs->getClassMetadata();
        $classMetadata->setTableName($this->prefix.$classMetadata->getTableName());

        foreach ($classMetadata->getAssociationMappings() as $fieldName => $mapping) {
            if (\Doctrine\ORM\Mapping\ClassMetadataInfo::MANY_TO_MANY == $mapping['type']) {
                $mappedTableName = $classMetadata->associationMappings[$fieldName]['joinTable']['name'];
                $classMetadata->associationMappings[$fieldName]['joinTable']['name'] = $this->prefix.$mappedTableName;
            }
        }
    }
}
