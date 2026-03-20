<?php
// config.php — Configuración global del portal KeepNAS

define('AD_DOMAIN',        'keepnas.sl');
define('NAS_HOST',         'truenas');
define('SHARE_BASE',       'clients');
define('SESSION_LIFETIME', 3600);
define('USERS_DB',         __DIR__ . '/data/users.db');

date_default_timezone_set('Europe/Madrid');
