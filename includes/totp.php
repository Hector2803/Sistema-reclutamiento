<?php
/* ============================================================
   TOTP (RFC 6238) para el segundo factor de los administradores.
   Implementación propia con hash_hmac('sha1') + base32 (sin librerías).
   ============================================================ */

const TOTP_DIGITOS  = 6;
const TOTP_PERIODO  = 30;   // segundos
const TOTP_VENTANA  = 1;    // ±1 periodo de tolerancia (reloj desfasado)

/* ---- Base32 (RFC 4648, alfabeto A-Z2-7, sin relleno) ---- */
function base32Encode(string $datos): string
{
    $alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $salida = '';
    $buffer = 0;
    $bits = 0;
    for ($i = 0; $i < strlen($datos); $i++) {
        $buffer = ($buffer << 8) | ord($datos[$i]);
        $bits += 8;
        while ($bits >= 5) {
            $bits -= 5;
            $salida .= $alfabeto[($buffer >> $bits) & 0x1F];
        }
    }
    if ($bits > 0) {
        $salida .= $alfabeto[($buffer << (5 - $bits)) & 0x1F];
    }
    return $salida;
}

function base32Decode(string $texto): string
{
    $alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $texto = strtoupper(preg_replace('/[^A-Z2-7]/', '', $texto));
    $buffer = 0;
    $bits = 0;
    $salida = '';
    for ($i = 0; $i < strlen($texto); $i++) {
        $pos = strpos($alfabeto, $texto[$i]);
        if ($pos === false) continue;
        $buffer = ($buffer << 5) | $pos;
        $bits += 5;
        if ($bits >= 8) {
            $bits -= 8;
            $salida .= chr(($buffer >> $bits) & 0xFF);
        }
    }
    return $salida;
}

/* Secreto aleatorio de 20 bytes (160 bits) para la app de autenticación. */
function totpClaveNueva(): string
{
    return base32Encode(random_bytes(20));
}

/* Código TOTP de 6 dígitos para un momento dado. */
function totpCodigo(string $claveBase32, ?int $momento = null): string
{
    $momento = $momento ?? time();
    $contador = intdiv($momento, TOTP_PERIODO);

    $mensaje = pack('N2', 0, $contador);            // 8 bytes en big-endian
    $clave   = base32Decode($claveBase32);
    $hmac    = hash_hmac('sha1', $mensaje, $clave, true);

    $offset = ord($hmac[strlen($hmac) - 1]) & 0x0F;
    $binario = unpack('N', substr($hmac, $offset, 4))[1] & 0x7FFFFFFF;

    return str_pad((string)($binario % (10 ** TOTP_DIGITOS)), TOTP_DIGITOS, '0', STR_PAD_LEFT);
}

/* Valida el código ingresado aceptando ±1 periodo. */
function totpValido(string $claveBase32, string $codigo): bool
{
    $codigo = trim($codigo);
    if (!preg_match('/^\d{6}$/', $codigo)) return false;

    $ahora = time();
    for ($d = -TOTP_VENTANA; $d <= TOTP_VENTANA; $d++) {
        if (hash_equals(totpCodigo($claveBase32, $ahora + $d * TOTP_PERIODO), $codigo)) {
            return true;
        }
    }
    return false;
}

/* URL otpauth:// que las apps (Google Authenticator, Authy, 1Password) importan. */
function totpURL(string $claveBase32, string $cuenta, string $emisor = 'A365-Recruit'): string
{
    return 'otpauth://totp/' . rawurlencode($emisor) . ':' . rawurlencode($cuenta)
         . '?secret=' . $claveBase32
         . '&issuer=' . rawurlencode($emisor)
         . '&algorithm=SHA1&digits=' . TOTP_DIGITOS . '&period=' . TOTP_PERIODO;
}
