<?php

namespace AlterPHP\EasyAdminMongoOdmBundle\Configuration;

use EasyCorp\Bundle\EasyAdminBundle\Configuration\ConfigPassInterface;

/**
 * Initializes the configuration for all the views of each document, which is
 * needed when some document relies on the default configuration for some view.
 */
class ViewConfigPass implements ConfigPassInterface
{
    private $easyAdminBackendConfig;
    private $views = array('list', 'search', 'show'); // RESTRICTED_ACTIONS array('edit', 'list', 'new', 'search', 'show');

    public function __construct(array $easyAdminBackendConfig)
    {
        $this->easyAdminBackendConfig = $easyAdminBackendConfig;
    }

    public function process(array $backendConfig)
    {
        $backendConfig = $this->processViewConfig($backendConfig);
        $backendConfig = $this->processDefaultFieldsConfig($backendConfig);
        $backendConfig = $this->processFieldConfig($backendConfig);
        $backendConfig = $this->processPageTitleConfig($backendConfig);
        $backendConfig = $this->processMaxResultsConfig($backendConfig);
        $backendConfig = $this->processSortingConfig($backendConfig);

        return $backendConfig;
    }

    private function processViewConfig(array $backendConfig)
    {
        // process the 'help' message that each view can define to display it under the page title
        foreach ($backendConfig['documents'] as $documentName => $documentConfig) {
            foreach ($this->views as $view) {
                // isset() cannot be used because the value can be 'null' (used to remove the inherited help message)
                if (array_key_exists('help', $backendConfig['documents'][$documentName][$view])) {
                    continue;
                }

                $backendConfig['documents'][$documentName][$view]['help'] = array_key_exists('help', $documentConfig) ? $documentConfig['help'] : null;
            }
        }

        return $backendConfig;
    }

    /**
     * This method takes care of the views that don't define their fields. In
     * those cases, we just use the $documentConfig['properties'] information and
     * we filter some fields to improve the user experience for default config.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function processDefaultFieldsConfig(array $backendConfig)
    {
        foreach ($backendConfig['documents'] as $documentName => $documentConfig) {
            foreach ($this->views as $view) {
                if (0 === count($documentConfig[$view]['fields'])) {
                    $fieldsConfig = $this->filterFieldList(
                        $documentConfig['properties'],
                        $this->getExcludedFieldNames($view, $documentConfig),
                        $this->getExcludedFieldTypes($view),
                        $this->getMaxNumberFields($view)
                    );

                    foreach ($fieldsConfig as $fieldName => $fieldConfig) {
                        // ORIGINAL if (null === $fieldsConfig[$fieldName]['format']) {
                        // instead of implementing PropertyConfigPass
                        if (isset($fieldsConfig[$fieldName]['format'])) {
                            $fieldsConfig[$fieldName]['format'] = $this->getFieldFormat($fieldConfig['type'], $backendConfig);
                        }
                    }

                    $backendConfig['documents'][$documentName][$view]['fields'] = $fieldsConfig;
                }
            }
        }

        return $backendConfig;
    }

    /**
     * This methods makes some minor tweaks in fields configuration to improve
     * the user experience.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function processFieldConfig(array $backendConfig)
    {
        foreach ($backendConfig['documents'] as $documentName => $documentConfig) {
            foreach ($this->views as $view) {
                foreach ($documentConfig[$view]['fields'] as $fieldName => $fieldConfig) {
                    // special case: if the field is called 'id' and doesn't define a custom
                    // label, use 'ID' as label. This improves the readability of the label
                    // of this important field, which is usually related to the primary key
                    if ('id' === $fieldConfig['fieldName'] && !isset($fieldConfig['label'])) {
                        $fieldConfig['label'] = 'ID';
                    }

                    $backendConfig['documents'][$documentName][$view]['fields'][$fieldName] = $fieldConfig;
                }
            }
        }

        return $backendConfig;
    }

    /**
     * This method resolves the page title inheritance when some global view
     * (list, edit, etc.) defines a global title for all documents that can be
     * overridden individually by each document.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function processPageTitleConfig(array $backendConfig)
    {
        foreach ($backendConfig['documents'] as $documentName => $documentConfig) {
            foreach ($this->views as $view) {
                if (!isset($documentConfig[$view]['title']) && isset($this->easyAdminBackendConfig[$view]['title'])) {
                    $backendConfig['documents'][$documentName][$view]['title'] = $this->easyAdminBackendConfig[$view]['title'];
                }
            }
        }

        return $backendConfig;
    }

    /**
     * This method resolves the 'max_results' inheritance when some global view
     * (list, show, etc.) defines a global value for all documents that can be
     * overridden individually by each document.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function processMaxResultsConfig(array $backendConfig)
    {
        foreach ($backendConfig['documents'] as $documentName => $documentConfig) {
            foreach (array('list', 'search', 'show') as $view) {
                if (!isset($documentConfig[$view]['max_results']) && isset($this->easyAdminBackendConfig[$view]['max_results'])) {
                    $backendConfig['documents'][$documentName][$view]['max_results'] = $this->easyAdminBackendConfig[$view]['max_results'];
                }
            }
        }

        return $backendConfig;
    }

    /**
     * This method processes the optional 'sort' config that the 'list' and
     * 'search' views can define to override the default (id, DESC) sorting
     * applied to their contents.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function processSortingConfig(array $backendConfig)
    {
        foreach ($backendConfig['documents'] as $documentName => $documentConfig) {
            foreach (array('list', 'search') as $view) {
                if (!isset($documentConfig[$view]['sort'])) {
                    continue;
                }

                $sortConfig = $documentConfig[$view]['sort'];
                if (!is_string($sortConfig) && !is_array($sortConfig)) {
                    throw new \InvalidArgumentException(sprintf('The "sort" option of the "%s" view of the "%s" document contains an invalid value (it can only be a string or an array).', $view, $documentName));
                }

                if (is_string($sortConfig)) {
                    $sortConfig = array('field' => $sortConfig, 'direction' => 'DESC');
                } else {
                    $sortConfig = array('field' => $sortConfig[0], 'direction' => strtoupper($sortConfig[1]));
                }

                if (!in_array($sortConfig['direction'], array('ASC', 'DESC'))) {
                    throw new \InvalidArgumentException(sprintf('If defined, the second value of the "sort" option of the "%s" view of the "%s" document can only be "ASC" or "DESC".', $view, $documentName));
                }

                $isSortedByDoctrineAssociation = false !== strpos($sortConfig['field'], '.');
                if (!$isSortedByDoctrineAssociation && (isset($documentConfig[$view]['fields'][$sortConfig['field']]) && true === $documentConfig[$view]['fields'][$sortConfig['field']]['virtual'])) {
                    throw new \InvalidArgumentException(sprintf('The "%s" field cannot be used in the "sort" option of the "%s" view of the "%s" document because it\'s a virtual property that is not persisted in the database.', $sortConfig['field'], $view, $documentName));
                }

                // sort can be defined using simple properties (sort: author) or association properties (sort: author.name)
                if (substr_count($sortConfig['field'], '.') > 1) {
                    throw new \InvalidArgumentException(sprintf('The "%s" value cannot be used as the "sort" option in the "%s" view of the "%s" document because it defines multiple sorting levels (e.g. "aaa.bbb.ccc") but only up to one level is supported (e.g. "aaa.bbb").', $sortConfig['field'], $view, $documentName));
                }

                // sort field can be a Doctrine association (sort: author.name) instead of a simple property
                $sortFieldParts = explode('.', $sortConfig['field']);
                $sortFieldProperty = $sortFieldParts[0];

                if (!array_key_exists($sortFieldProperty, $documentConfig['properties']) && !isset($documentConfig[$view]['fields'][$sortFieldProperty])) {
                    throw new \InvalidArgumentException(sprintf('The "%s" field used in the "sort" option of the "%s" view of the "%s" document does not exist neither as a property of that document nor as a virtual field of that view.', $sortFieldProperty, $view, $documentName));
                }

                $backendConfig['documents'][$documentName][$view]['sort'] = $sortConfig;
            }
        }

        return $backendConfig;
    }

    /**
     * Returns the date/time/datetime/number format for the given field
     * according to its type and the default formats defined for the backend.
     *
     * @param string $fieldType
     * @param array  $backendConfig
     *
     * @return string The format that should be applied to the field value
     */
    private function getFieldFormat($fieldType, array $backendConfig)
    {
        if (in_array($fieldType, array('date', 'date_immutable', 'time', 'time_immutable', 'datetime', 'datetime_immutable', 'datetimetz'))) {
            // make 'datetimetz' use the same format as 'datetime'
            $fieldType = ('datetimetz' === $fieldType) ? 'datetime' : $fieldType;
            $fieldType = ('_immutable' === substr($fieldType, -10)) ? substr($fieldType, 0, -10) : $fieldType;

            return $this->easyAdminBackendConfig['formats'][$fieldType];
        }

        if (in_array($fieldType, array('bigint', 'integer', 'smallint', 'decimal', 'float'))) {
            return isset($this->easyAdminBackendConfig['formats']['number']) ? $this->easyAdminBackendConfig['formats']['number'] : null;
        }
    }

    /**
     * Returns the list of excluded field names for the given view.
     *
     * @param string $view
     * @param array  $documentConfig
     *
     * @return array
     */
    private function getExcludedFieldNames($view, array $documentConfig)
    {
        $excludedFieldNames = array(
            'edit' => array($documentConfig['primary_key_field_name']),
            'list' => array('password', 'salt', 'slug', 'updatedAt', 'uuid'),
            'new' => array($documentConfig['primary_key_field_name']),
            'search' => array('password', 'salt'),
            'show' => array(),
        );

        return isset($excludedFieldNames[$view]) ? $excludedFieldNames[$view] : array();
    }

    /**
     * Returns the list of excluded field types for the given view.
     *
     * @param string $view
     *
     * @return array
     */
    private function getExcludedFieldTypes($view)
    {
        $excludedFieldTypes = array(
            'edit' => array('binary', 'blob', 'json_array', 'json', 'object'),
            'list' => array('array', 'binary', 'blob', 'guid', 'json_array', 'json', 'object', 'simple_array', 'text'),
            'new' => array('binary', 'blob', 'json_array', 'json', 'object'),
            'search' => array('association', 'binary', 'boolean', 'blob', 'date', 'date_immutable', 'datetime', 'datetime_immutable', 'datetimetz', 'time', 'time_immutable', 'object'),
            'show' => array(),
        );

        return isset($excludedFieldTypes[$view]) ? $excludedFieldTypes[$view] : array();
    }

    /**
     * Returns the maximum number of fields to display be default for the
     * given view.
     *
     * @param string $view
     *
     * @return int
     */
    private function getMaxNumberFields($view)
    {
        $maxNumberFields = array(
            'list' => 7,
        );

        return isset($maxNumberFields[$view]) ? $maxNumberFields[$view] : PHP_INT_MAX;
    }

    /**
     * Filters a list of fields excluding the given list of field names and field types.
     *
     * @param array    $fields
     * @param string[] $excludedFieldNames
     * @param string[] $excludedFieldTypes
     * @param int      $maxNumFields
     *
     * @return array The filtered list of fields
     */
    private function filterFieldList(array $fields, array $excludedFieldNames, array $excludedFieldTypes, $maxNumFields)
    {
        $filteredFields = array();

        foreach ($fields as $name => $metadata) {
            if (!in_array($name, $excludedFieldNames) && !in_array($metadata['type'], $excludedFieldTypes)) {
                $filteredFields[$name] = $fields[$name];
            }
        }

        if (count($filteredFields) > $maxNumFields) {
            $filteredFields = array_slice($filteredFields, 0, $maxNumFields, true);
        }

        return $filteredFields;
    }
}
