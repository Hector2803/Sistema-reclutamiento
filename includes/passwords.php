<?php
/* ============================================================
   Política de contraseñas (CP-08 reforzado)
   Mínimo 6 caracteres, con al menos una letra y un número.
   Se usa en: alta de usuarios, cambio forzado y portal público.
   ============================================================ */

function validarClave(string $clave): ?string
{
    if ($clave === '')                     return 'La contraseña es obligatoria.';
    if (strlen($clave) < 6)                return 'La contraseña debe tener al menos 6 caracteres.';
    if (!preg_match('/[A-Za-z]/', $clave)) return 'La contraseña debe incluir al menos una letra.';
    if (!preg_match('/[0-9]/', $clave))    return 'La contraseña debe incluir al menos un número.';
    return null;
}

/* Hashea con bcrypt y coste 12 (más lento de atacar que el coste 10 por defecto). */
function hashClave(string $clave): string
{
    return password_hash($clave, PASSWORD_BCRYPT, ['cost' => 12]);
}

/* Clave temporal aleatoria para "regenerar clave" del administrador.
   Siempre cumple la política: incluye letra y dígito, largo 10. */
function generarClaveTemporal(int $largo = 10): string
{
    $letras  = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';
    $digitos = '23456789';
    $todo     = $letras . $digitos;

    do {
        $clave = $letras[random_int(0, strlen($letras) - 1)]          // letra
               . $digitos[random_int(0, strlen($digitos) - 1)];       // número
        for ($i = 2; $i < $largo; $i++) {
            $clave .= $todo[random_int(0, strlen($todo) - 1)];
        }
        $clave = str_shuffle($clave);
    } while (validarClave($clave) !== null);

    return $clave;
}
