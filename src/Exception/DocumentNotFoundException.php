<?php

namespace AlterPHP\EasyAdminMongoOdmBundle\Exception;

use EasyCorp\Bundle\EasyAdminBundle\Exception\BaseException;
use EasyCorp\Bundle\EasyAdminBundle\Exception\ExceptionContext;

class DocumentNotFoundException extends BaseException
{
    public function __construct(array $parameters = [])
    {
        $exceptionContext = new ExceptionContext(
            'exception.document_not_found',
            \sprintf('The "%s" document with "%s = %s" does not exist in the database. The document may have been deleted by mistake or by a "cascade={"remove"}" operation executed by Doctrine.', $parameters['document_name'], $parameters['document_id_name'], $parameters['document_id_value']),
            $parameters,
            404
        );

        parent::__construct($exceptionContext);
    }
}
