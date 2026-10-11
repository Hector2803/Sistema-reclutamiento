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
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $ruta  = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    if ($ruta === '/' || $ruta === '.' || $ruta === '\\') { $ruta = ''; }
    if (substr($ruta, -8) === '/portal') { $ruta = substr($ruta, 0, -8); }
    return $https . '://' . $host . rtrim($ruta, '/') . '/login.php';
}

/**
 * ¿Conviene incluir el enlace de acceso en el correo?
 * Gmail bloquea ("mensaje sospechoso") los correos con enlaces HTTP o a dominios de
 * hosting gratuito. En esos casos se omite el enlace y se indica entrar desde la
 * dirección que ya conoce el usuario. Se puede forzar con
 * define('CORREO_INCLUIR_ENLACE', true|false) en config/sistema.php.
 */
function correoEnlaceSeguro(string $url): bool
{
    if (defined('CORREO_INCLUIR_ENLACE')) return (bool)CORREO_INCLUIR_ENLACE;
    if (stripos($url, 'https://') !== 0) return false;
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) return false;
    foreach (['unaux.com','epizy.com','ezyro.com','rf.gd','infinityfreeapp.com','byethost','wuaze.com','000webhostapp.com','free.nf','kesug.com','great-site.net'] as $mal) {
        if (strpos($host, $mal) !== false) return false;
    }
    return true;
}

/** Imágenes del diseño del correo (se envían incrustadas, con Content-ID). */
function correoImagenesDisponibles(): array
{
    $dir = __DIR__ . '/../assets/img/correo/';
    $r = [];
    foreach (['banner','pie','ico_sobre','ico_user','ico_mail','ico_lock','ico_info'] as $n) {
        if (is_file($dir . $n . '.png')) $r[$n . '@a365'] = $dir . $n . '.png';
    }
    return $r;
}

/** Plantilla HTML base de los correos del sistema (banner de bienvenida, cuerpo y pie con la marca A365). */
function correoPlantilla(string $titulo, string $contenido, string $nota = '', string $icono = 'ico_sobre'): string
{
    $img = fn(string $n, int $w, string $alt = '') =>
        '<img src="cid:' . $n . '@a365" width="' . $w . '" alt="' . $alt . '" style="display:block;border:0;outline:none;height:auto">';
    $notaH = $nota !== '' ? '<p style="font-size:12.5px;color:#7a869a;margin:18px 0 0;line-height:1.5">' . $nota . '</p>' : '';

    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;background:#f7fafd;font-family:Arial,Helvetica,sans-serif;color:#0f2447">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:22px 10px">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:100%;background:#ffffff;border-radius:14px">'
        . '<tr><td>' . $img('banner', 600, 'A365 · Bienvenido a nuestro equipo') . '</td></tr>'
        . '<tr><td style="padding:26px 30px 8px">'
        .   '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
        .   '<td width="86" valign="top" style="padding-right:16px">' . $img($icono, 70, '') . '</td>'
        .   '<td valign="top"><h1 style="font-size:24px;margin:2px 0 10px;color:#0b2a63">' . $titulo . '</h1>'
        .   $contenido . '</td></tr></table>'
        . '</td></tr>'
        . ($notaH !== '' ? '<tr><td style="padding:0 30px 6px">' . $notaH . '</td></tr>' : '')
        . '<tr><td style="padding:14px 30px 22px"><p style="margin:0;font-size:15px;font-weight:700;color:#0b2a63">¡Te deseamos mucho éxito en este nuevo reto!</p>'
        . '<p style="margin:2px 0 0;font-size:13.5px;color:#7a869a">Equipo de Reclutamiento A365</p></td></tr>'
        . '<tr><td>' . $img('pie', 600, 'A365 · Personas · Oportunidades · Crecimiento') . '</td></tr>'
        . '</table>'
        . '<p style="font-size:11.5px;color:#9aa6b8;margin:12px 0 0">Este mensaje fue enviado automáticamente por el sistema de reclutamiento de A365.</p>'
        . '</td></tr></table></body></html>';
}

/** Cuerpo HTML con las credenciales de acceso (cuenta nueva o contraseña restablecida). */
function correoCredenciales(string $nombre, string $cuenta, string $clave, bool $temporal = true, bool $reinicio = false): string
{
    $primer = trim(explode(' ', trim($nombre))[0] ?? $nombre);
    $e = fn(string $v) => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

    $fila = fn(string $ico, string $et, string $v) =>
        '<tr><td width="46" valign="middle" style="padding:8px 6px 8px 12px"><img src="cid:' . $ico . '@a365" width="40" alt="" style="display:block;border:0"></td>'
        . '<td valign="middle" style="padding:8px 8px 8px 6px"><div style="font-size:13px;color:#5b6b85">' . $et . ':</div>'
        . '<div style="font-size:16px;font-weight:700;color:#0b2a63;word-break:break-all">' . $v . '</div></td></tr>';

    $tabla = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f8fc;border-radius:12px;margin:14px 0 6px">'
        . $fila('ico_user', 'Nombre', $e($primer))
        . $fila('ico_mail', 'Cuenta de acceso', '<span style="color:#0b5ed7">' . $e($cuenta) . '</span>')
        . $fila('ico_lock', $temporal ? 'Clave temporal' : 'Contraseña', $e($clave))
        . '</table>';

    $intro = $reinicio
        ? '<p style="font-size:15px;color:#1b2f55;line-height:1.55;margin:0 0 4px">El administrador <strong>restableció tu contraseña</strong> del sistema de reclutamiento de A365.<br>A continuación te compartimos tus nuevos datos de acceso.</p>'
        : '<p style="font-size:15px;color:#1b2f55;line-height:1.55;margin:0 0 4px">¡Bienvenido a A365! 🎉<br>Tu cuenta en nuestro sistema de reclutamiento se ha creado correctamente.<br>A continuación te compartimos tus datos de acceso para que puedas ingresar.</p>';

    $login = correoUrlLogin();
    $ingreso = correoEnlaceSeguro($login)
        ? 'Para ingresar, usa el siguiente enlace: <a href="' . $e($login) . '" style="color:#0b5ed7;font-weight:700">Iniciar sesión</a>.'
        : 'Para ingresar, abre el sistema de reclutamiento desde la dirección que te indicó el administrador.';

    $aviso = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:14px"><tr>'
        . '<td width="40" valign="top"><img src="cid:ico_info@a365" width="30" alt="" style="display:block;border:0"></td>'
        . '<td valign="top" style="font-size:14px;color:#1b2f55;line-height:1.55;border-left:1px solid #dbe5f2;padding-left:14px">'
        . ($temporal ? 'Esta clave es <strong>temporal</strong>: al iniciar sesión se te pedirá crear una contraseña nueva.<br>' : '')
        . $ingreso . '</td></tr></table>';

    return correoPlantilla(
        'Hola, ' . $e($primer),
        $intro . $tabla . $aviso,
        'Por seguridad, no compartas estos datos. Si no esperabas este mensaje, contacta al administrador.'
    );
}

/** Versión en texto plano de un correo HTML (los filtros de spam penalizan los correos solo-HTML). */
function correoTextoPlano(string $html): string
{
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
    foreach ([$_SERVER['SERVER_NAME'] ?? '', gethostname() ?: ''] as $n) {
        if ($n !== '' && strpos($n, '.') !== false && preg_match('/^[A-Za-z0-9.-]+$/', $n)) return $n;
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
            $cuerpo .= 'Content-Type: image/png; name="' . basename($ruta) . "\"\r\n";
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
