<?php

namespace AlterPHP\EasyAdminMongoOdmBundle\EventListener;

use AlterPHP\EasyAdminMongoOdmBundle\Exception\DocumentNotFoundException;
use Doctrine\Bundle\MongoDBBundle\ManagerRegistry;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Adds some custom attributes to the request object to store information
 * related to EasyAdmin Mongo ODM.
 */
class RequestPostInitializeListener
{
    /** @var Request|null */
    private $request;

    /** @var RequestStack|null */
    private $requestStack;

    /** @var ManagerRegistry */
    private $doctrine;

    /**
     * @param ManagerRegistry   $doctrine
     * @param RequestStack|null $requestStack
     */
    public function __construct(ManagerRegistry $doctrine, RequestStack $requestStack = null)
    {
        $this->doctrine = $doctrine;
        $this->requestStack = $requestStack;
    }

    /**
     * Adds to the request some attributes with useful information, such as the
     * current document and the selected item, if any.
     *
     * @param GenericEvent $event
     */
    public function initializeRequest(GenericEvent $event)
    {
        if (null !== $this->requestStack) {
            $this->request = $this->requestStack->getCurrentRequest();
        }

        if (null === $this->request) {
            return;
        }

        $this->request->attributes->set('easyadmin_mongo_odm', [
            'document' => $document = $event->getArgument('document'),
            'view' => $this->request->query->get('action', 'list'),
            'item' => ($id = $this->request->query->get('id')) ? $this->findCurrentItem($document, $id) : null,
        ]);
    }

    /**
     * Looks for the object that corresponds to the selected 'id' of the current document.
     *
     * @param array $documentConfig
     * @param mixed $itemId
     *
     * @return object The document
     *
     * @throws Document
     */
    private function findCurrentItem(array $documentConfig, $itemId)
    {
        if (null === $manager = $this->doctrine->getManagerForClass($documentConfig['class'])) {
            throw new \RuntimeException(\sprintf('There is no Doctrine Document Manager defined for the "%s" class', $documentConfig['class']));
        }

        if (null === $document = $manager->getRepository($documentConfig['class'])->find($itemId)) {
            throw new DocumentNotFoundException(['document_name' => $documentConfig['name'], 'document_id_name' => $documentConfig['primary_key_field_name'], 'document_id_value' => $itemId]);
        }

        return $document;
    }
}
