<?php

namespace AlterPHP\EasyAdminMongoOdmBundle\Exception;

use EasyCorp\Bundle\EasyAdminBundle\Exception\BaseException;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ExceptionContext;

class NoDocumentsConfiguredException extends BaseException
{
    public function __construct(array $parameters = [])
    {
        $exceptionContext = new ExceptionContext(
            'exception.no_documents_configured',
            'The backend is empty because you haven\'t configured any Doctrine document to manage. Solution: edit your configuration file (e.g. "config/packages/easy_admin_mongo_odm.yaml" or "app/config/config.yml") and configure the backend under the "easy_admin_mongo_odm" key.',
            $parameters,
            500
        );

        parent::__construct($exceptionContext);
    }
}
