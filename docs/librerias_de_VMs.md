# Instalación de Dependencias en la Máquina Virtual

## Objetivo

Instalar y configurar los componentes necesarios para ejecutar una aplicación web PHP sobre Apache y permitir la conexión con una base de datos MySQL.

---

# Actualización de Repositorios

Antes de instalar cualquier paquete, se actualiza la información de los repositorios del sistema.

```bash
sudo apt update
```

### ¿Qué hace?

- Descarga la lista más reciente de paquetes disponibles.
- Actualiza las versiones disponibles desde los repositorios configurados.
- No instala ni actualiza software; únicamente sincroniza el catálogo de paquetes.

---

# Instalación de Apache HTTP Server

Apache será utilizado como servidor web para publicar la aplicación.

```bash
sudo apt install apache2 -y
```

### ¿Qué hace?

Instala:

- Apache HTTP Server (`apache2`)
- Servicios y configuraciones básicas del servidor web
- Archivos de configuración necesarios para servir contenido web

### Funcionalidades

- Publicación de sitios web HTTP/HTTPS.
- Manejo de solicitudes de clientes.
- Integración con PHP mediante módulos de Apache.

### Verificación

```bash
systemctl status apache2
```

o

```bash
curl http://localhost
```

---

# Instalación de PHP y Extensiones

Se instalan PHP y los módulos necesarios para la aplicación.

```bash
sudo apt install php libapache2-mod-php php-mysql php-curl -y
```

---

## PHP

Paquete:

```text
php
```

### Función

Instala el intérprete de PHP que permite ejecutar aplicaciones desarrolladas en este lenguaje.

### Uso

- Procesamiento de páginas dinámicas.
- Lógica de negocio.
- Comunicación con bases de datos.

---

## Módulo PHP para Apache

Paquete:

```text
libapache2-mod-php
```

### Función

Permite que Apache ejecute archivos PHP directamente.

### Beneficio

Cuando un usuario solicita un archivo `.php`:

```text
Apache
   ↓
Módulo PHP
   ↓
Ejecuta código PHP
   ↓
Genera HTML
```

---

## Extensión MySQL para PHP

Paquete:

```text
php-mysql
```

### Función

Permite que las aplicaciones PHP se conecten a bases de datos MySQL.

### Capacidades

- Apertura de conexiones.
- Ejecución de consultas SQL.
- Inserción y actualización de registros.
- Lectura de resultados.

### Ejemplo de uso

```php
$conn = new mysqli(
    $host,
    $user,
    $password,
    $database
);
```

---

## Extensión cURL para PHP

Paquete:

```text
php-curl
```

### Función

Permite realizar solicitudes HTTP y HTTPS desde PHP.

### Casos de uso

- Consumo de APIs REST.
- Integración con servicios externos.
- Comunicación con aplicaciones en la nube.

### Ejemplo

```php
$curl = curl_init($url);
curl_exec($curl);
```

---

# Reinicio de Apache

Después de instalar PHP y sus módulos es recomendable reiniciar Apache.

```bash
sudo systemctl restart apache2
```

---

# Verificación de la Instalación

Comprobar la versión de PHP instalada:

```bash
php -v
```

Comprobar que Apache se encuentra activo:

```bash
systemctl status apache2
```

Verificar los módulos PHP cargados:

```bash
php -m
```

---

# Componentes Instalados

| Componente | Propósito |
|------------|------------|
| Apache2 | Servidor web |
| PHP | Lenguaje de ejecución del backend |
| libapache2-mod-php | Integración de PHP con Apache |
| php-mysql | Conectividad con MySQL |
| php-curl | Consumo de APIs y servicios externos |

---

# Resultado Final

Al finalizar la instalación, la máquina virtual queda preparada para:

- Ejecutar aplicaciones PHP.
- Servir contenido web mediante Apache.
- Conectarse a bases de datos MySQL.
- Consumir servicios web externos mediante HTTP/HTTPS.
- Hospedar aplicaciones web académicas o empresariales.