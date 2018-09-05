<?php

namespace AlterPHP\EasyAdminMongoOdmBundle\Controller;

use AlterPHP\EasyAdminMongoOdmBundle\Event\EasyAdminMongoOdmEvents;
use AlterPHP\EasyAdminMongoOdmBundle\Exception\ForbiddenActionException;
use AlterPHP\EasyAdminMongoOdmBundle\Exception\NoDocumentsConfiguredException;
use AlterPHP\EasyAdminMongoOdmBundle\Exception\UndefinedDocumentException;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends Controller
{
    /** @var array The full configuration of the entire backend */
    protected $config;
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

        $this->config = $this->get('easyadmin_mongo_odm.config.manager')->getBackendConfig();

        if (0 === count($this->config['documents'])) {
            throw new NoDocumentsConfiguredException();
        }

        // this condition happens when accessing the backend homepage and before
        // redirecting to the default page set as the homepage
        if (null === $documentName = $request->query->get('document')) {
            return;
        }

        if (!array_key_exists($documentName, $this->config['documents'])) {
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

        $this->dm = $this->getDoctrine()->getManagerForClass($this->document['class']);
        $this->request = $request;

        $this->dispatch(EasyAdminMongoOdmEvents::POST_INITIALIZE);
    }

    protected function dispatch($eventName, array $arguments = array())
    {
        $arguments = array_replace(array(
            'config' => $this->config,
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
        $paginator = $this->findAll($this->document['class'], $this->request->query->get('page', 1), $this->document['list']['max_results'], $this->request->query->get('sortField'), $this->request->query->get('sortDirection'), $this->document['list']['dql_filter']);

        $this->dispatch(EasyAdminMongoOdmEvents::POST_LIST, array('paginator' => $paginator));

        $parameters = array(
            'paginator' => $paginator,
            'fields' => $fields,
            // RESTRICTED_ACTIONS 'delete_form_template' => $this->createDeleteForm($this->document['name'], '__id__')->createView(),
        );

        return $this->executeDynamicMethod('render<DocumentName>Template', array('list', $this->document['templates']['list'], $parameters));
    }

    /**
     * Given a method name pattern, it looks for the customized version of that
     * method (based on the document name) and executes it. If the custom method
     * does not exist, it executes the regular method.
     *
     * For example:
     *   executeDynamicMethod('create<DocumentName>Entity') and the document name is 'User'
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
     * Utility method that checks if the given action is allowed for
     * the current document.
     *
     * @param string $actionName
     *
     * @return bool
     */
    protected function isActionAllowed($actionName)
    {
        // autocomplete and embeddedList action are mapped to list action for access permissions
        if (in_array($actionName, ['autocomplete', 'embeddedList'])) {
            $actionName = 'list';
        }

        // Get item for edit/show or custom actions => security voters may apply
        $easyadmin = $this->request->attributes->get('easyadmin_mongo_odm');
        $subject = $easyadmin['item'] ?? null;
        $this->get('alterphp.easyadmin_extension.admin_authorization_checker')->checksUserAccess(
            $this->document, $actionName, $subject
        );

        return false === in_array($actionName, $this->document['disabled_actions'], true);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDoctrine(): ManagerRegistry
    {
        if (!$this->container->has('doctrine_mongodb')) {
            throw new \LogicException('The DoctrineMongoDBBundle is not registered in your application. Try running "composer require doctrine/mongodb-odm-bundle".');
        }

        return $this->container->get('doctrine_mongodb');
    }
}
