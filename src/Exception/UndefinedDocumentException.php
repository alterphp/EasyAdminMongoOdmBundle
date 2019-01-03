<?php

namespace AlterPHP\EasyAdminMongoOdmBundle\Exception;

use EasyCorp\Bundle\EasyAdminBundle\Exception\BaseException;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ExceptionContext;

class UndefinedDocumentException extends BaseException
{
    public function __construct(array $parameters = [])
    {
        $exceptionContext = new ExceptionContext(
            'exception.undefined_document',
            \sprintf('The "%s" document is not defined in the configuration of your backend. Solution: edit your configuration file (e.g. "congig/packages/easy_admin_mongo_odm.yaml" or "app/config/config.yml") and add the "%s" document to the list of documents managed by EasyAdmin Mongo ODM.', $parameters['document_name'], $parameters['document_name']),
            $parameters,
            404
        );

        parent::__construct($exceptionContext);
    }
}
