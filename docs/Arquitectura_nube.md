# Arquitectura en la Nube

## Descripción General

Para garantizar la disponibilidad, escalabilidad y persistencia de la información, se diseñó una arquitectura basada en servicios de Microsoft Azure. La solución permite distribuir las solicitudes de los usuarios entre múltiples instancias de la aplicación, almacenar información transaccional de forma segura y mantener archivos externos en un servicio de almacenamiento especializado.

La arquitectura fue diseñada siguiendo un modelo multicapa, separando la lógica de negocio, el almacenamiento de datos y los recursos de almacenamiento de archivos.

## Diagrama de Arquitectura

[Insertar aquí el diagrama Mermaid o imagen de arquitectura]

## Componentes Utilizados

### Azure Load Balancer

Se implementó un balanceador de carga para distribuir las solicitudes entrantes entre múltiples instancias del servidor de aplicación. Esto permite evitar puntos únicos de falla y mejorar la disponibilidad del sistema.

### Máquinas Virtuales de Azure

Las máquinas virtuales alojan la API y la lógica de negocio del sistema. Se eligió esta solución debido a la flexibilidad que ofrece para desplegar aplicaciones personalizadas y controlar completamente el entorno de ejecución.

### Azure Database for PostgreSQL Flexible Server

La información transaccional del sistema se almacena en PostgreSQL Flexible Server. Este servicio administrado reduce las tareas de mantenimiento de la base de datos y proporciona mecanismos de respaldo, monitoreo y alta disponibilidad.

### Azure Blob Storage

Se utilizó Blob Storage para almacenar archivos generados por la aplicación, documentación y recursos multimedia. Esta decisión permite desacoplar el almacenamiento de archivos del almacenamiento transaccional, mejorando la escalabilidad y reduciendo la carga sobre la base de datos.

## Flujo de Operación

1. El usuario realiza una solicitud a la aplicación.
2. El Azure Load Balancer recibe la petición.
3. La solicitud es redirigida a una de las máquinas virtuales disponibles.
4. La aplicación procesa la operación requerida.
5. Si se requiere información transaccional, la aplicación consulta PostgreSQL Flexible Server.
6. Si se requiere almacenar o recuperar archivos, la aplicación interactúa con Azure Blob Storage.
7. Finalmente, la respuesta es enviada al usuario.

## Justificación de la Arquitectura

La arquitectura propuesta busca combinar disponibilidad, escalabilidad y simplicidad operativa. El uso de múltiples máquinas virtuales detrás de un balanceador de carga permite soportar una mayor cantidad de usuarios y minimizar interrupciones del servicio. Por otro lado, PostgreSQL Flexible Server y Blob Storage proporcionan servicios administrados que reducen la complejidad de administración y permiten concentrar los esfuerzos en el desarrollo de la aplicación.
