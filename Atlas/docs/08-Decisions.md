# Architectural Decision Records (ADR)

## ADR-001: Almacenamiento en Tablas Custom Propias vs Custom Post Types
* **Fecha:** 2026-07-13
* **Estatus:** Aceptado
* **Contexto:** 
  WordPress ofrece Custom Post Types (CPT) de forma nativa para almacenar contenido. Sin embargo, para un motor de indexación de conocimiento, realizar consultas de texto complejo, búsquedas híbridas o almacenar vectores en `wp_posts` y `wp_postmeta` destruye el rendimiento del servidor en bases de datos de más de 5,000 registros debido a la arquitectura de tablas entidad-atributo-valor de WordPress.
* **Decisión:**
  Atlas utilizará una tabla dedicada llamada `wp_atlas_documents` para consolidar toda la información procesada (limpia de HTML y metadatos JSON).
* **Consecuencias:**
  * **Ventajas:** Rendimiento masivo en consultas, queries SQL dedicadas e independientes, y un campo `embedding` (BLOB) listo para RAG y búsquedas semánticas en la V2 sin romper la base de datos de WordPress.
  * **Desventajas:** Requiere desarrollar sincronización manual de datos mediante hooks (`save_post`), la cual controlará el modulo `Document Engine`.

  ## ADR-002: Aislamiento del Dominio de la Infraestructura de WordPress
* **Fecha:** 2026-07-13
* **Estatus:** Aceptado
* **Contexto:** Para evitar la obsolescencia tecnológica y el acoplamiento duro al ciclo de vida de WordPress, el negocio principal de Atlas (extracción, procesamiento, recuperación y flujos de conversación) debe ser agnóstico del CMS.
* **Decisión:**
  Toda la lógica de negocio vivirá en `/app`. La carpeta `/wordpress` actuará exclusivamente como una capa de infraestructura y adaptadores.
* **Consecuencias:**
  * **Estricta Prohibición:** Queda prohibido usar funciones globales de WordPress (`get_post`, `WP_Query`, etc.) dentro de `/app`.
  * **Abstracción:** Si el dominio necesita datos, solicitará un Contrato (Interface) a través de Inyección de Dependencias. El adaptador en `/wordpress` implementará dicha interfaz y consumirá el Core de WP.

## ADR-003: Capa de Anti-Corrupción (ACL) y Uso Pragmático de Tipos [SUPERSEDED por ADR-004]
*Nota: Reemplazado por el ADR-004 para evitar sobreingeniería en tipos primitivos y adaptarnos al ecosistema.*

## ADR-004: Límites del Dominio y Capa de Anti-Corrupción (ACL)
* **Fecha:** 2026-07-13
* **Estatus:** Aceptado (Reemplaza al ADR-003)
* **Contexto:** PHP y WordPress dependen altamente de arrays asociativos. Prohibirlos en todo el proyecto genera código redundante (boilperplate) y disminuye la productividad.
* **Decisión:** 1. Los arrays asociativos mutables están permitidos exclusivamente en las capas de infraestructura (como `/wordpress` o llamadas directas a APIs externas).
  2. Ningún array asociativo crudo puede cruzar la frontera hacia el dominio (`/app`). La infraestructura debe transformarlos en DTOs o Objetos del Dominio.
  3. Los Value Objects se reservan estrictamente para datos que encapsulan lógica de negocio o validación (ej. `Score`, `Url`). Los datos sin lógica asociada usarán tipos primitivos nativos (`string`, `int`, `array` tipado de objetos).