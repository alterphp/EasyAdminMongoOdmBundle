<?php

namespace AlterPHP\EasyAdminMongoOdmBundle\Configuration;

use AlterPHP\EasyAdminMongoOdmBundle\Exception\UndefinedDocumentException;
use EasyCorp\Bundle\EasyAdminBundle\Configuration\ConfigPassInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Contracts\Cache\CacheInterface;

class ConfigManager
{
    private const CACHE_KEY = 'easyadmin_mongoodm.processed_config';

    /** @var array */
    private $odmBackendConfig;
    /** @var CacheInterface */
    private $cache;
    /** @var PropertyAccessorInterface */
    private $propertyAccessor;
    /** @var array */
    private $originalOdmBackendConfig;
    /** @var ConfigPassInterface[] */
    private $odmConfigPasses = [];
    /** @var bool */
    private $debug;

    public function __construct(
        array $originalOdmBackendConfig, $debug, PropertyAccessorInterface $propertyAccessor, CacheInterface $cache
    ) {
        $this->originalOdmBackendConfig = $originalOdmBackendConfig;
        $this->debug = $debug;
        $this->propertyAccessor = $propertyAccessor;
        $this->cache = $cache;
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
     * the optional property path. Example: getBackendConfig('design.menu').
     *
     * @param string|null $propertyPath
     *
     * @return array
     */
    public function getBackendConfig($propertyPath = null)
    {
        $this->loadBackendConfig();

        if (empty($propertyPath)) {
            return $this->odmBackendConfig;
        }

        // turns 'design.menu' into '[design][menu]', the format required by PropertyAccess
        $propertyPath = '['.\str_replace('.', '][', $propertyPath).']';

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
        $odmBackendConfig = $this->getBackendConfig();
        if (!isset($odmBackendConfig['documents'][$documentName])) {
            throw new UndefinedDocumentException(['document_name' => $documentName]);
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
        $odmBackendConfig = $this->getBackendConfig();
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
            $documentConfig = [];
        }

        return $documentConfig[$view]['actions'][$action] ?? [];
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

        return !\in_array($action, $documentConfig['disabled_actions']) && \array_key_exists($action, $documentConfig[$view]['actions']);
    }

    private function loadBackendConfig(): array
    {
        if (null !== $this->odmBackendConfig) {
            return $this->odmBackendConfig;
        }

        if (true === $this->debug) {
            return $this->odmBackendConfig = $this->doProcessOdmConfig($this->originalOdmBackendConfig);
        }

        return $this->odmBackendConfig = $this->cache->get(self::CACHE_KEY, function () {
            return $this->doProcessOdmConfig($this->originalOdmBackendConfig);
        });
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
