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

/** Plantilla HTML base de los correos del sistema. */
function correoPlantilla(string $titulo, string $contenido, string $nota = ''): string
{
    $notaH = $nota !== '' ? '<p style="font-size:12.5px;color:#7a869a;margin-top:18px">' . $nota . '</p>' : '';
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;background:#f4f7fc;font-family:Arial,Helvetica,sans-serif;color:#1b2437">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:28px 14px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#fff;border:1px solid #e6eaf1;border-radius:14px;overflow:hidden">'
        . '<tr><td style="background:#0b3d91;padding:18px 24px"><span style="font-size:17px;font-weight:700;color:#fff">A365 · Reclutamiento</span></td></tr>'
        . '<tr><td style="padding:24px">'
        . '<h1 style="font-size:18px;margin:0 0 14px;color:#08306b">' . $titulo . '</h1>'
        . $contenido
        . $notaH
        . '</td></tr>'
        . '<tr><td style="background:#f4f7fc;padding:14px 24px;font-size:12px;color:#7a869a">Este mensaje fue enviado automáticamente por el sistema SSR - A365.</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** Cuerpo HTML con las credenciales de acceso. */
function correoCredenciales(string $nombre, string $cuenta, string $clave, bool $temporal = true): string
{
    $login = htmlspecialchars(correoUrlLogin(), ENT_QUOTES);
    $fila = fn(string $et, string $v) =>
        '<tr><td style="padding:7px 0;font-size:13px;color:#6b7793;width:140px">' . $et . '</td>'
        . '<td style="padding:7px 0;font-size:14px;font-weight:700;color:#1b2437">' . $v . '</td></tr>';

    $tabla = '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse">'
        . $fila('Nombre', htmlspecialchars($nombre, ENT_QUOTES))
        . $fila('Cuenta de acceso', htmlspecialchars($cuenta, ENT_QUOTES))
        . $fila($temporal ? 'Clave temporal' : 'Contraseña', htmlspecialchars($clave, ENT_QUOTES))
        . '</table>';

    $aviso = $temporal
        ? '<p style="font-size:13.5px;color:#1b2437;line-height:1.6;margin:16px 0 0">'
          . 'Esta clave es <strong>temporal</strong>: al iniciar sesión se te pedirá crear una contraseña nueva.</p>'
        : '';

    $btn = '<p style="margin:20px 0 0"><a href="' . $login . '" style="background:#0b3d91;color:#fff;text-decoration:none;'
        . 'display:inline-block;padding:11px 20px;border-radius:9px;font-size:14px;font-weight:700">Iniciar sesión</a></p>';

    return correoPlantilla(
        'Bienvenido, ' . htmlspecialchars($nombre, ENT_QUOTES),
        '<p style="font-size:13.5px;color:#44506a;line-height:1.6;margin:0 0 14px">'
        . 'Se creó tu cuenta en el sistema de reclutamiento. Ingresa con los siguientes datos:</p>'
        . $tabla . $aviso . $btn,
        'Por seguridad, no compartas estos datos. Si no solicitaste esta cuenta, contacta al administrador.'
    );
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

    smtpEscribir($fp, 'EHLO ' . (gethostname() ?: 'localhost'));
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
        smtpEscribir($fp, 'EHLO ' . (gethostname() ?: 'localhost'));
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
    $cabeceras  = 'From: =?UTF-8?B?' . base64_encode($nombreRem) . '?= <' . $de . ">\r\n";
    $cabeceras .= 'To: <' . $para . ">\r\n";
    $cabeceras .= 'Subject: =?UTF-8?B?' . base64_encode($asunto) . "?=\r\n";
    $cabeceras .= 'Date: ' . date('r') . "\r\n";
    $cabeceras .= 'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . (gethostname() ?: 'localhost') . ">\r\n";
    $cabeceras .= "MIME-Version: 1.0\r\n";
    $cabeceras .= "Content-Type: text/html; charset=UTF-8\r\n";
    $cabeceras .= "Content-Transfer-Encoding: base64\r\n";

    smtpEscribir($fp, $cabeceras . "\r\n" . chunk_split(base64_encode($html)) . "\r\n.");
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
