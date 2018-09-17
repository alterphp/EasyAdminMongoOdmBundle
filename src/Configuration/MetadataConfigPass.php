<?php

namespace AlterPHP\EasyAdminMongoOdmBundle\Configuration;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use EasyCorp\Bundle\EasyAdminBundle\Configuration\ConfigPassInterface;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;

/**
 * Introspects the metadata of the Doctrine documents to complete the
 * configuration of the properties.
 */
class MetadataConfigPass implements ConfigPassInterface
{
    /** @var ManagerRegistry */
    private $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    public function process(array $backendConfig)
    {
        foreach ($backendConfig['documents'] as $documentName => $documentConfig) {
            try {
                $em = $this->doctrine->getManagerForClass($documentConfig['class']);
            } catch (\ReflectionException $e) {
                throw new InvalidTypeException(sprintf('The configured class "%s" for the path "easy_admin_mongo_odm.documents.%s" does not exist. Did you forget to create the document class or to define its namespace?', $documentConfig['class'], $documentName));
            }

            if (null === $em) {
                throw new InvalidTypeException(sprintf('The configured class "%s" for the path "easy_admin_mongo_odm.documents.%s" is no mapped document.', $documentConfig['class'], $documentName));
            }

            $documentMetadata = $em->getMetadataFactory()->getMetadataFor($documentConfig['class']);

            $documentConfig['primary_key_field_name'] = $documentMetadata->getIdentifierFieldNames()[0];

            $documentConfig['properties'] = $this->processDocumentPropertiesMetadata($documentMetadata);

            $backendConfig['documents'][$documentName] = $documentConfig;
        }

        return $backendConfig;
    }

    /**
     * Takes the document metadata introspected via Doctrine and completes its
     * contents to simplify data processing for the rest of the application.
     *
     * @param ClassMetadata $documentMetadata The document metadata introspected via Doctrine
     *
     * @return array The document properties metadata provided by Doctrine
     */
    private function processDocumentPropertiesMetadata(ClassMetadata $documentMetadata)
    {
        $documentPropertiesMetadata = array();

        // SORT_ONLY_INDEXES
        $singleIndexes = $documentMetadata->getIdentifierFieldNames();
        $singleIndexes = array_filter(array_merge($singleIndexes, array_map(function ($idx) {
            if (1 === count($idx['keys'])) {
                $indexes = array_keys($idx['keys']);

                return reset($indexes);
            }
        }, $documentMetadata->getIndexes())));

        // introspect regular document fields
        foreach ($documentMetadata->fieldMappings as $fieldName => $fieldMetadata) {
            $documentPropertiesMetadata[$fieldName] = array_merge($fieldMetadata, array(
                // SORT_ONLY_INDEXES
                'sortable' => in_array($fieldName, $singleIndexes),
            ));
        }

        // introspect fields for document associations
        foreach ($documentMetadata->associationMappings as $fieldName => $associationMetadata) {
            $documentPropertiesMetadata[$fieldName] = array_merge($associationMetadata, array(
                'type' => 'association',
                'associationType' => $associationMetadata['type'],
            ));

            // associations different from *-to-one cannot be sorted
            if (ClassMetadata::MANY === $associationMetadata['type']) {
                $documentPropertiesMetadata[$fieldName]['sortable'] = false;
            }
        }

        return $documentPropertiesMetadata;
    }
}
