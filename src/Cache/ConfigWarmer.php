<?php

namespace AlterPHP\EasyAdminOdmBundle\Cache;

use AlterPHP\EasyAdminOdmBundle\Configuration\ConfigManager;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

/**
 * Ensures that the backend configuration is fully processed before executing
 * the application for the first time.
 */
class ConfigWarmer implements CacheWarmerInterface
{
    /** @var ConfigManager */
    private $configManager;

    public function __construct(ConfigManager $configManager)
    {
        $this->configManager = $configManager;
    }

    /**
     * {@inheritdoc}
     */
    public function warmUp($cacheDir)
    {
        try {
            // this forces the full processing of the backend configuration
            $this->configManager->getBackendConfig();
        } catch (\PDOException $e) {
            // this occurs for example when the database doesn't exist yet and the
            // project is being installed ('composer install' clears the cache at the end)
            // ignore this error at this point and display an error message later
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isOptional()
    {
        return false;
    }
}
