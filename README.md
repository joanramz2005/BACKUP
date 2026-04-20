# KeepNAS — Portal Web SMB sobre VPN
## Guía de instalación — Windows Server 2019 + IIS + PHP

---

## Arquitectura del portal

```
Navegador (HTML + CSS + JS)
        ↕ fetch() / JSON
    API PHP (/api/*.php)
        ↕ net use / SMB
    TrueNAS SCALE (carpetas SMB)
        ↕ AD / Kerberos
    Windows Server AD (keepnas.sl)
```

**Sin LDAP. Sin autenticación IIS integrada.**
La validación de credenciales ocurre implícitamente cuando PHP ejecuta `net use` con las credenciales del usuario. Si Windows monta la carpeta → autenticado.

---

## Estructura de archivos

```
keepnas/
│
├── login.html          ← Página de login (HTML puro)
├── dashboard.html      ← Explorador de archivos (HTML puro)
├── web.config          ← IIS: VPN only, HTTPS, cabeceras, MIME types
│
├── css/
│   ├── login.css       ← Estilos de login
│   ├── dashboard.css   ← Estilos del explorador
│
├── js/
│   ├── login.js        ← Lógica del login (fetch → api/login.php)
│   ├── dashboard.js    ← Explorador de archivos (fetch → api/files.php, etc.)
│
├── api/
│   ├── login.php       ← POST: autentica via net use, inicia sesión PHP
│   ├── logout.php      ← POST: net use /delete + destruye sesión
│   ├── files.php       ← GET:  lista directorio SMB → JSON
│   ├── download.php    ← GET:  descarga segura de archivo
│   ├── upload.php      ← POST: subida de archivos (multipart)
│   ├── delete.php      ← POST: eliminar archivo/carpeta
│   ├── mkdir.php       ← POST: crear carpeta
│
├── lib/
│   ├── auth.php        ← Protección de sesión + VPN check
│   └── smb.php        ← net use mount/unmount + utilidades
│
└── data/
    ├── web.config      ← Bloquea acceso web a este directorio
    └── users.db        ← SQLite: mapeo usuario → ruta SMB (auto-creado)
```

---

## 1. Configuración — config.php

```php
define('AD_DOMAIN',        'keepnas.sl');   // Dominio Windows
define('NAS_HOST',         'truenas');      // Nombre/IP del TrueNAS
define('SHARE_BASE',       'clients');      // Share SMB raíz
define('SESSION_LIFETIME', 3600);           // Segundos hasta expirar sesión
```

---

## 2. Requisitos IIS / PHP

- **IIS 10** con: URL Rewrite, IP and Domain Restrictions, FastCGI
- **PHP 8.1+** con extensiones: `pdo`, `pdo_sqlite`, `openssl`
- **No se necesita** la extensión `ldap`

```ini
; php.ini
file_uploads          = On
upload_max_filesize   = 500M
post_max_size         = 510M
max_execution_time    = 300
session.cookie_secure   = 1
session.cookie_httponly = 1
session.cookie_samesite = Strict
expose_php              = Off
```

---

## 3. Application Pool — cuenta de dominio

El pool debe ejecutarse con una cuenta de dominio para que `net use` funcione:

```
IIS Manager → Application Pools → KeepNAS → Advanced Settings
→ Identity → Custom Account → KEEPNAS\svc_keepnas
```

---

## 4. Permisos de carpetas

```powershell
# Directorio data/ (SQLite): escritura para IIS
icacls "C:\inetpub\wwwroot\keepnas\data" /grant "IIS_IUSRS:(OI)(CI)M"
```

---

## 5. Panel de administración

Accesible en `https://portal/admin.html`.
Solo para usuarios cuyo nombre esté en `$admin_users` dentro de `api/admin.php`:

```php
$admin_users = ['administrador', 'admin', 'sysadmin'];
```

Desde el panel se puede:
- Ver todos los usuarios registrados
- Añadir/editar usuarios con rutas SMB personalizadas
- Activar/desactivar usuarios
- Eliminar usuarios del portal (no del AD ni TrueNAS)

---

## 6. Añadir clientes

### Ruta automática (sin panel admin)
1. Crear usuario AD: `KEEPNAS\clienteXX`
2. Crear carpeta TrueNAS: `pool/clients/clienteXX` con ACL para `KEEPNAS\clienteXX`
3. Listo. El portal monta `\\truenas\clients\clienteXX` automáticamente.

### Ruta personalizada (vía panel admin)
1. Pasos 1 y 2 anteriores
2. Entrar en `admin.html` → Añadir usuario → rellenar usuario + ruta SMB específica

---

## 7. Firewall

```powershell
New-NetFirewallRule -DisplayName "HTTPS KeepNAS VPN Only" `
    -Direction Inbound -Protocol TCP -LocalPort 443 `
    -RemoteAddress "10.10.0.0/24" -Action Allow
```

---

*KeepNAS Portal — keepnas.sl*
