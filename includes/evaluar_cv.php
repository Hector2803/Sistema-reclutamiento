<?php
/* ============================================================
   SSR - A365 | Evaluación automática del CV (HU-05 / RF-05)
   - Extrae el texto del CV (PDF, DOCX o TXT)
   - Lo compara con las palabras clave del área
   - Devuelve un puntaje (%) y las coincidencias
   ============================================================ */

/* Normaliza texto: minúsculas y sin acentos, para comparar mejor */
function normalizarTexto(string $t): string {
    $t = mb_strtolower($t, 'UTF-8');
    $t = strtr($t, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
    $t = preg_replace('/\s+/', ' ', $t);
    return $t;
}

/* Extrae el texto de un CV según su extensión */
function extraerTextoCV(string $ruta): string {
    if (!is_file($ruta)) return '';
    $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));

    if ($ext === 'txt') {
        return (string)file_get_contents($ruta);
    }

    if ($ext === 'docx') {
        // DOCX = ZIP; el texto está en word/document.xml
        $xml = leerEntradaZip($ruta, 'word/document.xml');
        if ($xml === null) return '';
        $xml = str_replace(['</w:p>', '<w:br/>'], "\n", $xml);
        $texto = strip_tags($xml);
        return html_entity_decode($texto, ENT_QUOTES, 'UTF-8');
    }

    if ($ext === 'pdf') {
        return extraerTextoPDF($ruta);
    }

    return ''; // .doc antiguo u otros: no soportado de forma fiable
}

/*
   Lee un archivo interno de un ZIP (ej. word/document.xml de un DOCX).
   Usa ZipArchive si está disponible; si no (en XAMPP suele venir
   desactivada la extensión zip), lee el ZIP con PHP puro:
   busca el directorio central y descomprime con gzinflate().
*/
function leerEntradaZip(string $ruta, string $entrada): ?string {
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($ruta) === true) {
            $c = $zip->getFromName($entrada);
            $zip->close();
            return $c === false ? null : $c;
        }
        return null;
    }

    $data = @file_get_contents($ruta);
    if ($data === false) return null;

    // Fin del directorio central (firma PK\x05\x06)
    $eocd = strrpos($data, "PK\x05\x06");
    if ($eocd === false) return null;
    $info = unpack('vdisco/vdiscoDir/ventradasDisco/ventradas/Vtamano/Voffset', substr($data, $eocd + 4, 16));
    $pos = $info['offset'];

    for ($i = 0; $i < $info['entradas']; $i++) {
        if (substr($data, $pos, 4) !== "PK\x01\x02") return null;
        $h = unpack('vver/vnec/vflags/vmetodo/vhora/vfecha/Vcrc/Vcomp/Vorig/vlnom/vlextra/vlcom/vdisco/vint/Vext/Vlocal',
                    substr($data, $pos + 4, 42));
        $nombre = substr($data, $pos + 46, $h['lnom']);
        if ($nombre === $entrada) {
            // Cabecera local: calcular dónde empiezan los datos
            $l = unpack('vlnom/vlextra', substr($data, $h['local'] + 26, 4));
            $ini = $h['local'] + 30 + $l['lnom'] + $l['lextra'];
            $bruto = substr($data, $ini, $h['comp']);
            if ($h['metodo'] === 0) return $bruto;            // sin compresión
            if ($h['metodo'] === 8) {                          // deflate
                $x = @gzinflate($bruto);
                return $x === false ? null : $x;
            }
            return null;
        }
        $pos += 46 + $h['lnom'] + $h['lextra'] + $h['lcom'];
    }
    return null;
}

/* ============================================================
   Extracción de texto de PDF (PHP puro).
   Los PDF de Word/LibreOffice guardan códigos de glifos y una tabla
   ToUnicode por fuente; aquí se decodifican esas tablas.
   ============================================================ */
function ascii85Decode(string $s): string {
    $s = preg_replace('/\s+/', '', $s);
    if (substr($s, 0, 2) === '<~') $s = substr($s, 2);
    $p = strpos($s, '~>'); if ($p !== false) $s = substr($s, 0, $p);
    $out = ''; $grupo = [];
    for ($i = 0, $n = strlen($s); $i < $n; $i++) {
        if ($s[$i] === 'z' && !$grupo) { $out .= "\0\0\0\0"; continue; }
        $grupo[] = ord($s[$i]) - 33;
        if (count($grupo) === 5) {
            $v = 0; foreach ($grupo as $g) $v = $v * 85 + $g;
            $out .= pack('N', $v); $grupo = [];
        }
    }
    if ($grupo) {
        $k = count($grupo); while (count($grupo) < 5) $grupo[] = 84;
        $v = 0; foreach ($grupo as $g) $v = $v * 85 + $g;
        $out .= substr(pack('N', $v), 0, $k - 1);
    }
    return $out;
}

function pdfDecodificarStream(string $dict, string $raw): string {
    if (strpos($dict, 'ASCII85Decode') !== false) $raw = ascii85Decode($raw);
    if (strpos($dict, 'FlateDecode') !== false) {
        $x = @gzuncompress($raw);
        if ($x === false) $x = @gzinflate($raw);
        if ($x === false) $x = @gzinflate(substr($raw, 2));
        return $x === false ? '' : $x;
    }
    return $raw;
}

/* Devuelve [id => ['dict'=>..., 'stream'=>...]] incluyendo objetos dentro de ObjStm */
function pdfObjetos(string $data): array {
    $objs = [];
    preg_match_all('/(\d+)\s+\d+\s+obj\b(.*?)\bendobj/s', $data, $m, PREG_SET_ORDER);
    foreach ($m as $o) {
        $id = (int)$o[1]; $cuerpo = $o[2]; $stream = null; $dict = $cuerpo;
        if (preg_match('/^(.*?)\bstream\r?\n(.*?)\r?\n?endstream/s', $cuerpo, $sm)) {
            $dict = $sm[1];
            $stream = pdfDecodificarStream($dict, $sm[2]);
        }
        $objs[$id] = ['dict' => $dict, 'stream' => $stream];
        // Objetos comprimidos dentro de un Object Stream
        if ($stream !== null && preg_match('/\/Type\s*\/ObjStm/', $dict)
            && preg_match('/\/N\s+(\d+)/', $dict, $mn) && preg_match('/\/First\s+(\d+)/', $dict, $mf)) {
            $cab = preg_split('/\s+/', trim(substr($stream, 0, (int)$mf[1])));
            for ($i = 0; $i + 1 < count($cab) && $i / 2 < (int)$mn[1]; $i += 2) {
                $oid = (int)$cab[$i]; $off = (int)$mf[1] + (int)$cab[$i + 1];
                $sig = isset($cab[$i + 3]) ? (int)$mf[1] + (int)$cab[$i + 3] : strlen($stream);
                if (!isset($objs[$oid])) $objs[$oid] = ['dict' => substr($stream, $off, $sig - $off), 'stream' => null];
            }
        }
    }
    return $objs;
}

function utf16HexAUtf8(string $hex): string {
    $bin = @hex2bin(strlen($hex) % 2 ? '0' . $hex : $hex);
    return $bin === false ? '' : (string)mb_convert_encoding($bin, 'UTF-8', 'UTF-16BE');
}

/* Interpreta una CMap ToUnicode -> ['bytes'=>n, 'map'=>[codigo=>texto]] */
function pdfParseCMap(string $cm): array {
    $bytes = 1; $map = [];
    if (preg_match('/begincodespacerange\s*<([0-9A-Fa-f]+)>/', $cm, $cs)) $bytes = max(1, intdiv(strlen($cs[1]), 2));
    preg_match_all('/beginbfchar(.*?)endbfchar/s', $cm, $bl);
    foreach ($bl[1] as $b) {
        preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]*)>/', $b, $pp, PREG_SET_ORDER);
        foreach ($pp as $p) $map[hexdec($p[1])] = utf16HexAUtf8($p[2]);
    }
    preg_match_all('/beginbfrange(.*?)endbfrange/s', $cm, $bl);
    foreach ($bl[1] as $b) {
        preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*(<[0-9A-Fa-f]+>|\[[^\]]*\])/', $b, $pp, PREG_SET_ORDER);
        foreach ($pp as $p) {
            $lo = hexdec($p[1]); $hi = hexdec($p[2]);
            if ($hi - $lo > 5000) continue;
            if ($p[3][0] === '[') {
                preg_match_all('/<([0-9A-Fa-f]+)>/', $p[3], $arr);
                foreach ($arr[1] as $k => $h) $map[$lo + $k] = utf16HexAUtf8($h);
            } else {
                $base = trim($p[3], '<>');
                $pref = substr($base, 0, -4); $ult = hexdec(substr($base, -4));
                for ($c = $lo; $c <= $hi; $c++)
                    $map[$c] = utf16HexAUtf8($pref . str_pad(dechex($ult + $c - $lo), 4, '0', STR_PAD_LEFT));
            }
        }
    }
    return ['bytes' => $bytes, 'map' => $map];
}

function pdfDecodificarCadena(string $bin, ?array $cmap): string {
    if ($cmap && $cmap['map']) {
        $out = ''; $n = $cmap['bytes'];
        for ($i = 0; $i + $n <= strlen($bin); $i += $n) {
            $cod = hexdec(bin2hex(substr($bin, $i, $n)));
            $out .= $cmap['map'][$cod] ?? '';
        }
        return $out;
    }
    return (string)mb_convert_encoding($bin, 'UTF-8', 'Windows-1252');
}

function extraerTextoPDF(string $ruta): string {
    $data = @file_get_contents($ruta);
    if ($data === false) return '';
    $objs = pdfObjetos($data);

    // 1) Fuente (objeto) -> CMap
    $cmapPorObj = [];
    foreach ($objs as $id => $o) {
        if (preg_match('/\/ToUnicode\s+(\d+)\s+\d+\s+R/', $o['dict'], $tu) && isset($objs[(int)$tu[1]]['stream']))
            $cmapPorObj[$id] = pdfParseCMap($objs[(int)$tu[1]]['stream']);
    }
    // 2) Nombre de recurso (/F1) -> CMap
    $cmapPorNombre = [];
    foreach ($objs as $o) {
        $d = $o['dict'];
        if (preg_match('/\/Font\s+(\d+)\s+\d+\s+R/', $d, $fr) && isset($objs[(int)$fr[1]])) $d .= ' /Font ' . $objs[(int)$fr[1]]['dict'];
        if (preg_match_all('/\/Font\s*<<(.*?)>>/s', $d, $fd)) {
            foreach ($fd[1] as $bloque) {
                preg_match_all('/\/([^\s\/<>\[\]()]+)\s+(\d+)\s+\d+\s+R/', $bloque, $pares, PREG_SET_ORDER);
                foreach ($pares as $p) if (isset($cmapPorObj[(int)$p[2]])) $cmapPorNombre[$p[1]] = $cmapPorObj[(int)$p[2]];
            }
        }
    }
    // 3) Recorrer los streams de contenido
    $texto = '';
    foreach ($objs as $o) {
        $c = $o['stream'];
        if ($c === null || strpos($c, 'Tf') === false || !preg_match('/T[jJ]/', $c)) continue;
        if (strpos($c, 'begincmap') !== false) continue;
        $fuente = null;
        preg_match_all('/\/([^\s\/<>\[\]()]+)\s+[-\d.]+\s+Tf|<([0-9A-Fa-f\s]*)>|\(((?:\\\\.|[^\\\\()])*)\)|\b(T\*|Td|TD|Tm|ET)\b/s', $c, $tok, PREG_SET_ORDER);
        foreach ($tok as $t) {
            if ($t[1] !== '') { $fuente = $cmapPorNombre[$t[1]] ?? null; continue; }
            if (isset($t[4]) && $t[4] !== '') { $texto .= ' '; continue; }
            if ($t[2] !== '' || (isset($t[0][0]) && $t[0][0] === '<')) {
                $hex = preg_replace('/\s+/', '', $t[2]);
                if ($hex !== '') $texto .= pdfDecodificarCadena((string)@hex2bin(strlen($hex) % 2 ? $hex . '0' : $hex), $fuente);
                continue;
            }
            if (isset($t[3])) {
                $lit = preg_replace_callback('/\\\\([0-7]{1,3}|.)/s', function ($x) {
                    $e = $x[1];
                    if (ctype_digit($e)) return chr(octdec($e) & 255);
                    return ['n'=>"\n",'r'=>"\r",'t'=>"\t",'b'=>"\x08",'f'=>"\x0C"][$e] ?? $e;
                }, $t[3]);
                $texto .= pdfDecodificarCadena($lit, $fuente);
            }
        }
        $texto .= "\n";
    }
    return $texto;
}

/*
   Evalúa el texto del CV contra las palabras clave del área.
   Devuelve: ['puntaje'=>int 0-100, 'coincidencias'=>[palabras], 'total'=>n]
*/
function evaluarCV(string $textoCV, int $areaId, PDO $pdo): array {
    $stmt = $pdo->prepare("SELECT palabra, peso FROM palabras_clave WHERE area_id = :a");
    $stmt->execute([':a' => $areaId]);
    $claves = $stmt->fetchAll();

    if (!$claves) return ['puntaje'=>null, 'coincidencias'=>[], 'total'=>0];

    $texto = normalizarTexto($textoCV);
    $pesoTotal = 0; $pesoLogrado = 0; $encontradas = [];

    foreach ($claves as $c) {
        $pesoTotal += (int)$c['peso'];
        $palabra = normalizarTexto($c['palabra']);
        if ($palabra !== '' && str_contains($texto, $palabra)) {
            $pesoLogrado += (int)$c['peso'];
            $encontradas[] = $c['palabra'];
        }
    }

    $puntaje = $pesoTotal > 0 ? (int)round($pesoLogrado * 100 / $pesoTotal) : 0;
    return ['puntaje'=>$puntaje, 'coincidencias'=>$encontradas, 'total'=>count($claves)];
}
