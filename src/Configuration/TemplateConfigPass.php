<?php

namespace AlterPHP\EasyAdminMongoOdmBundle\Configuration;

use EasyCorp\Bundle\EasyAdminBundle\Configuration\ConfigPassInterface;
use Symfony\Component\Finder\Finder;

/**
 * Processes the template configuration to decide which template to use to
 * display each property in each view. It also processes the global templates
 * used when there is no document configuration (e.g. for error pages).
 */
class TemplateConfigPass implements ConfigPassInterface
{
    private $twigLoader;
    private $defaultBackendTemplates = array(
        'layout' => '@EasyAdminMongoOdm/default/layout.html.twig',
        'menu' => '@EasyAdminMongoOdm/default/menu.html.twig',
        'edit' => '@EasyAdminMongoOdm/default/edit.html.twig',
        'list' => '@EasyAdminMongoOdm/default/list.html.twig',
        'new' => '@EasyAdminMongoOdm/default/new.html.twig',
        'show' => '@EasyAdminMongoOdm/default/show.html.twig',
        'exception' => '@EasyAdminMongoOdm/default/exception.html.twig',
        'flash_messages' => '@EasyAdminMongoOdm/default/flash_messages.html.twig',
        'paginator' => '@EasyAdminMongoOdm/default/paginator.html.twig',
        'field_array' => '@EasyAdminMongoOdm/default/field_array.html.twig',
        'field_association' => '@EasyAdminMongoOdm/default/field_association.html.twig',
        'field_bigint' => '@EasyAdminMongoOdm/default/field_bigint.html.twig',
        'field_boolean' => '@EasyAdminMongoOdm/default/field_boolean.html.twig',
        'field_date' => '@EasyAdminMongoOdm/default/field_date.html.twig',
        'field_datetime' => '@EasyAdminMongoOdm/default/field_datetime.html.twig',
        'field_datetimetz' => '@EasyAdminMongoOdm/default/field_datetimetz.html.twig',
        'field_decimal' => '@EasyAdminMongoOdm/default/field_decimal.html.twig',
        'field_email' => '@EasyAdminMongoOdm/default/field_email.html.twig',
        'field_file' => '@EasyAdminMongoOdm/default/field_file.html.twig',
        'field_float' => '@EasyAdminMongoOdm/default/field_float.html.twig',
        'field_guid' => '@EasyAdminMongoOdm/default/field_guid.html.twig',
        'field_hash' => '@EasyAdminMongoOdm/default/field_hash.html.twig',
        'field_id' => '@EasyAdminMongoOdm/default/field_id.html.twig',
        'field_image' => '@EasyAdminMongoOdm/default/field_image.html.twig',
        'field_json' => '@EasyAdminMongoOdm/default/field_json.html.twig',
        'field_json_array' => '@EasyAdminMongoOdm/default/field_json_array.html.twig',
        'field_int' => '@EasyAdminMongoOdm/default/field_integer.html.twig',
        'field_integer' => '@EasyAdminMongoOdm/default/field_integer.html.twig',
        'field_object' => '@EasyAdminMongoOdm/default/field_object.html.twig',
        'field_raw' => '@EasyAdminMongoOdm/default/field_raw.html.twig',
        'field_simple_array' => '@EasyAdminMongoOdm/default/field_simple_array.html.twig',
        'field_smallint' => '@EasyAdminMongoOdm/default/field_smallint.html.twig',
        'field_string' => '@EasyAdminMongoOdm/default/field_string.html.twig',
        'field_tel' => '@EasyAdminMongoOdm/default/field_tel.html.twig',
        'field_text' => '@EasyAdminMongoOdm/default/field_text.html.twig',
        'field_time' => '@EasyAdminMongoOdm/default/field_time.html.twig',
        'field_toggle' => '@EasyAdminMongoOdm/default/field_toggle.html.twig',
        'field_url' => '@EasyAdminMongoOdm/default/field_url.html.twig',
        'label_empty' => '@EasyAdminMongoOdm/default/label_empty.html.twig',
        'label_inaccessible' => '@EasyAdminMongoOdm/default/label_inaccessible.html.twig',
        'label_null' => '@EasyAdminMongoOdm/default/label_null.html.twig',
        'label_undefined' => '@EasyAdminMongoOdm/default/label_undefined.html.twig',
    );
    private $existingTemplates = array();

    public function __construct(\Twig_Loader_Filesystem $twigLoader)
    {
        $this->twigLoader = $twigLoader;
    }

    public function process(array $backendConfig)
    {
        $backendConfig = $this->processDocumentTemplates($backendConfig);
        $backendConfig = $this->processDefaultTemplates($backendConfig);
        $backendConfig = $this->processFieldTemplates($backendConfig);

        $this->existingTemplates = array();

        return $backendConfig;
    }

    /**
     * Determines the template used to render each backend element. This is not
     * trivial because templates can depend on the document displayed and they
     * define an advanced override mechanism.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function processDocumentTemplates(array $backendConfig)
    {
        // first, resolve the general template overriding mechanism
        // 1st level priority: easy_admin.documents.<documentName>.templates.<templateName> config option
        // 2nd level priority: easy_admin.design.templates.<templateName> config option
        // 3rd level priority: app/Resources/views/easy_admin/<documentName>/<templateName>.html.twig
        // 4th level priority: app/Resources/views/easy_admin/<templateName>.html.twig
        // 5th level priority: @EasyAdminMongoOdm/default/<templateName>.html.twig
        foreach ($backendConfig['documents'] as $documentName => $documentConfig) {
            foreach ($this->defaultBackendTemplates as $templateName => $defaultTemplatePath) {
                $candidateTemplates = array(
                    isset($documentConfig['templates'][$templateName]) ? $documentConfig['templates'][$templateName] : null,
                    isset($backendConfig['design']['templates'][$templateName]) ? $backendConfig['design']['templates'][$templateName] : null,
                    'easy_admin/'.$documentName.'/'.$templateName.'.html.twig',
                    'easy_admin/'.$templateName.'.html.twig',
                );
                $templatePath = $this->findFirstExistingTemplate($candidateTemplates) ?: $defaultTemplatePath;

                if (null === $templatePath) {
                    throw new \RuntimeException(sprintf('None of the templates defined for the "%s" fragment of the "%s" document exists (templates defined: %s).', $templateName, $documentName, implode(', ', $candidateTemplates)));
                }

                $documentConfig['templates'][$templateName] = $templatePath;
            }

            $backendConfig['documents'][$documentName] = $documentConfig;
        }

        // second, walk through all document fields to determine their specific template
        foreach ($backendConfig['documents'] as $documentName => $documentConfig) {
            foreach (array('list', 'show') as $view) {
                foreach ($documentConfig[$view]['fields'] as $fieldName => $fieldMetadata) {
                    // if the field defines its own template, resolve its location
                    if (isset($fieldMetadata['template'])) {
                        $templatePath = $fieldMetadata['template'];

                        // template path should contain the .html.twig extension
                        // however, for usability reasons, we silently fix this issue if needed
                        if ('.html.twig' !== substr($templatePath, -10)) {
                            $templatePath .= '.html.twig';
                            @trigger_error(sprintf('Passing a template path without the ".html.twig" extension is deprecated since version 1.11.7 and will be removed in 2.0. Use "%s" as the value of the "template" option for the "%s" field in the "%s" view of the "%s" document.', $templatePath, $fieldName, $view, $documentName), E_USER_DEPRECATED);
                        }

                        // before considering $templatePath a regular Symfony template
                        // path, check if the given template exists in any of these directories:
                        // * app/Resources/views/easy_admin/<documentName>/<templatePath>
                        // * app/Resources/views/easy_admin/<templatePath>
                        $templatePath = $this->findFirstExistingTemplate(array(
                            'easy_admin/'.$documentName.'/'.$templatePath,
                            'easy_admin/'.$templatePath,
                            $templatePath,
                        ));
                    } else {
                        // At this point, we don't know the exact data type associated with each field.
                        // The template is initialized to null and it will be resolved at runtime in the Configurator class
                        $templatePath = null;
                    }

                    $documentConfig[$view]['fields'][$fieldName]['template'] = $templatePath;
                }
            }

            $backendConfig['documents'][$documentName] = $documentConfig;
        }

        return $backendConfig;
    }

    /**
     * Determines the templates used to render each backend element when no
     * document configuration is available. It's similar to processDocumentTemplates()
     * but it doesn't take into account the details of each document.
     * This is needed for example when an exception is triggered and no document
     * configuration is available to know which template should be rendered.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function processDefaultTemplates(array $backendConfig)
    {
        // 1st level priority: easy_admin.design.templates.<templateName> config option
        // 2nd level priority: app/Resources/views/easy_admin/<templateName>.html.twig
        // 3rd level priority: @EasyAdminMongoOdm/default/<templateName>.html.twig
        foreach ($this->defaultBackendTemplates as $templateName => $defaultTemplatePath) {
            $candidateTemplates = array(
                isset($backendConfig['design']['templates'][$templateName]) ? $backendConfig['design']['templates'][$templateName] : null,
                'easy_admin/'.$templateName.'.html.twig',
            );
            $templatePath = $this->findFirstExistingTemplate($candidateTemplates) ?: $defaultTemplatePath;

            if (null === $templatePath) {
                throw new \RuntimeException(sprintf('None of the templates defined for the global "%s" template of the backend exists (templates defined: %s).', $templateName, implode(', ', $candidateTemplates)));
            }

            $backendConfig['design']['templates'][$templateName] = $templatePath;
        }

        return $backendConfig;
    }

    /**
     * Determines the template used to render each backend element. This is not
     * trivial because templates can depend on the document displayed and they
     * define an advanced override mechanism.
     *
     * @param array $backendConfig
     *
     * @return array
     */
    private function processFieldTemplates(array $backendConfig)
    {
        foreach ($backendConfig['documents'] as $documentName => $documentConfig) {
            foreach (array('list', 'show') as $view) {
                foreach ($documentConfig[$view]['fields'] as $fieldName => $fieldMetadata) {
                    if (null !== $fieldMetadata['template']) {
                        continue;
                    }

                    // needed to add support for immutable datetime/date/time fields
                    // (which are rendered using the same templates as their non immutable counterparts)
                    if ('_immutable' === substr($fieldMetadata['dataType'], -10)) {
                        $fieldTemplateName = 'field_'.substr($fieldMetadata['dataType'], 0, -10);
                    } else {
                        $fieldTemplateName = 'field_'.$fieldMetadata['dataType'];
                    }

                    // primary key values are displayed unmodified to prevent common issues
                    // such as formatting its values as numbers (e.g. `1,234` instead of `1234`)
                    if ($documentConfig['primary_key_field_name'] === $fieldName) {
                        $template = $documentConfig['templates']['field_id'];
                    } elseif (array_key_exists($fieldTemplateName, $documentConfig['templates'])) {
                        $template = $documentConfig['templates'][$fieldTemplateName];
                    } else {
                        $template = $documentConfig['templates']['label_undefined'];
                    }

                    $documentConfig[$view]['fields'][$fieldName]['template'] = $template;
                }
            }

            $backendConfig['documents'][$documentName] = $documentConfig;
        }

        return $backendConfig;
    }

    private function findFirstExistingTemplate(array $templatePaths)
    {
        foreach ($templatePaths as $templatePath) {
            // template name normalization code taken from \Twig_Loader_Filesystem::normalizeName()
            $templatePath = preg_replace('#/{2,}#', '/', str_replace('\\', '/', $templatePath));
            $namespace = \Twig_Loader_Filesystem::MAIN_NAMESPACE;

            if (isset($templatePath[0]) && '@' === $templatePath[0]) {
                if (false === $pos = strpos($templatePath, '/')) {
                    throw new \LogicException(sprintf('Malformed namespaced template name "%s" (expecting "@namespace/template_name").', $templatePath));
                }

                $namespace = substr($templatePath, 1, $pos - 1);
            }

            if (!isset($this->existingTemplates[$namespace])) {
                foreach ($this->twigLoader->getPaths($namespace) as $path) {
                    $finder = new Finder();
                    $finder->files()->in($path);

                    foreach ($finder as $templateFile) {
                        $template = $templateFile->getRelativePathname();

                        if ('\\' === DIRECTORY_SEPARATOR) {
                            $template = str_replace('\\', '/', $template);
                        }

                        if (\Twig_Loader_Filesystem::MAIN_NAMESPACE !== $namespace) {
                            $template = sprintf('@%s/%s', $namespace, $template);
                        }
                        $this->existingTemplates[$namespace][$template] = true;
                    }
                }
            }

            if (null !== $templatePath && isset($this->existingTemplates[$namespace][$templatePath])) {
                return $templatePath;
            }
        }
    }
}
