<?php

namespace AlterPHP\EasyAdminMongoOdmBundle\Configuration;

use EasyCorp\Bundle\EasyAdminBundle\Configuration\ConfigPassInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Normalizes the different configuration formats available for documents, views,
 * actions and properties.
 */
class NormalizerConfigPass implements ConfigPassInterface
{
    // USE_MAIN_CONFIG
    private $easyAdminBackendConfig;
    private $defaultViewConfig = [
        'list' => [
            'fields' => [],
        ],
        'search' => [
            'fields' => [],
        ],
        'show' => [
            'fields' => [],
        ],
        /* RESTRICTED_ACTION
        'form' => array(
            'fields' => array(),
            'form_options' => array(),
        ),
        'edit' => array(
            'fields' => array(),
            'form_options' => array(),
        ),
        'new' => array(
            'fields' => array(),
            'form_options' => array(),
        ),
        */
    ];

    /** @var ContainerInterface */
    private $container;

    public function __construct(ContainerInterface $container, array $easyAdminBackendConfig)
    {
        $this->container = $container;
        $this->easyAdminBackendConfig = $easyAdminBackendConfig;
    }

    public function process(array $backendConfig)
    {
        $backendConfig = $this->normalizeDocumentConfig($backendConfig);
        $backendConfig = $this->normalizeViewConfig($backendConfig);
        $backendConfig = $this->normalizePropertyConfig($backendConfig);
        // RESTRICTED_ACTIONS $backendConfig = $this->normalizeFormDesignConfig($backendConfig);
        $backendConfig = $this->normalizeActionConfig($backendConfig);
        // RESTRICTED_ACTIONS $backendConfig = $this->normalizeFormConfig($backendConfig);
        $backendConfig = $this->normalizeControllerConfig($backendConfig);
        $backendConfig = $this->normalizeTranslationConfig($backendConfig);

        return $backendConfig;
    }

    /**
     * By default the document name is used as its label (showed in buttons, the
     * main menu, etc.) unless the document config defines the 'label' option:.
     *
     * easy_admin:
     *     documents:
     *         User:
     *             class: AppBundle\Document\User
     *             label: 'Clients'
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function normalizeDocumentConfig(array $backendConfig)
    {
        foreach ($backendConfig['documents'] as $documentName => $documentConfig) {
            if (!isset($documentConfig['label'])) {
                $backendConfig['documents'][$documentName]['label'] = $documentName;
            }
        }

        return $backendConfig;
    }

    /**
     * Normalizes the view configuration when some of them doesn't define any
     * configuration.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function normalizeViewConfig(array $backendConfig)
    {
        foreach ($backendConfig['documents'] as $documentName => $documentConfig) {
            foreach (\array_keys($this->defaultViewConfig) as $view) {
                $documentConfig[$view] = \array_replace_recursive(
                    $this->defaultViewConfig[$view],
                    $documentConfig[$view] ?? []
                );
            }

            $backendConfig['documents'][$documentName] = $documentConfig;
        }

        return $backendConfig;
    }

    /**
     * Fields can be defined using two different formats:.
     *
     * # Config format #1: simple configuration
     * easy_admin:
     *     Client:
     *         # ...
     *         list:
     *             fields: ['id', 'name', 'email']
     *
     * # Config format #2: extended configuration
     * easy_admin:
     *     Client:
     *         # ...
     *         list:
     *             fields: ['id', 'name', { property: 'email', label: 'Contact' }]
     *
     * This method processes both formats to produce a common form field configuration
     * format used in the rest of the application.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function normalizePropertyConfig(array $backendConfig)
    {
        foreach ($backendConfig['documents'] as $documentName => $documentConfig) {
            $designElementIndex = 0;
            foreach (\array_keys($this->defaultViewConfig) as $view) {
                $fields = [];
                foreach ($documentConfig[$view]['fields'] as $i => $field) {
                    if (!\is_string($field) && !\is_array($field)) {
                        throw new \RuntimeException(\sprintf('The values of the "fields" option for the "%s" view of the "%s" document can only be strings or arrays.', $view, $documentConfig['class']));
                    }

                    if (\is_string($field)) {
                        // Config format #1: field is just a string representing the document property
                        $fieldConfig = ['property' => $field];
                    } else {
                        // Config format #1: field is an array that defines one or more
                        // options. Check that either 'property' or 'type' option is set
                        if (!\array_key_exists('property', $field) && !\array_key_exists('type', $field)) {
                            throw new \RuntimeException(\sprintf('One of the values of the "fields" option for the "%s" view of the "%s" document does not define neither of the mandatory options ("property" or "type").', $view, $documentConfig['class']));
                        }

                        $fieldConfig = $field;
                    }

                    // for 'image' type fields, if the document defines an 'image_base_path'
                    // option, but the field does not, use the value defined by the document
                    if (isset($fieldConfig['type']) && 'image' === $fieldConfig['type']) {
                        if (!isset($fieldConfig['base_path']) && isset($documentConfig['image_base_path'])) {
                            $fieldConfig['base_path'] = $documentConfig['image_base_path'];
                        }
                    }

                    // for 'file' type fields, if the document defines an 'file_base_path'
                    // option, but the field does not, use the value defined by the document
                    if (isset($fieldConfig['type']) && 'file' === $fieldConfig['type']) {
                        if (!isset($fieldConfig['base_path']) && isset($documentConfig['file_base_path'])) {
                            $fieldConfig['base_path'] = $documentConfig['file_base_path'];
                        }
                    }

                    // fields that don't define the 'property' name are special form design elements
                    $fieldName = $fieldConfig['property'] ?? '_easyadmin_form_design_element_'.$designElementIndex;
                    $fields[$fieldName] = $fieldConfig;
                    ++$designElementIndex;
                }

                $backendConfig['documents'][$documentName][$view]['fields'] = $fields;
            }
        }

        return $backendConfig;
    }

    private function normalizeActionConfig(array $backendConfig)
    {
        $views = \array_diff(\array_keys($this->defaultViewConfig), ['search']);

        foreach ($backendConfig['documents'] as $documentName => $documentConfig) {
            foreach ($views as $view) {
                if (!isset($documentConfig[$view]['actions'])) {
                    $backendConfig['documents'][$documentName][$view]['actions'] = [];
                }

                if (!\is_array($backendConfig['documents'][$documentName][$view]['actions'])) {
                    throw new \InvalidArgumentException(\sprintf('The "actions" configuration for the "%s" view of the "%s" document must be an array (a string was provided).', $view, $documentName));
                }
            }
        }

        return $backendConfig;
    }

    /**
     * It processes the optional 'controller' config option to check if the
     * given controller exists (it doesn't matter if it's a normal controller
     * or if it's defined as a service).
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function normalizeControllerConfig(array $backendConfig)
    {
        foreach ($backendConfig['documents'] as $documentName => $documentConfig) {
            if (isset($documentConfig['controller'])) {
                $controller = \trim($documentConfig['controller']);

                if (!$this->container->has($controller) && !\class_exists($controller)) {
                    throw new \InvalidArgumentException(\sprintf('The "%s" value defined in the "controller" option of the "%s" document is not a valid controller. For a regular controller, set its FQCN as the value; for a controller defined as service, set its service name as the value.', $controller, $documentName));
                }

                $backendConfig['documents'][$documentName]['controller'] = $controller;
            }
        }

        return $backendConfig;
    }

    private function normalizeTranslationConfig(array $backendConfig)
    {
        foreach ($backendConfig['documents'] as $documentName => $documentConfig) {
            if (!isset($documentConfig['translation_domain'])) {
                $documentConfig['translation_domain'] = $this->easyAdminBackendConfig['translation_domain'];
            }

            if ('' === $documentConfig['translation_domain']) {
                throw new \InvalidArgumentException(\sprintf('The value defined in the "translation_domain" option of the "%s" document is not a valid translation domain name (use false to disable translations).', $documentName));
            }

            $backendConfig['documents'][$documentName] = $documentConfig;
        }

        return $backendConfig;
    }

    /*
     * Normalizes the configuration of the special elements that forms may include
     * to create advanced designs (such as dividers and fieldsets).
     *
     * @param array $backendConfig
     *
     * @return array
     */
    /* RESTRICTED_ACTIONS
    private function normalizeFormDesignConfig(array $backendConfig)
    {
        // edge case: if the first 'group' type is not the first form field,
        // all the previous form fields are "ungrouped". To avoid design issues,
        // insert an empty 'group' type (no label, no icon) as the first form element.
        foreach ($backendConfig['documents'] as $documentName => $documentConfig) {
            foreach (array('form', 'edit', 'new') as $view) {
                $fieldNumber = 0;

                foreach ($documentConfig[$view]['fields'] as $fieldName => $fieldConfig) {
                    ++$fieldNumber;
                    $isFormDesignElement = !isset($fieldConfig['property']) && isset($fieldConfig['type']);

                    if ($isFormDesignElement && 'tab' === $fieldConfig['type']) {
                        if ($fieldNumber > 1) {
                            $backendConfig['documents'][$documentName][$view]['fields'] = array_merge(
                                array('_easyadmin_form_design_element_forced_first_tab' => array('type' => 'tab')),
                                $backendConfig['documents'][$documentName][$view]['fields']
                            );
                        }
                        break;
                    }
                }

                $fieldNumber = 0;
                $previousTabFieldNumber = -1;
                $isTheFirstGroupElement = true;

                foreach ($documentConfig[$view]['fields'] as $fieldName => $fieldConfig) {
                    ++$fieldNumber;
                    $isFormDesignElement = !isset($fieldConfig['property']) && isset($fieldConfig['type']);

                    if ($isFormDesignElement && 'tab' === $fieldConfig['type']) {
                        $previousTabFieldNumber = $fieldNumber;
                        $isTheFirstGroupElement = true;
                    } elseif ($isFormDesignElement && 'group' === $fieldConfig['type']) {
                        if ($isTheFirstGroupElement && -1 === $previousTabFieldNumber && $fieldNumber > 1) {
                            // if no tab is used, insert the group at the beginning of the array
                            $backendConfig['documents'][$documentName][$view]['fields'] = array_merge(
                                array('_easyadmin_form_design_element_forced_first_group' => array('type' => 'group')),
                                $backendConfig['documents'][$documentName][$view]['fields']
                            );
                            break;
                        } elseif ($isTheFirstGroupElement && $previousTabFieldNumber >= 0 && $fieldNumber > $previousTabFieldNumber + 1) {
                            // if tabs are used, we insert the group after the previous tab field into the array
                            $backendConfig['documents'][$documentName][$view]['fields'] = array_merge(
                                array_slice($backendConfig['documents'][$documentName][$view]['fields'], 0, $previousTabFieldNumber, true),
                                array('_easyadmin_form_design_element_forced_group_'.$fieldNumber => array('type' => 'group')),
                                array_slice($backendConfig['documents'][$documentName][$view]['fields'], $previousTabFieldNumber, null, true)
                            );
                        }

                        $isTheFirstGroupElement = false;
                    }
                }
            }
        }

        foreach ($backendConfig['documents'] as $documentName => $documentConfig) {
            foreach (array('form', 'edit', 'new') as $view) {
                foreach ($documentConfig[$view]['fields'] as $fieldName => $fieldConfig) {
                    // this is a form design element instead of a regular property
                    $isFormDesignElement = !isset($fieldConfig['property']) && isset($fieldConfig['type']);
                    if ($isFormDesignElement && in_array($fieldConfig['type'], array('divider', 'group', 'section', 'tab'))) {
                        // assign them a property name to add them later as unmapped form fields
                        $fieldConfig['property'] = $fieldName;

                        if ('tab' === $fieldConfig['type'] && empty($fieldConfig['id'])) {
                            // ensures unique IDs like '_easyadmin_form_design_element_0'
                            $fieldConfig['id'] = $fieldConfig['property'];
                        }

                        // transform the form type shortcuts into the real form type short names
                        $fieldConfig['type'] = 'easyadmin_'.$fieldConfig['type'];
                    }

                    $backendConfig['documents'][$documentName][$view]['fields'][$fieldName] = $fieldConfig;
                }
            }
        }

        return $backendConfig;
    }*/

    /*
     * Process the configuration of the 'form' view (if any) to complete the
     * configuration of the 'edit' and 'new' views.
     *
     * @param array $backendConfig [description]
     *
     * @return array
     */
    /* RESTRICTED_ACTIONS
    private function normalizeFormConfig(array $backendConfig)
    {
        foreach ($backendConfig['documents'] as $documentName => $documentConfig) {
            if (isset($documentConfig['form'])) {
                $documentConfig['new'] = isset($documentConfig['new']) ? $this->mergeFormConfig($documentConfig['form'], $documentConfig['new']) : $documentConfig['form'];
                $documentConfig['edit'] = isset($documentConfig['edit']) ? $this->mergeFormConfig($documentConfig['form'], $documentConfig['edit']) : $documentConfig['form'];
            }

            $backendConfig['documents'][$documentName] = $documentConfig;
        }

        return $backendConfig;
    }*/

    /*
     * Merges the form configuration recursively from the 'form' view to the
     * 'edit' and 'new' views. It processes the configuration of the form fields
     * in a special way to keep all their configuration and allow overriding and
     * removing of fields.
     *
     * @param array $parentConfig The config of the 'form' view
     * @param array $childConfig  The config of the 'edit' and 'new' views
     *
     * @return array
     */
    /* RESTRICTED_ACTIONS
    private function mergeFormConfig(array $parentConfig, array $childConfig)
    {
        // save the fields config for later processing
        $parentFields = isset($parentConfig['fields']) ? $parentConfig['fields'] : array();
        $childFields = isset($childConfig['fields']) ? $childConfig['fields'] : array();
        $removedFieldNames = $this->getRemovedFieldNames($childFields);

        // first, perform a recursive replace to merge both configs
        $mergedConfig = array_replace_recursive($parentConfig, $childConfig);

        // merge the config of each field individually
        $mergedFields = array();
        foreach ($parentFields as $parentFieldName => $parentFieldConfig) {
            if (isset($parentFieldConfig['property']) && in_array($parentFieldConfig['property'], $removedFieldNames)) {
                continue;
            }

            if (!isset($parentFieldConfig['property'])) {
                // this isn't a regular form field but a special design element (group, section, divider); add it
                $mergedFields[$parentFieldName] = $parentFieldConfig;
                continue;
            }

            $childFieldConfig = $this->findFieldConfigByProperty($childFields, $parentFieldConfig['property']) ?: array();
            $mergedFields[$parentFieldName] = array_replace_recursive($parentFieldConfig, $childFieldConfig);
        }

        // add back the fields that are defined in child config but not in parent config
        foreach ($childFields as $childFieldName => $childFieldConfig) {
            $isFormDesignElement = !isset($childFieldConfig['property']);
            $isNotRemovedField = isset($childFieldConfig['property']) && '-' !== substr($childFieldConfig['property'], 0, 1);
            $isNotAlreadyIncluded = isset($childFieldConfig['property']) && !in_array($childFieldConfig['property'], array_keys($mergedFields));

            if ($isFormDesignElement || ($isNotRemovedField && $isNotAlreadyIncluded)) {
                $mergedFields[$childFieldName] = $childFieldConfig;
            }
        }

        // finally, copy the processed field config into the merged config
        $mergedConfig['fields'] = $mergedFields;

        return $mergedConfig;
    }*/

    /*
     * The 'edit' and 'new' views can remove fields defined in the 'form' view
     * by defining fields with a '-' dash at the beginning of its name (e.g.
     * { property: '-name' } to remove the 'name' property).
     *
     * @param array $fieldsConfig
     *
     * @return array
     */
    /* RESTRICTED_ACTIONS
    private function getRemovedFieldNames(array $fieldsConfig)
    {
        $removedFieldNames = array();
        foreach ($fieldsConfig as $fieldConfig) {
            if (isset($fieldConfig['property']) && '-' === substr($fieldConfig['property'], 0, 1)) {
                $removedFieldNames[] = substr($fieldConfig['property'], 1);
            }
        }

        return $removedFieldNames;
    }*/

    /* RESTRICTED_ACTIONS
    private function findFieldConfigByProperty(array $fieldsConfig, $propertyName)
    {
        foreach ($fieldsConfig as $fieldConfig) {
            if (isset($fieldConfig['property']) && $propertyName === $fieldConfig['property']) {
                return $fieldConfig;
            }
        }

        return null;
    }*/
}
