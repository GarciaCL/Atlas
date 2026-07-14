<?php

namespace Atlas\Contracts;

interface ModuleInterface
{
    /**
     * Registra los servicios del módulo dentro del sistema.
     */
    public function register(): void;

    /**
     * Arranca la ejecución y ganchos del módulo.
     */
    public function boot(): void;
}