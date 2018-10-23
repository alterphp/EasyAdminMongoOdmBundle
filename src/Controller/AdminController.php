<?php

namespace AlterPHP\EasyAdminMongoOdmBundle\Controller;

use AlterPHP\EasyAdminMongoOdmBundle\Event\EasyAdminMongoOdmEvents;
use AlterPHP\EasyAdminMongoOdmBundle\Exception\ForbiddenActionException;
use AlterPHP\EasyAdminMongoOdmBundle\Exception\NoDocumentsConfiguredException;
use AlterPHP\EasyAdminMongoOdmBundle\Exception\UndefinedDocumentException;
use Doctrine\Common\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AdminController as BaseAdminController;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends BaseAdminController
{
    /** @var array The full configuration of the entire backend */
    protected $mongoOdmConfig;
    /** @var array The full configuration of the current document */
    protected $document = array();
    /** @var Request The instance of the current Symfony request */
    protected $request;
    /** @var DocumentManager The Doctrine document manager for the current document */
    protected $dm;

    /**
     * @Route("/", name="easyadmin_mongo_odm")
     *
     * @param Request $request
     *
     * @return RedirectResponse|Response
     */
    public function indexAction(Request $request)
    {
        $this->initialize($request);

        if (null === $request->query->get('document')) {
            return $this->redirectToBackendHomepage();
        }

        $action = $request->query->get('action', 'list');
        if (!$this->isActionAllowed($action)) {
            throw new ForbiddenActionException(array('action' => $action, 'document_name' => $this->document['name']));
        }

        return $this->executeDynamicMethod($action.'<DocumentName>Action');
    }

    /**
     * Utility method which initializes the configuration of the document on which
     * the user is performing the action.
     *
     * @param Request $request
     */
    protected function initialize(Request $request)
    {
        $this->dispatch(EasyAdminMongoOdmEvents::PRE_INITIALIZE);

        $this->mongoOdmConfig = $this->get('easyadmin_mongo_odm.config.manager')->getBackendConfig();

        if (0 === count($this->mongoOdmConfig['documents'])) {
            throw new NoDocumentsConfiguredException();
        }

        // this condition happens when accessing the backend homepage and before
        // redirecting to the default page set as the homepage
        if (null === $documentName = $request->query->get('document')) {
            return;
        }

        if (!array_key_exists($documentName, $this->mongoOdmConfig['documents'])) {
            throw new UndefinedDocumentException(array('document_name' => $documentName));
        }

        $this->document = $this->get('easyadmin_mongo_odm.config.manager')->getDocumentConfiguration($documentName);

        $action = $request->query->get('action', 'list');
        if (!$request->query->has('sortField')) {
            $sortField = isset($this->document[$action]['sort']['field']) ? $this->document[$action]['sort']['field'] : $this->document['primary_key_field_name'];
            $request->query->set('sortField', $sortField);
        }
        if (!$request->query->has('sortDirection')) {
            $sortDirection = isset($this->document[$action]['sort']['direction']) ? $this->document[$action]['sort']['direction'] : 'DESC';
            $request->query->set('sortDirection', $sortDirection);
        }

        $this->dm = $this->getMongoOdmDoctrine()->getManagerForClass($this->document['class']);
        $this->request = $request;

        $this->dispatch(EasyAdminMongoOdmEvents::POST_INITIALIZE);
    }

    protected function dispatch($eventName, array $arguments = array())
    {
        $arguments = array_replace(array(
            'config' => $this->mongoOdmConfig,
            'dm' => $this->dm,
            'document' => $this->document,
            'request' => $this->request,
        ), $arguments);

        $subject = isset($arguments['paginator']) ? $arguments['paginator'] : $arguments['document'];
        $event = new GenericEvent($subject, $arguments);

        $this->get('event_dispatcher')->dispatch($eventName, $event);
    }

    /**
     * The method that is executed when the user performs a 'list' action on an document.
     *
     * @return Response
     */
    protected function listAction()
    {
        $this->dispatch(EasyAdminMongoOdmEvents::PRE_LIST);

        $fields = $this->document['list']['fields'];
        $paginator = $this->mongoOdmFindAll($this->document['class'], $this->request->query->get('page', 1), $this->document['list']['max_results'], $this->request->query->get('sortField'), $this->request->query->get('sortDirection'));

        $this->dispatch(EasyAdminMongoOdmEvents::POST_LIST, array('paginator' => $paginator));

        $parameters = array(
            'paginator' => $paginator,
            'fields' => $fields,
            // RESTRICTED_ACTIONS 'delete_form_template' => $this->createDeleteForm($this->document['name'], '__id__')->createView(),
        );

        return $this->executeDynamicMethod('render<DocumentName>Template', array('list', $this->document['templates']['list'], $parameters));
    }

    /**
     * The method that is executed when the user performs a query on an document.
     *
     * @return Response
     */
    protected function searchAction()
    {
        $this->dispatch(EasyAdminMongoOdmEvents::PRE_SEARCH);

        $query = trim($this->request->query->get('query'));
        // if the search query is empty, redirect to the 'list' action
        if ('' === $query) {
            $queryParameters = array_replace($this->request->query->all(), array('action' => 'list', 'query' => null));
            $queryParameters = array_filter($queryParameters);

            return $this->redirect($this->get('router')->generate('easyadmin_mongo_odm', $queryParameters));
        }

        $searchableFields = $this->document['search']['fields'];
        $paginator = $this->mongoOdmFindBy(
            $this->document['class'],
            $query,
            $searchableFields,
            $this->request->query->get('page', 1),
            $this->document['list']['max_results'],
            isset($this->document['search']['sort']['field']) ? $this->document['search']['sort']['field'] : $this->request->query->get('sortField'),
            isset($this->document['search']['sort']['direction']) ? $this->document['search']['sort']['direction'] : $this->request->query->get('sortDirection')
        );
        $fields = $this->document['list']['fields'];

        $this->dispatch(EasyAdminMongoOdmEvents::POST_SEARCH, array(
            'fields' => $fields,
            'paginator' => $paginator,
        ));

        $parameters = array(
            'paginator' => $paginator,
            'fields' => $fields,
            // RESTRICTED_ACTIONS 'delete_form_template' => $this->createDeleteForm($this->document['name'], '__id__')->createView(),
        );

        return $this->executeDynamicMethod('render<DocumentName>Template', array('search', $this->document['templates']['list'], $parameters));
    }

    /**
     * The method that is executed when the user performs a 'show' action on a document.
     *
     * @return Response
     */
    protected function showAction()
    {
        $this->dispatch(EasyAdminMongoOdmEvents::PRE_SHOW);

        $id = $this->request->query->get('id');
        $easyadminMongoOdm = $this->request->attributes->get('easyadmin_mongo_odm');
        $document = $easyadminMongoOdm['item'];

        $fields = $this->document['show']['fields'];
        // RESTRICTED_ACTIONS $deleteForm = $this->createDeleteForm($this->document['name'], $id);

        $this->dispatch(EasyAdminMongoOdmEvents::POST_SHOW, array(
            // RESTRICTED_ACTIONS 'deleteForm' => $deleteForm,
            'fields' => $fields,
            'document' => $document,
        ));

        $parameters = array(
            'document' => $document,
            'fields' => $fields,
            // RESTRICTED_ACTIONS 'delete_form' => $deleteForm->createView(),
        );

        return $this->executeDynamicMethod('render<DocumentName>Template', array('show', $this->document['templates']['show'], $parameters));
    }

    /**
     * {@inheritdoc}
     */
    protected function isActionAllowed($actionName)
    {
        return false === in_array($actionName, $this->document['disabled_actions'], true);
    }

    /**
     * Given a method name pattern, it looks for the customized version of that
     * method (based on the document name) and executes it. If the custom method
     * does not exist, it executes the regular method.
     *
     * For example:
     *   executeDynamicMethod('create<DocumentName>Document') and the document name is 'User'
     *   if 'createUserDocument()' exists, execute it; otherwise execute 'createDocument()'
     *
     * @param string $methodNamePattern The pattern of the method name (dynamic parts are enclosed with <> angle brackets)
     * @param array  $arguments         The arguments passed to the executed method
     *
     * @return mixed
     */
    protected function executeDynamicMethod($methodNamePattern, array $arguments = array())
    {
        $methodName = str_replace('<DocumentName>', $this->document['name'], $methodNamePattern);

        if (!is_callable(array($this, $methodName))) {
            $methodName = str_replace('<DocumentName>', '', $methodNamePattern);
        }

        return call_user_func_array(array($this, $methodName), $arguments);
    }

    /**
     * Performs a database query to get all the records related to the given
     * document. It supports pagination and field sorting.
     *
     * @param string      $documentClass
     * @param int         $page
     * @param int         $maxPerPage
     * @param string|null $sortField
     * @param string|null $sortDirection
     *
     * @return Pagerfanta The paginated query results
     */
    protected function mongoOdmFindAll($documentClass, $page = 1, $maxPerPage = 15, $sortField = null, $sortDirection = null)
    {
        if (empty($sortDirection) || !in_array(strtoupper($sortDirection), array('ASC', 'DESC'))) {
            $sortDirection = 'DESC';
        }

        $queryBuilder = $this->executeDynamicMethod('createMongoOdm<DocumentName>ListQueryBuilder', array($documentClass, $sortDirection, $sortField));

        $this->dispatch(EasyAdminMongoOdmEvents::POST_LIST_QUERY_BUILDER, array(
            'query_builder' => $queryBuilder,
            'sort_field' => $sortField,
            'sort_direction' => $sortDirection,
        ));

        return $this->get('easyadmin_mongo_odm.paginator')->createMongoOdmPaginator($queryBuilder, $page, $maxPerPage);
    }

    /**
     * Performs a database query based on the search query provided by the user.
     * It supports pagination and field sorting.
     *
     * @param string      $documentClass
     * @param string      $searchQuery
     * @param array       $searchableFields
     * @param int         $page
     * @param int         $maxPerPage
     * @param string|null $sortField
     * @param string|null $sortDirection
     *
     * @return Pagerfanta The paginated query results
     */
    protected function mongoOdmFindBy($documentClass, $searchQuery, array $searchableFields, $page = 1, $maxPerPage = 15, $sortField = null, $sortDirection = null)
    {
        $queryBuilder = $this->executeDynamicMethod('createMongoOdm<DocumentName>SearchQueryBuilder', array($documentClass, $searchQuery, $searchableFields, $sortField, $sortDirection));

        $this->dispatch(EasyAdminMongoOdmEvents::POST_SEARCH_QUERY_BUILDER, array(
            'query_builder' => $queryBuilder,
            'search_query' => $searchQuery,
            'searchable_fields' => $searchableFields,
        ));

        return $this->get('easyadmin_mongo_odm.paginator')->createMongoOdmPaginator($queryBuilder, $page, $maxPerPage);
    }

    /**
     * Creates Query Builder instance for all the records.
     *
     * @param string      $documentClass
     * @param string      $sortDirection
     * @param string|null $sortField
     *
     * @return QueryBuilder The Query Builder instance
     */
    protected function createMongoOdmListQueryBuilder($documentClass, $sortDirection, $sortField = null)
    {
        return $this->get('easyadmin_mongo_odm.query_builder')->createListQueryBuilder($this->document, $sortField, $sortDirection);
    }


    /**
     * Creates Query Builder instance for search query.
     *
     * @param string      $documentClass
     * @param string      $searchQuery
     * @param array       $searchableFields
     * @param string|null $sortField
     * @param string|null $sortDirection
     *
     * @return QueryBuilder The Query Builder instance
     */
    protected function createMongoOdmSearchQueryBuilder($documentClass, $searchQuery, array $searchableFields, $sortField = null, $sortDirection = null)
    {
        return $this->get('easyadmin_mongo_odm.query_builder')->createSearchQueryBuilder($this->document, $searchQuery, $sortField, $sortDirection);
    }

    /**
     * Used to add/modify/remove parameters before passing them to the Twig template.
     * Instead of defining a render method per action (list, show, search, etc.) use
     * the $actionName argument to discriminate between actions.
     *
     * @param string $actionName   The name of the current action (list, show, new, etc.)
     * @param string $templatePath The path of the Twig template to render
     * @param array  $parameters   The parameters passed to the template
     *
     * @return Response
     */
    protected function renderTemplate($actionName, $templatePath, array $parameters = array())
    {
        return $this->render($templatePath, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    protected function getMongoOdmDoctrine(): ManagerRegistry
    {
        if (!$this->container->has('doctrine_mongodb')) {
            throw new \LogicException('The DoctrineMongoDBBundle is not registered in your application. Try running "composer require doctrine/mongodb-odm-bundle".');
        }

        return $this->container->get('doctrine_mongodb');
    }
}
