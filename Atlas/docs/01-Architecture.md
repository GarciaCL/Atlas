# Architectural Specification

## 1. Diseño de Arquitectura de Capas (Ports & Adapters Modificado)
Atlas está diseñado bajo una arquitectura de flujo unidireccional y desacoplada mediante interfaces de bajo acoplamiento (PHP 8.2+).

## 2. Convenciones de Diseño y Reglas de Código
* **Namespaces:** Toda clase debe residir bajo el namespace raíz `Atlas\`.
* **Inyección de Dependencias:** El constructor de cualquier módulo o clase de servicio debe exigir sus dependencias mediante Interfaces (Contracts), nunca mediante implementaciones concretas.
* **No Side-Effects:** Ninguna clase debe interactuar directamente con funciones de base de datos globales de WordPress sin usar una capa de abstracción de Repositorio (`DocumentRepository`).