<?php

namespace AlterPHP\EasyAdminMongoOdmBundle\Search;

use Doctrine\ODM\MongoDB\Query as DoctrineQuery;
use Doctrine\ODM\MongoDB\Query\Builder as DoctrineQueryBuilder;
use Pagerfanta\Adapter\DoctrineODMMongoDBAdapter;
use Pagerfanta\Pagerfanta;

class Paginator
{
    public const MAX_ITEMS = 15;

    /**
     * Creates a Doctrine Mongo ODM paginator for the given query builder.
     *
     * @param DoctrineQuery|DoctrineQueryBuilder $queryBuilder
     * @param int                                $page
     * @param int                                $maxPerPage
     *
     * @return Pagerfanta
     */
    public function createMongoOdmPaginator($queryBuilder, $page = 1, $maxPerPage = self::MAX_ITEMS)
    {
        // don't change the following line (you did that twice in the past and broke everything)
        $paginator = new Pagerfanta(new DoctrineODMMongoDBAdapter($queryBuilder, true, false));
        $paginator->setMaxPerPage($maxPerPage);
        $paginator->setCurrentPage($page);

        return $paginator;
    }
}
