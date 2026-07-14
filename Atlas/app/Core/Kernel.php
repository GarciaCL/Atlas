<?php

namespace Atlas\Core;

use Atlas\Configuration\Config;
use Atlas\Conversation\ConversationService;
use Atlas\Retrieval\LexicalRetrievalEngine;
use Atlas\WordPress\WordPressAdapter;
use Atlas\WordPress\Repositories\WordPressRetrievalRepository;
use Atlas\WordPress\Repositories\WordPressQuestionRepository;

class Kernel
{
    private ModuleManager $moduleManager;
    private WordPressAdapter $infraAdapter;
    private bool $isBooted = false;

    public function __construct()
    {
        $this->moduleManager = new ModuleManager();
        $this->infraAdapter = new WordPressAdapter($this->moduleManager);
    }

    public function boot(): void
    {
        if ($this->isBooted) {
            return;
        }

        $this->loadConfiguration();
        $this->loadEnvironment();
        $this->registerModules();
        $this->registerProviders();
        
        // 1. Cableamos la infraestructura de analíticas con su interfaz del dominio
        $analyticsRepo = new \Atlas\WordPress\Repositories\WordPressAnalyticsRepository();
        $analyticsService = new \Atlas\Analytics\AnalyticsService($analyticsRepo);

        // 2. Pasamos AMBOS servicios al adaptador de WordPress
        $this->infraAdapter->integrate(
            $this->buildConversationService(), 
            $analyticsService
        );
        
        $this->isBooted = true;
    }

    private function loadConfiguration(): void
    {
        Config::load([
            'version' => '1.0.0',
            'db_version' => '1.0',
            'language_default' => 'en'
        ]);
    }

    private function loadEnvironment(): void {}

    private function registerModules(): void {}

    private function registerProviders(): void {}

    /**
     * Factoría interna para construir el servicio de conversación cableando
     * las implementaciones de infraestructura con las interfaces del dominio.
     */
    private function buildConversationService(): ConversationService
    {
        // Acoplamos los repositorios físicos a las interfaces abstractas (ACL)
        $retrievalRepo = new WordPressRetrievalRepository();
        $questionRepo = new WordPressQuestionRepository();

        $retrievalEngine = new LexicalRetrievalEngine($retrievalRepo);

        return new ConversationService($retrievalEngine, $questionRepo);
    }
}