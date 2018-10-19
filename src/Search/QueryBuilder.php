<?php

namespace AlterPHP\EasyAdminMongoOdmBundle\Search;

use Doctrine\Bundle\MongoDBBundle\ManagerRegistry;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Query\Builder as DoctrineQueryBuilder;
use Ramsey\Uuid\Uuid;

class QueryBuilder
{
    /** @var ManagerRegistry */
    private $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * Creates the query builder used to get all the records displayed by the
     * "list" view.
     *
     * @param array       $documentConfig
     * @param string|null $sortField
     * @param string|null $sortDirection
     *
     * @return DoctrineQueryBuilder
     */
    public function createListQueryBuilder(array $documentConfig, $sortField = null, $sortDirection = null)
    {
        /* @var DocumentManager */
        $dm = $this->doctrine->getManagerForClass($documentConfig['class']);
        /* @var DoctrineQueryBuilder */
        $queryBuilder = $dm->createQueryBuilder($documentConfig['class']);

        /* NO_ASSOCIATION
        $isSortedByDoctrineAssociation = false !== strpos($sortField, '.');
        if ($isSortedByDoctrineAssociation) {
            $sortFieldParts = explode('.', $sortField);
            $queryBuilder->leftJoin('document.'.$sortFieldParts[0], $sortFieldParts[0]);
        }*/

        if (null !== $sortField) {
            $queryBuilder->sort($sortField, $sortDirection);
        }

        return $queryBuilder;
    }

    /**
     * Creates the query builder used to get the results of the search query
     * performed by the user in the "search" view.
     *
     * @param array       $documentConfig
     * @param string      $searchQuery
     * @param string|null $sortField
     * @param string|null $sortDirection
     *
     * @return DoctrineQueryBuilder
     */
    public function createSearchQueryBuilder(array $documentConfig, $searchQuery, $sortField = null, $sortDirection = null)
    {
        /* @var DocumentManager */
        $dm = $this->doctrine->getManagerForClass($documentConfig['class']);
        /* @var DoctrineQueryBuilder */
        $queryBuilder = $dm->createQueryBuilder($documentConfig['class']);

        $isSearchQueryNumeric = is_numeric($searchQuery);
        $isSearchQuerySmallInteger = (is_int($searchQuery) || ctype_digit($searchQuery)) && $searchQuery >= -32768 && $searchQuery <= 32767;
        $isSearchQueryInteger = (is_int($searchQuery) || ctype_digit($searchQuery)) && $searchQuery >= -2147483648 && $searchQuery <= 2147483647;
        $isSearchQueryUuid = Uuid::isValid($searchQuery);
        $lowerSearchQuery = mb_strtolower($searchQuery);

        // NO_ASSOCIATION $documentAlreadyJoined = array();
        foreach ($documentConfig['search']['fields'] as $fieldName => $metadata) {
            /* NO_ASSOCIATION
            if (false !== strpos($fieldName, '.')) {
                list($associatedDocumentName, $associatedFieldName) = explode('.', $fieldName);
                if (!in_array($associatedDocumentName, $documentAlreadyJoined)) {
                    $queryBuilder->leftJoin('document.'.$associatedDocumentName, $associatedDocumentName);
                    $documentAlreadyJoined[] = $associatedDocumentName;
                }

                $documentName = $associatedDocumentName;
                $fieldName = $associatedFieldName;
            }*/

            $isSmallIntegerField = 'smallint' === $metadata['dataType'];
            $isIntegerField = 'integer' === $metadata['dataType'];
            $isNumericField = in_array($metadata['dataType'], array('number', 'bigint', 'decimal', 'float'));
            $isTextField = in_array($metadata['dataType'], array('string', 'text'));
            $isGuidField = 'guid' === $metadata['dataType'];

            // this complex condition is needed to avoid issues on PostgreSQL databases
            if (
                $isSmallIntegerField && $isSearchQuerySmallInteger ||
                $isIntegerField && $isSearchQueryInteger ||
                $isNumericField && $isSearchQueryNumeric
            ) {
                // adding '0' turns the string into a numeric value
                $queryBuilder->addOr($queryBuilder->expr()->field($fieldName)->equals(0 + $searchQuery));
            } elseif ($isGuidField && $isSearchQueryUuid) {
                $queryBuilder->addOr($queryBuilder->expr()->field($fieldName)->equals($searchQuery));
            } elseif ($isTextField) {
                // Fuzzy query
                $fuzzyRegexp = new \MongoRegex('/.*'.$lowerSearchQuery.'.*/i');
                $queryBuilder->addOr(
                    $queryBuilder->expr()->field($fieldName)->operator('$regex', $fuzzyRegexp)
                );
                // Words query
                $queryBuilder->addOr(
                    $queryBuilder->expr()->field($fieldName)->in(explode(' ', $lowerSearchQuery))
                );
            }
        }

        /* NO_ASSOCIATION
        $isSortedByDoctrineAssociation = false !== strpos($sortField, '.');
        if ($isSortedByDoctrineAssociation) {
            list($associatedDocumentName, $associatedFieldName) = explode('.', $sortField);
            if (!in_array($associatedDocumentName, $documentAlreadyJoined)) {
                $queryBuilder->leftJoin('document.'.$associatedDocumentName, $associatedDocumentName);
                $documentAlreadyJoined[] = $associatedDocumentName;
            }
        }*/

        if (null !== $sortField) {
            $queryBuilder->sort($sortField, $sortDirection ?: 'DESC');
        }

        return $queryBuilder;
    }
}
