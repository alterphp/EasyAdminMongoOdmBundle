<?php

namespace AlterPHP\EasyAdminMongoOdmBundle\DataCollector;

use AlterPHP\EasyAdminMongoOdmBundle\Configuration\ConfigManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\Yaml\Yaml;

class EasyAdminMongoOdmDataCollector extends DataCollector
{
    /** @var ConfigManager */
    private $configManager;

    public function __construct(ConfigManager $configManager)
    {
        $this->configManager = $configManager;
        $this->reset();
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $this->data = [
            'num_documents' => 0,
            'request_parameters' => null,
            'current_document_configuration' => null,
            'backend_configuration' => null,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Throwable $exception = null)
    {
        if ('easyadmin_mongo_odm' !== $request->attributes->get('_route')) {
            return;
        }

        $backendConfig = $this->configManager->getBackendConfig();
        $documentName = $request->query->get('document', null);
        $currentDocumentConfig = \array_key_exists($documentName, $backendConfig['documents']) ? $backendConfig['documents'][$documentName] : [];

        $this->data = [
            'num_documents' => \count($backendConfig['documents']),
            'request_parameters' => $this->getEasyAdminMongoOdmParameters($request),
            'current_document_configuration' => $currentDocumentConfig,
            'backend_configuration' => $backendConfig,
        ];
    }

    /**
     * @param Request $request
     *
     * @return array|null
     */
    private function getEasyAdminMongoOdmParameters(Request $request)
    {
        return [
            'action' => $request->query->get('action'),
            'document' => $request->query->get('document'),
            'id' => $request->query->get('id'),
            'sort_field' => $request->query->get('sortField'),
            'sort_direction' => $request->query->get('sortDirection'),
        ];
    }

    /**
     * @return bool
     */
    public function isEasyAdminMongoOdmAction()
    {
        return isset($this->data['num_documents']) && 0 !== $this->data['num_documents'];
    }

    /**
     * @return int
     */
    public function getNumDocuments()
    {
        return $this->data['num_documents'];
    }

    /**
     * @return array
     */
    public function getRequestParameters()
    {
        return $this->data['request_parameters'];
    }

    /**
     * @return array
     */
    public function getCurrentDocumentConfig()
    {
        return $this->data['current_document_configuration'];
    }

    /**
     * @return array
     */
    public function getBackendConfig()
    {
        return $this->data['backend_configuration'];
    }

    /**
     * It dumps the contents of the given variable. It tries several dumpers in
     * turn (VarDumper component, Yaml::dump, etc.) and if none is available, it
     * falls back to PHP's var_export().
     *
     * @param mixed $variable
     *
     * @return string
     */
    public function dump($variable)
    {
        if (\class_exists('Symfony\Component\VarDumper\Dumper\HtmlDumper')) {
            $cloner = new VarCloner();
            $dumper = new HtmlDumper();

            $dumper->dump($cloner->cloneVar($variable), $output = \fopen('php://memory', 'r+b'));
            $dumpedData = \stream_get_contents($output, -1, 0);
        } elseif (\class_exists('Symfony\Component\Yaml\Yaml')) {
            $dumpedData = \sprintf('<pre class="sf-dump">%s</pre>', Yaml::dump((array) $variable, 1024));
        } else {
            $dumpedData = \sprintf('<pre class="sf-dump">%s</pre>', \var_export($variable, true));
        }

        return $dumpedData;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'easyadmin_mongo_odm';
    }
}
