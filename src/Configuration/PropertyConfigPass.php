<?php

namespace AlterPHP\EasyAdminMongoOdmBundle\Configuration;

use EasyCorp\Bundle\EasyAdminBundle\Configuration\ConfigPassInterface;
use EasyCorp\Bundle\EasyAdminBundle\Form\Util\LegacyFormHelper;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;

/**
 * Processes the document fields to complete their configuration and to treat
 * some fields in a special way.
 */
class PropertyConfigPass implements ConfigPassInterface
{
    // USE_MAIN_CONFIG
    private $easyAdminBackendConfig;
    private $views = array('list', 'search', 'show'); // RESTRICTED_ACTIONS array('edit', 'list', 'new', 'search', 'show');
    private $defaultDocumentFieldConfig = array(
        // CSS class or classes applied to form field or list/show property
        'css_class' => '',
        // date/time/datetime/number format applied to form field value
        'format' => null,
        // form field help message
        'help' => null,
        // form field label (if 'null', autogenerate it)
        'label' => null,
        // its value matches the value of 'dataType' for list/show and the value of 'fieldType' for new/edit
        'type' => null,
        // Symfony form field type (text, date, number, choice, ...) used to display the field
        'fieldType' => null,
        // Data type (text, date, integer, boolean, ...) of the Doctrine property associated with the field
        'dataType' => null,
        // is a virtual field or a real Doctrine document property?
        'virtual' => false,
        // listings can be sorted according to the values of this field
        'sortable' => false, // SORT_INDEX_ONLY true,
        // the path of the template used to render the field in 'show' and 'list' views
        'template' => null,
        // the options passed to the Symfony Form type used to render the form field
        'type_options' => array(),
        // the name of the group where this form field is displayed (used only for complex form layouts)
        'form_group' => null,
    );

    private $defaultVirtualFieldMetadata = array(
        'columnName' => 'virtual',
        'fieldName' => 'virtual',
        'id' => false,
        'length' => null,
        'nullable' => false,
        'precision' => 0,
        'scale' => 0,
        'sortable' => false,
        'type' => 'text',
        'type_options' => array(
            'required' => false,
        ),
        'unique' => false,
        'virtual' => true,
    );

    private $formRegistry;

    public function __construct(FormRegistryInterface $formRegistry, array $easyAdminBackendConfig)
    {
        $this->formRegistry = $formRegistry;
        $this->easyAdminBackendConfig = $easyAdminBackendConfig;
    }

    public function process(array $backendConfig)
    {
        $backendConfig = $this->processMetadataConfig($backendConfig);
        $backendConfig = $this->processFieldConfig($backendConfig);

        return $backendConfig;
    }

    /**
     * $documentConfig['properties'] stores the raw metadata provided by Doctrine.
     * This method adds some other options needed for EasyAdmin backends. This is
     * required because $documentConfig['properties'] will be used as the fields of
     * the views that don't define their fields.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function processMetadataConfig(array $backendConfig)
    {
        foreach ($backendConfig['documents'] as $documentName => $documentConfig) {
            $properties = array();
            foreach ($documentConfig['properties'] as $propertyName => $propertyMetadata) {
                $typeGuess = $this->getFormTypeGuessOfProperty($documentConfig['class'], $propertyName);
                $requiredGuess = $this->getFormRequiredGuessOfProperty($documentConfig['class'], $propertyName);

                $guessedType = null !== $typeGuess
                    ? LegacyFormHelper::getShortType($typeGuess->getType())
                    : $propertyMetadata['type'];

                $guessedTypeOptions = null !== $typeGuess
                    ? $typeGuess->getOptions()
                    : array();

                if (null !== $requiredGuess) {
                    $guessedTypeOptions['required'] = $requiredGuess->getValue();
                }

                $properties[$propertyName] = array_replace(
                    $this->defaultDocumentFieldConfig,
                    $propertyMetadata,
                    array(
                        'property' => $propertyName,
                        'dataType' => $propertyMetadata['type'],
                        'fieldType' => $guessedType,
                        'type_options' => $guessedTypeOptions,
                    )
                );

                // 'boolean' properties are displayed by default as toggleable
                // flip switches (if the 'edit' action is enabled for the document)
                if ('boolean' === $properties[$propertyName]['dataType'] && !in_array('edit', $documentConfig['disabled_actions'])) {
                    $properties[$propertyName]['dataType'] = 'toggle';
                }
            }

            $backendConfig['documents'][$documentName]['properties'] = $properties;
        }

        return $backendConfig;
    }

    /**
     * Completes the configuration of each field/property with the metadata
     * provided by Doctrine for each document property.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function processFieldConfig(array $backendConfig)
    {
        foreach ($backendConfig['documents'] as $documentName => $documentConfig) {
            foreach ($this->views as $view) {
                $originalViewConfig = $backendConfig['documents'][$documentName][$view];
                foreach ($documentConfig[$view]['fields'] as $fieldName => $fieldConfig) {
                    $originalFieldConfig = isset($originalViewConfig['fields'][$fieldName]) ? $originalViewConfig['fields'][$fieldName] : null;

                    if (array_key_exists($fieldName, $documentConfig['properties'])) {
                        $fieldMetadata = array_merge(
                            $documentConfig['properties'][$fieldName],
                            array('virtual' => false)
                        );
                    } else {
                        // this is a virtual field which doesn't exist as a property of
                        // the related document. That's why Doctrine can't provide metadata for it
                        $fieldMetadata = array_merge(
                            $this->defaultVirtualFieldMetadata,
                            array('columnName' => $fieldName, 'fieldName' => $fieldName)
                        );
                    }

                    $normalizedConfig = array_replace_recursive(
                        $this->defaultDocumentFieldConfig,
                        $fieldMetadata,
                        $fieldConfig
                    );

                    // 'list', 'search' and 'show' views: use the value of the 'type' option
                    // as the 'dataType' option because the previous code has already
                    // prioritized end-user preferences over Doctrine and default values
                    if (in_array($view, array('list', 'search', 'show'))) {
                        $normalizedConfig['dataType'] = $normalizedConfig['type'];
                    }

                    // 'new' and 'edit' views: if the user has defined the 'type' option
                    // for the field, use it as 'fieldType'. Otherwise, use the guessed
                    // form type of the property data type.
                    if (in_array($view, array('edit', 'new'))) {
                        $normalizedConfig['fieldType'] = isset($originalFieldConfig['type'])
                            ? $originalFieldConfig['type']
                            : $normalizedConfig['fieldType'];

                        if (null === $normalizedConfig['fieldType']) {
                            // this is a virtual field which doesn't exist as a property of
                            // the related document. Textarea is used as a default form type.
                            $normalizedConfig['fieldType'] = 'textarea';
                        }

                        $normalizedConfig['type_options'] = $this->getFormTypeOptionsOfProperty(
                            $normalizedConfig, $fieldMetadata, $originalFieldConfig
                        );
                    }

                    // special case for the 'list' view: 'boolean' properties are displayed
                    // as toggleable flip switches when certain conditions are met
                    if ('list' === $view && 'boolean' === $normalizedConfig['dataType']) {
                        // conditions:
                        //   1) the end-user hasn't configured the field type explicitly
                        //   2) the 'edit' action is enabled for the 'list' view of this document
                        if (!isset($originalFieldConfig['type']) && !in_array('edit', $documentConfig['disabled_actions'])) {
                            $normalizedConfig['dataType'] = 'toggle';
                        }
                    }

                    if (null === $normalizedConfig['format']) {
                        $normalizedConfig['format'] = $this->getFieldFormat($normalizedConfig['type'], $backendConfig);
                    }

                    $backendConfig['documents'][$documentName][$view]['fields'][$fieldName] = $normalizedConfig;
                }
            }
        }

        return $backendConfig;
    }

    /**
     * Resolves from type options of field
     *
     * @param array $mergedConfig
     * @param array $guessedConfig
     * @param array $userDefinedConfig
     *
     * @return array
     */
    private function getFormTypeOptionsOfProperty(array $mergedConfig, array $guessedConfig, array $userDefinedConfig)
    {
        $resolvedFormOptions = $mergedConfig['type_options'];

        // if the user has defined a 'type', the type options
        // must be reset so they don't get mixed with the form components guess.
        // Only the 'required' and user defined option are kept
        if (
            isset($userDefinedConfig['type'])
            && isset($guessedConfig['fieldType'])
            && $userDefinedConfig['type'] !== $guessedConfig['fieldType']
        ) {
            $resolvedFormOptions = array_merge(
                array_intersect_key($resolvedFormOptions, array('required' => null)),
                isset($userDefinedConfig['type_options']) ? $userDefinedConfig['type_options'] : array()
            );
        }
        // if the user has defined the "type" or "type_options"
        // AND the "type" is the same as the default one
        elseif (
            (
                isset($userDefinedConfig['type'])
                && isset($guessedConfig['fieldType'])
                && $userDefinedConfig['type'] === $guessedConfig['fieldType']
            ) || (
                !isset($userDefinedConfig['type']) && isset($userDefinedConfig['type_options'])
            )
        ) {
            $resolvedFormOptions = array_merge(
                $resolvedFormOptions,
                isset($userDefinedConfig['type_options']) ? $userDefinedConfig['type_options'] : array()
            );
        }

        return $resolvedFormOptions;
    }

    /**
     * Guesses what Form Type a property of a class has.
     *
     * @param string $class
     * @param string $property
     *
     * @return TypeGuess|null
     */
    private function getFormTypeGuessOfProperty($class, $property)
    {
        return $this->formRegistry->getTypeGuesser()->guessType($class, $property);
    }

    /**
     * Guesses if a property of a class should be a required field in a Form.
     *
     * @param string $class
     * @param string $property
     *
     * @return ValueGuess|null
     */
    private function getFormRequiredGuessOfProperty($class, $property)
    {
        return $this->formRegistry->getTypeGuesser()->guessRequired($class, $property);
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
}
