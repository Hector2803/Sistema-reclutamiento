<?php
/* ============================================================
   SSR - A365 | Correo saliente (SMTP)
   - Configuración SMTP en config_correo (pestaña Correo)
   - Envío con conexión directa al servidor SMTP (sin librerías)
   - Historial de correos en correos_enviados
   ============================================================ */

/** Configuración vigente (se guarda en caché por petición). */
function correoConfig(PDO $pdo): array
{
    static $cfg = null;
    if ($cfg !== null) { return $cfg; }

    $defecto = [
        'host'             => 'smtp.gmail.com',
        'puerto'           => 587,
        'seguridad'        => 'tls',
        'usuario'          => '',
        'clave'            => '',
        'remitente_nombre' => 'A365 Reclutamiento',
        'remitente_correo' => '',
        'dominio'          => 'a365.com',
        'activo'           => 1,
    ];

    try {
        $row = $pdo->query("SELECT * FROM config_correo WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $row = false;
    }

    $cfg = $row ? array_merge($defecto, $row) : $defecto;
    return $cfg;
}

/** Dominio de las cuentas de la empresa (ej. a365.com). */
function correoDominio(PDO $pdo): string
{
    $d = trim(correoConfig($pdo)['dominio'] ?? '');
    return $d !== '' ? $d : 'a365.com';
}

/** ¿Está completo todo lo necesario para enviar? */
function correoListo(array $c): bool
{
    return ($c['host'] ?? '') !== ''
        && (int)($c['puerto'] ?? 0) > 0
        && ($c['usuario'] ?? '') !== ''
        && (string)($c['clave'] ?? '') !== ''
        && ($c['remitente_correo'] ?? '') !== '';
}

/** Guarda una copia del correo en el historial. */
function correoRegistrar(
    PDO $pdo, ?int $usuarioId, string $para, string $asunto,
    string $cuerpo, string $estado, string $detalle = ''
): void {
    try {
        $st = $pdo->prepare(
            "INSERT INTO correos_enviados (usuario_id, destinatario, asunto, cuerpo, estado, detalle)
             VALUES (:u, :p, :a, :c, :e, :d)"
        );
        $st->execute([
            ':u' => $usuarioId,
            ':p' => mb_substr($para, 0, 190),
            ':a' => mb_substr($asunto, 0, 190),
            ':c' => $cuerpo,
            ':e' => mb_substr($estado, 0, 20),
            ':d' => mb_substr($detalle, 0, 500),
        ]);
    } catch (Throwable $e) {
        // El historial es secundario: no interrumpe el flujo
    }
}

/** Últimos correos registrados (para la pestaña Correo). */
function correoHistorial(PDO $pdo, int $limite = 50): array
{
    try {
        return $pdo->query(
            "SELECT id, destinatario, asunto, estado, detalle, creado_en
               FROM correos_enviados
              ORDER BY creado_en DESC
              LIMIT " . (int)$limite
        )->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/** URL de acceso al login (para incluir en los correos). */
function correoUrlLogin(): string
{
    if (defined('CORREO_URL_LOGIN') && CORREO_URL_LOGIN !== '') return (string)CORREO_URL_LOGIN;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $ruta  = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if ($ruta === '/' || $ruta === '.' || $ruta === '\\') { $ruta = ''; }
    if (substr($ruta, -8) === '/portal') { $ruta = substr($ruta, 0, -8); }
    return $https . '://' . $host . rtrim($ruta, '/') . '/login.php';
}

/**
 * ¿Conviene incluir el botón/enlace de acceso en el correo?
 * Gmail rechaza ("mensaje sospechoso / se bloqueó") los correos con enlaces a dominios de
 * hosting gratuito (unaux.com, epizy, etc.) o sin HTTPS, y Chrome muestra "Sitio peligroso"
 * en esos dominios (Navegación Segura de Google). Por eso, por defecto, el botón solo se
 * incluye con HTTPS y un dominio propio; en los demás casos el correo sale sin botón.
 * Se puede forzar con define('CORREO_INCLUIR_ENLACE', true|false) en config/sistema.php,
 * y fijar la dirección con define('CORREO_URL_LOGIN', 'https://su-dominio/login.php').
 */
function correoEnlaceSeguro(string $url): bool
{
    if (defined('CORREO_INCLUIR_ENLACE')) return (bool)CORREO_INCLUIR_ENLACE;
    if (stripos($url, 'https://') !== 0) return false;
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) return false;
    foreach (['unaux.com','epizy.com','ezyro.com','rf.gd','infinityfreeapp.com','byethost','wuaze.com','000webhostapp.com','free.nf','kesug.com','great-site.net','42web.io','lovestoblog.com','gt.tc'] as $mal) {
        if (strpos($host, $mal) !== false) return false;
    }
    return true;
}

/** Imágenes del diseño del correo (se envían incrustadas, con Content-ID). */
function correoImagenesDisponibles(): array
{
    $dir = __DIR__ . '/../assets/img/correo/';
    $r = [];
    foreach (['banner','pie','ic_sobre','ic_candado','ic_monitor','ic_info','ic_ayuda','ic_cuenta','ic_clave'] as $n) {
        if (is_file($dir . $n . '.png')) $r[$n . '@a365'] = $dir . $n . '.png';
    }
    foreach (correoLayout() as $n => $m) {
        if (is_file($dir . $m['f'])) $r[$n . '@a365'] = $dir . $m['f'];
    }
    return $r;
}

/** Medidas (px a 600 de ancho) y archivo de cada tira del diseño fiel del correo. */
function correoLayout(): array
{
    static $l = null;
    if ($l === null) {
        $f = __DIR__ . '/../assets/img/correo/layout.json';
        $l = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
    }
    return $l;
}

/**
 * Plantilla HTML base de los correos del sistema.
 * Diseño A365: banner con logo, título en dos líneas (azul + rojo) con ícono, contenido y pie de marca.
 *   $titulo  : "Bienvenido|a nuestro equipo!" -> la barra separa la línea azul de la roja
 *              (sin barra: la última palabra va en rojo).
 *   $icono   : ic_sobre | ic_candado
 */
function correoPlantilla(string $titulo, string $contenido, string $nota = '', string $icono = 'ic_sobre'): string
{
    $img = fn(string $n, int $w, string $alt = '') =>
        '<img src="cid:' . $n . '@a365" width="' . $w . '" alt="' . $alt . '" style="display:block;border:0;outline:none;height:auto;width:' . $w . 'px;max-width:100%">';

    if (strpos($titulo, '|') !== false) {
        [$l1, $l2] = array_map('trim', explode('|', $titulo, 2));
    } else {
        $partes = explode(' ', trim($titulo));
        $l2 = array_pop($partes);
        $l1 = implode(' ', $partes);
    }
    $tit = ($l1 !== '' ? '<span style="color:#0b2a63">' . $l1 . '</span><br>' : '') . '<span style="color:#e10613">' . $l2 . '</span>';

    $notaH = $nota !== ''
        ? '<tr><td style="padding:0 32px 10px"><p style="font-size:12.5px;color:#7a869a;margin:0;line-height:1.55">' . $nota . '</p></td></tr>'
        : '';

    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="color-scheme" content="light only"><meta name="supported-color-schemes" content="light only"></head>'
        . '<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#0f2447">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#f4f7fb"><tr><td align="center" style="padding:22px 10px">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="width:600px;max-width:100%;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e3eaf4">'
        . '<tr><td>' . $img('banner', 600, 'A365 · Talento que impulsa grandes resultados') . '</td></tr>'
        // Título + ícono
        . '<tr><td style="padding:26px 32px 4px">'
        .   '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
        .   '<td valign="middle"><div style="font-size:32px;line-height:1.1;font-weight:800;letter-spacing:-.5px">' . $tit . '</div>'
        .   '<div style="width:46px;height:3px;background:#e10613;margin-top:14px;font-size:0;line-height:0">&nbsp;</div></td>'
        .   '<td width="84" valign="middle" align="right">' . $img($icono, 80, '') . '</td>'
        .   '</tr></table>'
        . '</td></tr>'
        . '<tr><td style="padding:14px 32px 6px">' . $contenido . '</td></tr>'
        . $notaH
        . '<tr><td style="padding:8px 32px 18px"><p style="margin:0;font-size:13.5px;color:#5b6b85">Equipo de Reclutamiento A365</p></td></tr>'
        . '<tr><td>' . $img('pie', 600, 'A365 · Personas · Oportunidades · Crecimiento · Juntos logramos más') . '</td></tr>'
        . '</table>'
        . '<p style="font-size:11.5px;color:#9aa6b8;margin:12px 0 0;line-height:1.5">Este mensaje fue enviado automáticamente por el sistema de reclutamiento de A365.<br>Por favor, no respondas a este correo.</p>'
        . '</td></tr></table></body></html>';
}

/**
 * Correo de credenciales con el diseño aprobado (A365): el fondo, textos, íconos y pie van como
 * tiras de imagen idénticas a la maqueta, y solo la cuenta y la contraseña son texto real
 * (se pueden seleccionar y copiar).
 *   $reinicio = false -> "¡Bienvenido a nuestro equipo!"   |   true -> "Nueva contraseña temporal"
 */
function correoCredenciales(string $nombre, string $cuenta, string $clave, bool $temporal = true, bool $reinicio = false): string
{
    $L = correoLayout();
    $v = $reinicio ? 'r' : 'n';
    if ((defined('CORREO_DISENO') && CORREO_DISENO === 'texto') || !isset($L[$v . '_top'])) return correoCredencialesSencillo($nombre, $cuenta, $clave, $reinicio);

    $e = fn(string $x) => htmlspecialchars($x, ENT_QUOTES, 'UTF-8');
    $im = function (string $k, string $alt = '') use ($L, $v) {
        $m = $L[$v . '_' . $k];
        return '<img src="cid:' . $v . '_' . $k . '@a365" width="' . $m['w'] . '" height="' . $m['h'] . '" alt="' . $alt
            . '" style="display:block;border:0;outline:none;width:' . $m['w'] . 'px;height:' . $m['h'] . 'px">';
    };
    $campo = function (string $k, string $valor) use ($L, $v, $im) {
        $l = $L[$v . '_' . $k . 'l']; $r = $L[$v . '_' . $k . 'r'];
        $alto = $l['h']; $ancho = 600 - $l['w'] - $r['w'];
        return '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;border-collapse:collapse"><tr>'
            . '<td width="' . $l['w'] . '" style="padding:0;line-height:0;font-size:0">' . $im($k . 'l') . '</td>'
            . '<td width="' . $ancho . '" height="' . $alto . '" bgcolor="#e3effd" valign="middle" style="background:#e3effd;border-radius:8px;height:' . $alto . 'px;padding:0 10px;'
            . 'font-family:Arial,Helvetica,sans-serif;font-size:17px;font-weight:700;color:#0b2a63;line-height:1.1;word-break:break-all">' . $valor . '</td>'
            . '<td width="' . $r['w'] . '" style="padding:0;line-height:0;font-size:0">' . $im($k . 'r') . '</td></tr></table>';
    };
    $f1 = $campo('f1', '<a href="mailto:' . $e($cuenta) . '" style="color:#0b2a63;text-decoration:none">' . $e($cuenta) . '</a>');
    $f2 = $campo('f2', $e($clave));

    $login = correoUrlLogin();
    $fin = 'fin';
    if (correoEnlaceSeguro($login)) {
        $b = $L[$v . '_bt'];
        $panel = $im('pa', 'Acceso al sistema')
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;border-collapse:collapse"><tr>'
            . '<td style="padding:0;line-height:0;font-size:0">' . $im('bl') . '</td>'
            . '<td style="padding:0;line-height:0;font-size:0"><a href="' . $e($login) . '" target="_blank" style="text-decoration:none">'
            . $im('bt', 'Ir al sistema') . '</a></td>'
            . '<td style="padding:0;line-height:0;font-size:0">' . $im('br') . '</td></tr></table>';
    } else {
        $panel = $im('pu', 'Acceso al sistema: ingresa desde la dirección web que te indicó el administrador');
        $fin = 'finu';
    }

    $texto = ($reinicio
        ? "NUEVA CONTRASEÑA TEMPORAL\nSe ha generado una nueva contraseña temporal para tu cuenta en el sistema de reclutamiento de A365. Utiliza las siguientes credenciales para ingresar nuevamente al sistema."
        : "¡BIENVENIDO A NUESTRO EQUIPO!\nSe ha creado tu cuenta en el sistema de reclutamiento de A365. Con esta cuenta podrás continuar con tu proceso y realizar las actividades asignadas por nuestro equipo.")
        . "\n\nCuenta de acceso: {$cuenta}\nContraseña temporal: {$clave}\n\n"
        . (correoEnlaceSeguro($login) ? "Ingresa al sistema: {$login}\n\n" : "Ingresa al sistema de reclutamiento desde la dirección web que te indicó el administrador.\n\n")
        . "Esta contraseña es temporal: al iniciar sesión se te pedirá crear una nueva contraseña.\n"
        . ($reinicio ? "Si no solicitaste esta recuperación, por favor contacta al administrador.\n" : "Si no solicitaste esta cuenta, por favor contacta al administrador.\n")
        . "\nEquipo de Reclutamiento A365";

    $alt = $reinicio ? 'A365 · Nueva contraseña temporal' : 'A365 · ¡Bienvenido a nuestro equipo!';
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="color-scheme" content="light only"><meta name="supported-color-schemes" content="light only"></head>'
        . '<body style="margin:0;padding:0;background:#f4f7fb"><!--TXT' . $texto . 'TXT-->'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" bgcolor="#f4f7fb"><tr><td align="center" style="padding:20px 8px">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" bgcolor="#ffffff" style="width:600px;max-width:600px;background:#ffffff;border-collapse:collapse"><tr><td style="padding:0;line-height:0;font-size:0">'
        . $im('top', $alt) . '</td></tr><tr><td style="padding:0;line-height:0;font-size:0">' . $f1 . '</td></tr>'
        . '<tr><td style="padding:0;line-height:0;font-size:0">' . $im('mid') . '</td></tr>'
        . '<tr><td style="padding:0;line-height:0;font-size:0">' . $f2 . '</td></tr>'
        . '<tr><td style="padding:0;line-height:0;font-size:0">' . $panel . '</td></tr>'
        . '<tr><td style="padding:0;line-height:0;font-size:0">' . $im($fin, 'A365 · Personas · Oportunidades · Crecimiento · Juntos logramos más') . '</td></tr></table>'
        . '</td></tr></table></body></html>';
}

/** Respaldo si faltan las imágenes del diseño: versión simple con la plantilla genérica. */
function correoCredencialesSencillo(string $nombre, string $cuenta, string $clave, bool $reinicio): string
{
    $e = fn(string $x) => htmlspecialchars($x, ENT_QUOTES, 'UTF-8');
    return correoPlantilla(
        $reinicio ? 'Nueva|contraseña temporal' : '¡Bienvenido|a nuestro equipo!',
        '<p style="font-size:15px;line-height:1.6">Cuenta de acceso: <strong>' . $e($cuenta) . '</strong><br>Contraseña temporal: <strong>' . $e($clave) . '</strong></p>'
        . '<p style="font-size:13.5px">Esta contraseña es temporal: al iniciar sesión se te pedirá crear una nueva.</p>',
        'Si no solicitaste esto, contacta al administrador.',
        $reinicio ? 'ic_candado' : 'ic_sobre'
    );
}

/** Versión en texto plano de un correo HTML (los filtros de spam penalizan los correos solo-HTML). */
function correoTextoPlano(string $html): string
{
    if (preg_match('/<!--TXT(.*?)TXT-->/s', $html, $mm)) return trim($mm[1]);
    $t = preg_replace('#<(style|head)[^>]*>.*?</\1>#is', '', $html);
    $t = preg_replace('#<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', '$2: $1', $t);
    $t = preg_replace('#</(tr|p|h1|h2|div|table)>|<br\s*/?>#i', "\n", $t);
    $t = preg_replace('#</td>#i', '  ', $t);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8');
    $t = preg_replace("/[ \t]+\n/", "\n", $t);
    $t = preg_replace("/\n{3,}/", "\n\n", $t);
    return trim($t);
}

/* ============================================================
   Conexión SMTP
   ============================================================ */

function smtpLeer($fp): array
{
    $codigo = 0; $texto = ''; $i = 0;
    while (($linea = fgets($fp, 1500)) !== false && $i++ < 60) {
        $texto .= $linea;
        if (strlen($linea) >= 4 && $linea[3] === '-') { continue; }
        $codigo = (int)substr(ltrim($linea), 0, 3);
        break;
    }
    return ['codigo' => $codigo, 'texto' => trim($texto)];
}

function smtpEscribir($fp, string $datos): bool
{
    return @fwrite($fp, $datos . "\r\n") !== false;
}

/** Envía un correo HTML. Devuelve [bool ok, string mensaje]. */
/** Nombre que el sistema usa en el saludo EHLO: el dominio del servidor si es válido. */
function smtpNombreEhlo(): string
{
    // Nunca anunciar un dominio de hosting gratuito (unaux.com, etc.): aparece en la cabecera
    // "Received" y Gmail lo usa para juzgar el mensaje.
    $libres = ['unaux.com','epizy.com','ezyro.com','rf.gd','infinityfreeapp.com','byethost','wuaze.com','000webhostapp.com','free.nf','kesug.com','great-site.net','42web.io','lovestoblog.com','gt.tc'];
    foreach ([$_SERVER['SERVER_NAME'] ?? '', gethostname() ?: ''] as $n) {
        if ($n === '' || strpos($n, '.') === false || !preg_match('/^[A-Za-z0-9.-]+$/', $n)) continue;
        foreach ($libres as $l) if (stripos($n, $l) !== false) continue 2;
        return $n;
    }
    return 'localhost';
}

function smtpEnviar(array $c, string $para, string $asunto, string $html): array
{
    $host   = trim($c['host']);
    $puerto = (int)$c['puerto'];
    $seg    = strtolower($c['seguridad'] ?? 'tls');
    $tiempo = 12;

    $destino = ($seg === 'ssl' ? 'ssl://' : '') . $host . ':' . $puerto;
    $errno = 0; $errstr = '';
    $fp = @stream_socket_client($destino, $errno, $errstr, $tiempo, STREAM_CLIENT_CONNECT);
    if (!$fp) {
        return [false, "No se pudo conectar con {$destino}: " . ($errstr ?: "error {$errno}")];
    }
    stream_set_timeout($fp, $tiempo);

    $r = smtpLeer($fp);
    if ($r['codigo'] !== 220) { fclose($fp); return [false, 'Saludo del servidor: ' . $r['texto']]; }

    $ehlo = smtpNombreEhlo();
    smtpEscribir($fp, 'EHLO ' . $ehlo);
    $r = smtpLeer($fp);
    if ($r['codigo'] !== 250) { fclose($fp); return [false, 'EHLO: ' . $r['texto']]; }

    if ($seg === 'tls') {
        smtpEscribir($fp, 'STARTTLS');
        $r = smtpLeer($fp);
        if ($r['codigo'] !== 220) { fclose($fp); return [false, 'STARTTLS: ' . $r['texto']]; }
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($fp);
            return [false, 'No se pudo establecer la conexión cifrada (TLS).'];
        }
        smtpEscribir($fp, 'EHLO ' . $ehlo);
        $r = smtpLeer($fp);
        if ($r['codigo'] !== 250) { fclose($fp); return [false, 'EHLO (2): ' . $r['texto']]; }
    }

    smtpEscribir($fp, 'AUTH LOGIN');
    $r = smtpLeer($fp);
    if ($r['codigo'] !== 334) { fclose($fp); return [false, 'AUTH: ' . $r['texto']]; }

    smtpEscribir($fp, base64_encode($c['usuario']));
    $r = smtpLeer($fp);
    if ($r['codigo'] !== 334) { fclose($fp); return [false, 'Usuario SMTP: ' . $r['texto']]; }

    smtpEscribir($fp, base64_encode((string)$c['clave']));
    $r = smtpLeer($fp);
    if ($r['codigo'] !== 235) { fclose($fp); return [false, 'Contraseña SMTP rechazada: ' . $r['texto']]; }

    $de = $c['remitente_correo'] ?: $c['usuario'];
    smtpEscribir($fp, 'MAIL FROM:<' . $de . '>');
    $r = smtpLeer($fp);
    if ($r['codigo'] !== 250) { fclose($fp); return [false, 'MAIL FROM: ' . $r['texto']]; }

    smtpEscribir($fp, 'RCPT TO:<' . $para . '>');
    $r = smtpLeer($fp);
    if (!in_array($r['codigo'], [250, 251], true)) { fclose($fp); return [false, 'RCPT TO: ' . $r['texto']]; }

    smtpEscribir($fp, 'DATA');
    $r = smtpLeer($fp);
    if ($r['codigo'] !== 354) { fclose($fp); return [false, 'DATA: ' . $r['texto']]; }

    $nombreRem = (string)($c['remitente_nombre'] ?: 'SSR A365');
    $dominioDe = substr(strrchr($de, '@') ?: '@localhost', 1);
    $limite    = '=_ssr_' . bin2hex(random_bytes(10));
    $texto     = correoTextoPlano($html);

    $cabeceras  = 'From: =?UTF-8?B?' . base64_encode($nombreRem) . '?= <' . $de . ">\r\n";
    $cabeceras .= 'Reply-To: ' . $de . "\r\n";
    $cabeceras .= 'To: <' . $para . ">\r\n";
    $cabeceras .= 'Subject: =?UTF-8?B?' . base64_encode($asunto) . "?=\r\n";
    $cabeceras .= 'Date: ' . date('r') . "\r\n";
    $cabeceras .= 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $dominioDe . ">\r\n";
    $cabeceras .= "Auto-Submitted: auto-generated\r\n";
    $cabeceras .= "MIME-Version: 1.0\r\n";
    $cabeceras .= 'Content-Type: multipart/alternative; boundary="' . $limite . "\"\r\n";

    // Imágenes incrustadas (solo las que el HTML usa con cid:)
    $adjuntas = [];
    foreach (correoImagenesDisponibles() as $cid => $ruta) {
        if (strpos($html, 'cid:' . $cid) !== false) $adjuntas[$cid] = $ruta;
    }

    $cuerpo  = '--' . $limite . "\r\n";
    $cuerpo .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $cuerpo .= chunk_split(base64_encode($texto)) . "\r\n";
    $cuerpo .= '--' . $limite . "\r\n";
    if ($adjuntas) {
        $rel = '=_rel_' . bin2hex(random_bytes(8));
        $cuerpo .= 'Content-Type: multipart/related; type="text/html"; boundary="' . $rel . "\"\r\n\r\n";
        $cuerpo .= '--' . $rel . "\r\n";
        $cuerpo .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $cuerpo .= chunk_split(base64_encode($html)) . "\r\n";
        foreach ($adjuntas as $cid => $ruta) {
            $cuerpo .= '--' . $rel . "\r\n";
            $mime = strtolower(pathinfo($ruta, PATHINFO_EXTENSION)) === 'jpg' ? 'image/jpeg' : 'image/png';
            $cuerpo .= 'Content-Type: ' . $mime . '; name="' . basename($ruta) . "\"\r\n";
            $cuerpo .= "Content-Transfer-Encoding: base64\r\n";
            $cuerpo .= 'Content-ID: <' . $cid . ">\r\n";
            $cuerpo .= 'Content-Disposition: inline; filename="' . basename($ruta) . "\"\r\n\r\n";
            $cuerpo .= chunk_split(base64_encode((string)file_get_contents($ruta))) . "\r\n";
        }
        $cuerpo .= '--' . $rel . "--\r\n";
    } else {
        $cuerpo .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $cuerpo .= chunk_split(base64_encode($html)) . "\r\n";
    }
    $cuerpo .= '--' . $limite . "--\r\n";

    smtpEscribir($fp, $cabeceras . "\r\n" . $cuerpo . ".");
    $r = smtpLeer($fp);
    if ($r['codigo'] !== 250) { fclose($fp); return [false, 'Envío rechazado: ' . $r['texto']]; }

    smtpEscribir($fp, 'QUIT');
    fclose($fp);

    return [true, 'Correo enviado a ' . $para];
}

/**
 * Envía un correo y lo registra en el historial.
 * Devuelve [bool ok, string mensaje].
 */
function correoEnviar(PDO $pdo, string $para, string $asunto, string $html, ?int $usuarioId = null): array
{
    $para = trim($para);

    if ($para === '' || !filter_var($para, FILTER_VALIDATE_EMAIL)) {
        $msg = 'Correo destino no válido.';
        correoRegistrar($pdo, $usuarioId, $para, $asunto, $html, 'error', $msg);
        return [false, $msg];
    }

    $c = correoConfig($pdo);
    if (!correoListo($c)) {
        $msg = 'El SMTP no está configurado: ve a Administración → Correo y completa los datos.';
        correoRegistrar($pdo, $usuarioId, $para, $asunto, $html, 'no_configurado', $msg);
        return [false, $msg];
    }

    [$ok, $detalle] = smtpEnviar($c, $para, $asunto, $html);
    correoRegistrar($pdo, $usuarioId, $para, $asunto, $html, $ok ? 'enviado' : 'error', $detalle);

    return [$ok, $detalle];
}
