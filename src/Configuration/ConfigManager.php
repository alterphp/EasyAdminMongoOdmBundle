<?php

namespace AlterPHP\EasyAdminOdmBundle\Configuration;

use AlterPHP\EasyAdminOdmBundle\Exception\UndefinedDocumentException;
use EasyCorp\Bundle\EasyAdminBundle\Cache\CacheManager;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class ConfigManager
{
    /** @var array */
    private $odmBackendConfig;
    /** @var CacheManager */
    private $cacheManager;
    /** @var PropertyAccessorInterface */
    private $propertyAccessor;
    /** @var array */
    private $originalOdmBackendConfig;
    /** @var ConfigPassInterface[] */
    private $odmConfigPasses;
    /** @var bool */
    private $debug;

    public function __construct(
        CacheManager $cacheManager, PropertyAccessorInterface $propertyAccessor, array $originalOdmBackendConfig, $debug
    ) {
        $this->cacheManager = $cacheManager;
        $this->propertyAccessor = $propertyAccessor;
        $this->originalOdmBackendConfig = $originalOdmBackendConfig;
        $this->debug = $debug;
    }

    /*
     * ### Every methods below are copied from EasyAdmin ConfigManager and adapted to ODM documents ###
     */

    /**
     * @param ConfigPassInterface $odmConfigPass
     */
    public function addConfigPass(ConfigPassInterface $odmConfigPass)
    {
        $this->odmConfigPasses[] = $odmConfigPass;
    }

    /**
     * Returns the entire backend configuration or just the configuration for
     * the optional property path. Example: getOdmBackendConfig('design.menu').
     *
     * @param string|null $propertyPath
     *
     * @return array
     */
    public function getOdmBackendConfig($propertyPath = null)
    {
        if (null === $this->odmBackendConfig) {
            $this->odmBackendConfig = $this->processOdmConfig();
        }

        if (empty($propertyPath)) {
            return $this->odmBackendConfig;
        }

        // turns 'design.menu' into '[design][menu]', the format required by PropertyAccess
        $propertyPath = '['.str_replace('.', '][', $propertyPath).']';

        return $this->propertyAccessor->getValue($this->odmBackendConfig, $propertyPath);
    }

    /**
     * Returns the configuration for the given document name.
     *
     * @param string $documentName
     *
     * @deprecated Use getDocumentConfig()
     *
     * @return array The full document configuration
     *
     * @throws \InvalidArgumentException when the document isn't managed by EasyAdmin
     */
    public function getDocumentConfiguration($documentName)
    {
        return $this->getDocumentConfig($documentName);
    }

    /**
     * Returns the configuration for the given document name.
     *
     * @param string $documentName
     *
     * @return array The full document configuration
     *
     * @throws \InvalidArgumentException
     */
    public function getDocumentConfig($documentName)
    {
        $odmBackendConfig = $this->getOdmBackendConfig();
        if (!isset($odmBackendConfig['documents'][$documentName])) {
            throw new UndefinedDocumentException(array('document_name' => $documentName));
        }

        return $odmBackendConfig['documents'][$documentName];
    }

    /**
     * Returns the full document config for the given document class.
     *
     * @param string $fqcn The full qualified class name of the document
     *
     * @return array|null The full document configuration
     */
    public function getDocumentConfigByClass($fqcn)
    {
        $odmBackendConfig = $this->getOdmBackendConfig();
        foreach ($odmBackendConfig['documents'] as $documentName => $documentConfig) {
            if ($documentConfig['class'] === $fqcn) {
                return $documentConfig;
            }
        }
    }

    /**
     * Returns the full action configuration for the given 'document' and 'view'.
     *
     * @param string $documentName
     * @param string $view
     * @param string $action
     *
     * @return array
     */
    public function getActionConfig($documentName, $view, $action)
    {
        try {
            $documentConfig = $this->getDocumentConfig($documentName);
        } catch (\Exception $e) {
            $documentConfig = array();
        }

        return isset($documentConfig[$view]['actions'][$action]) ? $documentConfig[$view]['actions'][$action] : array();
    }

    /**
     * Checks whether the given 'action' is enabled for the given 'document' and
     * 'view'.
     *
     * @param string $documentName
     * @param string $view
     * @param string $action
     *
     * @return bool
     */
    public function isActionEnabled($documentName, $view, $action)
    {
        $documentConfig = $this->getDocumentConfig($documentName);

        return !in_array($action, $documentConfig['disabled_actions']) && array_key_exists($action, $documentConfig[$view]['actions']);
    }

    /**
     * It processes the original backend configuration defined by the end-users
     * to generate the full configuration used by the application. Depending on
     * the environment, the configuration is processed every time or once and
     * the result cached for later reuse.
     *
     * @return array
     */
    private function processOdmConfig()
    {
        if (true === $this->debug) {
            return $this->doProcessOdmConfig($this->originalOdmBackendConfig);
        }

        if ($this->cacheManager->hasItem('processed_odm_config')) {
            return $this->cacheManager->getItem('processed_odm_config');
        }

        $odmBackendConfig = $this->doProcessOdmConfig($this->originalOdmBackendConfig);
        $this->cacheManager->save('processed_odm_config', $odmBackendConfig);

        return $odmBackendConfig;
    }

    /**
     * It processes the given backend configuration to generate the fully
     * processed configuration used in the application.
     *
     * @param array $odmBackendConfig
     *
     * @return array
     */
    private function doProcessOdmConfig($odmBackendConfig)
    {
        foreach ($this->odmConfigPasses as $configPass) {
            $odmBackendConfig = $configPass->process($odmBackendConfig);
        }

        return $odmBackendConfig;
    }
}
