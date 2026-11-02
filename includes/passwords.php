<?php
/* ============================================================
   Política de contraseñas (CP-08 reforzado)
   Mínimo 10 caracteres, con mayúscula, minúscula, número y un carácter especial.
   Se usa en: alta de usuarios, cambio forzado y portal público.
   ============================================================ */

function validarClave(string $clave): ?string
{
    if ($clave === '')                         return 'La contraseña es obligatoria.';
    if (strlen($clave) < 10)                    return 'La contraseña debe tener al menos 10 caracteres.';
    if (!preg_match('/[A-Z]/', $clave))        return 'La contraseña debe incluir al menos una letra mayúscula.';
    if (!preg_match('/[a-z]/', $clave))        return 'La contraseña debe incluir al menos una letra minúscula.';
    if (!preg_match('/[0-9]/', $clave))        return 'La contraseña debe incluir al menos un número.';
    if (!preg_match('/[^A-Za-z0-9]/', $clave)) return 'La contraseña debe incluir al menos un carácter especial (por ejemplo ! @ # $ % & * ?).';
    return null;
}

/* Hashea con bcrypt y coste 12 (más lento de atacar que el coste 10 por defecto). */
function hashClave(string $clave): string
{
    return password_hash($clave, PASSWORD_BCRYPT, ['cost' => 12]);
}

/* Clave temporal aleatoria (regenerar clave / alta / importación).
   Siempre cumple la política: mayúscula, minúscula, número y especial; largo mínimo 10. */
function generarClaveTemporal(int $largo = 10): string
{
    $largo = max($largo, 10);
    $may = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $min = 'abcdefghjkmnpqrstuvwxyz';
    $dig = '23456789';
    $esp = '!@#$%&*?';
    $todo = $may . $min . $dig . $esp;
    $pick = fn(string $x) => $x[random_int(0, strlen($x) - 1)];

    do {
        $clave = $pick($may) . $pick($min) . $pick($dig) . $pick($esp);
        for ($i = 4; $i < $largo; $i++) $clave .= $pick($todo);
        $clave = str_shuffle($clave);
    } while (validarClave($clave) !== null);

    return $clave;
}
