<?php

namespace AlterPHP\EasyAdminMongoOdmBundle\Search;

use Doctrine\Bundle\MongoDBBundle\ManagerRegistry;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Query\Builder as DoctrineQueryBuilder;

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

        $isSortedByDoctrineAssociation = false !== strpos($sortField, '.');
        if ($isSortedByDoctrineAssociation) {
            $sortFieldParts = explode('.', $sortField);
            $queryBuilder->leftJoin('document.'.$sortFieldParts[0], $sortFieldParts[0]);
        }

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
        $isSearchQueryUuid = 1 === preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $searchQuery);
        $lowerSearchQuery = mb_strtolower($searchQuery);

        $queryParameters = array();
        $documentAlreadyJoined = array();
        foreach ($documentConfig['search']['fields'] as $fieldName => $metadata) {
            $documentName = 'document';
            if (false !== strpos($fieldName, '.')) {
                list($associatedDocumentName, $associatedFieldName) = explode('.', $fieldName);
                if (!in_array($associatedDocumentName, $documentAlreadyJoined)) {
                    $queryBuilder->leftJoin('document.'.$associatedDocumentName, $associatedDocumentName);
                    $documentAlreadyJoined[] = $associatedDocumentName;
                }

                $documentName = $associatedDocumentName;
                $fieldName = $associatedFieldName;
            }

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
                $queryBuilder->orWhere(sprintf('%s.%s = :numeric_query', $documentName, $fieldName));
                // adding '0' turns the string into a numeric value
                $queryParameters['numeric_query'] = 0 + $searchQuery;
            } elseif ($isGuidField && $isSearchQueryUuid) {
                $queryBuilder->orWhere(sprintf('%s.%s = :uuid_query', $documentName, $fieldName));
                $queryParameters['uuid_query'] = $searchQuery;
            } elseif ($isTextField) {
                $queryBuilder->orWhere(sprintf('LOWER(%s.%s) LIKE :fuzzy_query', $documentName, $fieldName));
                $queryParameters['fuzzy_query'] = '%'.$lowerSearchQuery.'%';

                $queryBuilder->orWhere(sprintf('LOWER(%s.%s) IN (:words_query)', $documentName, $fieldName));
                $queryParameters['words_query'] = explode(' ', $lowerSearchQuery);
            }
        }

        if (0 !== count($queryParameters)) {
            $queryBuilder->setParameters($queryParameters);
        }

        $isSortedByDoctrineAssociation = false !== strpos($sortField, '.');
        if ($isSortedByDoctrineAssociation) {
            list($associatedDocumentName, $associatedFieldName) = explode('.', $sortField);
            if (!in_array($associatedDocumentName, $documentAlreadyJoined)) {
                $queryBuilder->leftJoin('document.'.$associatedDocumentName, $associatedDocumentName);
                $documentAlreadyJoined[] = $associatedDocumentName;
            }
        }

        if (null !== $sortField) {
            $queryBuilder->sort($sortField, $sortDirection ?: 'DESC');
        }

        return $queryBuilder;
    }
}
