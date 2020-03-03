<?php

namespace AlterPHP\EasyAdminMongoOdmBundle\Twig;

use AlterPHP\EasyAdminMongoOdmBundle\Configuration\ConfigManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Defines the filters and functions used to render the bundle's templates.
 */
class EasyAdminMongoOdmTwigExtension extends AbstractExtension
{
    /** @var ConfigManager */
    private $configManager;
    /** @var PropertyAccessor */
    private $propertyAccessor;
    /** @var bool */
    private $debug;

    public function __construct(ConfigManager $configManager, PropertyAccessor $propertyAccessor, $debug = false)
    {
        $this->configManager = $configManager;
        $this->propertyAccessor = $propertyAccessor;
        $this->debug = $debug;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('easyadmin_mongo_odm_config', [$this, 'getBackendConfiguration']),
            new TwigFunction('easyadmin_mongo_odm_document', [$this, 'getDocumentConfiguration']),
            new TwigFunction('easyadmin_mongo_odm_action_is_enabled_for_*_view', [$this, 'isActionEnabled']),
            new TwigFunction('easyadmin_mongo_odm_action_is_enabled', [$this, 'isActionEnabled']),
            new TwigFunction('easyadmin_mongo_odm_get_action', [$this, 'getActionConfiguration']),
            new TwigFunction('easyadmin_mongo_odm_get_action_for_*_view', [$this, 'getActionConfiguration']),
            new TwigFunction('easyadmin_mongo_odm_get_actions_for_*_item', [$this, 'getActionsForItem']),
            new TwigFunction('easyadmin_mongo_odm_render_field_for_*_view', [$this, 'renderDocumentField'], ['is_safe' => ['html'], 'needs_environment' => true]),

            /*
            new TwigFunction('easyadmin_path', array($this, 'getDocumentPath')),
            */
        ];
    }

    /**
     * Returns the entire backend configuration or the value corresponding to
     * the provided key. The dots of the key are automatically transformed into
     * nested keys. Example: 'assets.css' => $config['assets']['css'].
     *
     * @param string|null $key
     *
     * @return mixed
     */
    public function getBackendConfiguration($key = null)
    {
        return $this->configManager->getBackendConfig($key);
    }

    /**
     * Returns the entire configuration of the given document.
     *
     * @param string $documentName
     *
     * @return array|null
     */
    public function getDocumentConfiguration($documentName)
    {
        return null !== $this->getBackendConfiguration('documents.'.$documentName)
            ? $this->configManager->getDocumentConfig($documentName)
            : null;
    }

    /**
     * Checks whether the given 'action' is enabled for the given 'document'.
     *
     * @param string $view
     * @param string $action
     * @param string $documentName
     *
     * @return bool
     */
    public function isActionEnabled($view, $action, $documentName)
    {
        return $this->configManager->isActionEnabled($documentName, $view, $action);
    }

    /**
     * Returns the full action configuration for the given 'document' and 'view'.
     *
     * @param string $view
     * @param string $action
     * @param string $documentName
     *
     * @return array
     */
    public function getActionConfiguration($view, $action, $documentName)
    {
        return $this->configManager->getActionConfig($documentName, $view, $action);
    }

    /**
     * Returns the actions configured for each item displayed in the given view.
     * This method is needed because some actions are displayed globally for the
     * entire view (e.g. 'new' action in 'list' view).
     *
     * @param string $view
     * @param string $documentName
     *
     * @return array
     */
    public function getActionsForItem($view, $documentName)
    {
        try {
            $documentConfig = $this->configManager->getDocumentConfig($documentName);
        } catch (\Exception $e) {
            return [];
        }

        $disabledActions = $documentConfig['disabled_actions'];
        $viewActions = $documentConfig[$view]['actions'];

        $actionsExcludedForItems = [
            'list' => ['new', 'search'],
            'edit' => [],
            'new' => [],
            'show' => [],
        ];
        $excludedActions = $actionsExcludedForItems[$view];

        return \array_filter($viewActions, function ($action) use ($excludedActions, $disabledActions) {
            return !\in_array($action['name'], $excludedActions) && !\in_array($action['name'], $disabledActions);
        });
    }

    /**
     * Renders the value stored in a property/field of the given document. This
     * function contains a lot of code protections to avoid errors when the
     * property doesn't exist or its value is not accessible. This ensures that
     * the function never generates a warning or error message when calling it.
     *
     * @param Environment $twig
     * @param string      $view          The view in which the item is being rendered
     * @param string      $documentName  The name of the document associated with the item
     * @param object      $item          The item which is being rendered
     * @param array       $fieldMetadata The metadata of the actual field being rendered
     *
     * @return string
     *
     * @throws \Exception
     */
    public function renderDocumentField(Environment $twig, $view, $documentName, $item, array $fieldMetadata)
    {
        $documentConfiguration = $this->configManager->getDocumentConfig($documentName);
        $hasCustomTemplate = 0 !== \strpos($fieldMetadata['template'], '@EasyAdminMongoOdm/');
        $templateParameters = [];

        try {
            $templateParameters = $this->getTemplateParameters($documentName, $view, $fieldMetadata, $item);

            // if the field defines a custom template, render it (no matter if the value is null or inaccessible)
            if ($hasCustomTemplate) {
                return $twig->render($fieldMetadata['template'], $templateParameters);
            }

            if (false === $templateParameters['is_accessible']) {
                return $twig->render($documentConfiguration['templates']['label_inaccessible'], $templateParameters);
            }

            if (null === $templateParameters['value']) {
                return $twig->render($documentConfiguration['templates']['label_null'], $templateParameters);
            }

            if (empty($templateParameters['value']) && \in_array($fieldMetadata['dataType'], ['image', 'file', 'array', 'simple_array'])) {
                return $twig->render($templateParameters['document_config']['templates']['label_empty'], $templateParameters);
            }

            return $twig->render($fieldMetadata['template'], $templateParameters);
        } catch (\Exception $e) {
            if ($this->debug) {
                throw $e;
            }

            return $twig->render($documentConfiguration['templates']['label_undefined'], $templateParameters);
        }
    }

    private function getTemplateParameters($documentName, $view, array $fieldMetadata, $item)
    {
        $fieldName = $fieldMetadata['property'];
        $fieldType = $fieldMetadata['dataType'];

        $parameters = [
            'backend_config' => $this->getBackendConfiguration(),
            'document_config' => $this->configManager->getDocumentConfig($documentName),
            'field_options' => $fieldMetadata,
            'item' => $item,
            'view' => $view,
        ];

        // the try..catch block is required because we can't use
        // $propertyAccessor->isReadable(), which is unavailable in Symfony 2.3
        try {
            $parameters['value'] = $this->propertyAccessor->getValue($item, $fieldName);
            $parameters['is_accessible'] = true;
        } catch (\Exception $e) {
            $parameters['value'] = null;
            $parameters['is_accessible'] = false;
        }

        /*
        if ('image' === $fieldType) {
            $parameters = $this->addImageFieldParameters($parameters);
        }

        if ('file' === $fieldType) {
            $parameters = $this->addFileFieldParameters($parameters);
        }

        if ('association' === $fieldType) {
            $parameters = $this->addAssociationFieldParameters($parameters);
        }
        */

        if (true === $fieldMetadata['virtual']) {
            // when a virtual field doesn't define it's type, consider it a string
            if (null === $parameters['field_options']['dataType']) {
                $parameters['value'] = (string) $parameters['value'];
            }
        }

        return $parameters;
    }

    /*
     * @param object|string $document
     * @param string        $action
     * @param array         $parameters
     *
     * @return string
     */
    /*public function getDocumentPath($document, $action, array $parameters = array())
    {
        return $this->easyAdminRouter->generate($document, $action, $parameters);
    }*/

    /*private function addImageFieldParameters(array $templateParameters)
    {
        // add the base path only to images that are not absolute URLs (http or https) or protocol-relative URLs (//)
        if (null !== $templateParameters['value'] && 0 === preg_match('/^(http[s]?|\/\/)/i', $templateParameters['value'])) {
            $templateParameters['value'] = isset($templateParameters['field_options']['base_path'])
                ? rtrim($templateParameters['field_options']['base_path'], '/').'/'.ltrim($templateParameters['value'], '/')
                : '/'.ltrim($templateParameters['value'], '/');
        }

        $templateParameters['uuid'] = md5($templateParameters['value']);

        return $templateParameters;
    }*/

    /*private function addFileFieldParameters(array $templateParameters)
    {
        // add the base path only to files that are not absolute URLs (http or https) or protocol-relative URLs (//)
        if (null !== $templateParameters['value'] && 0 === preg_match('/^(http[s]?|\/\/)/i', $templateParameters['value'])) {
            $templateParameters['value'] = isset($templateParameters['field_options']['base_path'])
                ? rtrim($templateParameters['field_options']['base_path'], '/').'/'.ltrim($templateParameters['value'], '/')
                : '/'.ltrim($templateParameters['value'], '/');
        }

        $templateParameters['filename'] = isset($templateParameters['field_options']['filename']) ? $templateParameters['field_options']['filename'] : basename($templateParameters['value']);

        return $templateParameters;
    }*/

    /*private function addAssociationFieldParameters(array $templateParameters)
    {
        $targetDocumentConfig = $this->configManager->getDocumentConfigByClass($templateParameters['field_options']['targetDocument']);
        // the associated document is not managed by EasyAdmin
        if (null === $targetDocumentConfig) {
            return $templateParameters;
        }

        $isShowActionAllowed = !in_array('show', $targetDocumentConfig['disabled_actions']);

        if ($templateParameters['field_options']['associationType'] & ClassMetadata::TO_ONE) {
            // the try..catch block is required because we can't use
            // $propertyAccessor->isReadable(), which is unavailable in Symfony 2.3
            try {
                $primaryKeyValue = $this->propertyAccessor->getValue($templateParameters['value'], $targetDocumentConfig['primary_key_field_name']);
            } catch (\Exception $e) {
                $primaryKeyValue = null;
            }

            // get the string representation of the associated *-to-one document
            if (method_exists($templateParameters['value'], '__toString')) {
                $templateParameters['value'] = (string) $templateParameters['value'];
            } elseif (null !== $primaryKeyValue) {
                $templateParameters['value'] = sprintf('%s #%s', $targetDocumentConfig['name'], $primaryKeyValue);
            } else {
                $templateParameters['value'] = null;
            }

            // if the associated document is managed by EasyAdmin, and the "show"
            // action is enabled for the associated document, display a link to it
            if (null !== $targetDocumentConfig && null !== $primaryKeyValue && $isShowActionAllowed) {
                $templateParameters['link_parameters'] = array(
                    'action' => 'show',
                    'document' => $targetDocumentConfig['name'],
                    // casting to string is needed because documents can use objects as primary keys
                    'id' => (string) $primaryKeyValue,
                );
            }
        }

        if ($templateParameters['field_options']['associationType'] & ClassMetadata::TO_MANY) {
            // if the associated document is managed by EasyAdmin, and the "show"
            // action is enabled for the associated document, display a link to it
            if (null !== $targetDocumentConfig && $isShowActionAllowed) {
                $templateParameters['link_parameters'] = array(
                    'action' => 'show',
                    'document' => $targetDocumentConfig['name'],
                    'primary_key_name' => $targetDocumentConfig['primary_key_field_name'],
                );
            }
        }

        return $templateParameters;
    }*/
}
