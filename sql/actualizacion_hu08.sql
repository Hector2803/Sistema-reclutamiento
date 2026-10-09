SET NAMES utf8mb4;
-- ============================================================
--  SSR - A365 | ACTUALIZACIÓN HU-08 (Evaluaciones por área)
--  Ejecuta este archivo UNA vez en phpMyAdmin (pestaña SQL) sobre
--  la base ssr_a365. NO borra datos y se puede repetir sin duplicar.
--    1) Repara tildes corruptas (solo si la base se importó sin UTF-8)
--    2) Crea las evaluaciones de ATC, Ventas y Técnica
--    3) Carga un banco inicial de preguntas (20 ATC, 10 Ventas, 10 Técnica)
-- ============================================================
USE ssr_a365;

-- 1) Reparación de tildes (solo afecta textos con caracteres corruptos como 'Ã' o 'â€')
UPDATE areas SET nombre = CONVERT(CAST(CONVERT(nombre USING latin1) AS BINARY) USING utf8mb4) WHERE nombre COLLATE utf8mb4_bin LIKE '%Ã%' OR nombre COLLATE utf8mb4_bin LIKE '%â€%';
UPDATE areas SET descripcion = CONVERT(CAST(CONVERT(descripcion USING latin1) AS BINARY) USING utf8mb4) WHERE descripcion COLLATE utf8mb4_bin LIKE '%Ã%' OR descripcion COLLATE utf8mb4_bin LIKE '%â€%';
UPDATE etapas SET nombre = CONVERT(CAST(CONVERT(nombre USING latin1) AS BINARY) USING utf8mb4) WHERE nombre COLLATE utf8mb4_bin LIKE '%Ã%' OR nombre COLLATE utf8mb4_bin LIKE '%â€%';
UPDATE motivos_descarte SET descripcion = CONVERT(CAST(CONVERT(descripcion USING latin1) AS BINARY) USING utf8mb4) WHERE descripcion COLLATE utf8mb4_bin LIKE '%Ã%' OR descripcion COLLATE utf8mb4_bin LIKE '%â€%';
UPDATE origenes SET nombre = CONVERT(CAST(CONVERT(nombre USING latin1) AS BINARY) USING utf8mb4) WHERE nombre COLLATE utf8mb4_bin LIKE '%Ã%' OR nombre COLLATE utf8mb4_bin LIKE '%â€%';
UPDATE palabras_clave SET palabra = CONVERT(CAST(CONVERT(palabra USING latin1) AS BINARY) USING utf8mb4) WHERE palabra COLLATE utf8mb4_bin LIKE '%Ã%' OR palabra COLLATE utf8mb4_bin LIKE '%â€%';
UPDATE campanas SET puesto = CONVERT(CAST(CONVERT(puesto USING latin1) AS BINARY) USING utf8mb4) WHERE puesto COLLATE utf8mb4_bin LIKE '%Ã%' OR puesto COLLATE utf8mb4_bin LIKE '%â€%';
UPDATE campanas SET descripcion = CONVERT(CAST(CONVERT(descripcion USING latin1) AS BINARY) USING utf8mb4) WHERE descripcion COLLATE utf8mb4_bin LIKE '%Ã%' OR descripcion COLLATE utf8mb4_bin LIKE '%â€%';
UPDATE evaluaciones SET nombre = CONVERT(CAST(CONVERT(nombre USING latin1) AS BINARY) USING utf8mb4) WHERE nombre COLLATE utf8mb4_bin LIKE '%Ã%' OR nombre COLLATE utf8mb4_bin LIKE '%â€%';
UPDATE usuarios SET nombre = CONVERT(CAST(CONVERT(nombre USING latin1) AS BINARY) USING utf8mb4) WHERE nombre COLLATE utf8mb4_bin LIKE '%Ã%' OR nombre COLLATE utf8mb4_bin LIKE '%â€%';
UPDATE versiones_sistema SET descripcion = CONVERT(CAST(CONVERT(descripcion USING latin1) AS BINARY) USING utf8mb4) WHERE descripcion COLLATE utf8mb4_bin LIKE '%Ã%' OR descripcion COLLATE utf8mb4_bin LIKE '%â€%';
ALTER TABLE campanas MODIFY tipo VARCHAR(30) NOT NULL DEFAULT 'Nueva Posición';
UPDATE campanas SET tipo = 'Nueva Posición' WHERE tipo LIKE 'Nueva%';
ALTER TABLE campanas MODIFY tipo ENUM('Nueva Posición','Reemplazo') NOT NULL DEFAULT 'Nueva Posición';

-- 2) Evaluaciones por área (solo si no existen)
INSERT INTO evaluaciones (area_id, nombre, nota_minima, tiempo_limite, total_preguntas)
SELECT 1, 'Evaluación de Aptitud - Atención al Cliente', 70, 20, 20 FROM DUAL
WHERE EXISTS (SELECT 1 FROM areas WHERE id = 1) AND NOT EXISTS (SELECT 1 FROM evaluaciones WHERE area_id = 1);
INSERT INTO evaluaciones (area_id, nombre, nota_minima, tiempo_limite, total_preguntas)
SELECT 2, 'Evaluación de Aptitud Comercial - Ventas', 70, 20, 10 FROM DUAL
WHERE EXISTS (SELECT 1 FROM areas WHERE id = 2) AND NOT EXISTS (SELECT 1 FROM evaluaciones WHERE area_id = 2);
INSERT INTO evaluaciones (area_id, nombre, nota_minima, tiempo_limite, total_preguntas)
SELECT 3, 'Evaluación Técnica - Soporte', 70, 20, 10 FROM DUAL
WHERE EXISTS (SELECT 1 FROM areas WHERE id = 3) AND NOT EXISTS (SELECT 1 FROM evaluaciones WHERE area_id = 3);

-- 3) Banco inicial de preguntas (cada pregunta se inserta una sola vez)
SET @ev := (SELECT id FROM evaluaciones WHERE area_id = 1 ORDER BY id LIMIT 1);
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'Un cliente llama molesto por un cobro indebido. ¿Qué es lo primero que debes hacer?' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'Un cliente llama molesto por un cobro indebido. ¿Qué es lo primero que debes hacer?');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Escucharlo sin interrumpir y mostrar empatía' AS texto, 1 AS correcta, 0 AS pos UNION ALL SELECT 'Transferirlo a otra área de inmediato' AS texto, 0 AS correcta, 1 AS pos UNION ALL SELECT 'Explicarle que el sistema no se equivoca' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Pedirle que llame más tarde' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'Un cliente llama molesto por un cobro indebido. ¿Qué es lo primero que debes hacer?'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, '¿Qué significa brindar una solución en el primer contacto?' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = '¿Qué significa brindar una solución en el primer contacto?');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Atender en menos de un minuto' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Resolver la consulta del cliente sin que tenga que volver a llamar' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Derivar siempre al supervisor' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Registrar el caso sin resolverlo' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = '¿Qué significa brindar una solución en el primer contacto?'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'Si no conoces la respuesta a una consulta, lo correcto es:' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'Si no conoces la respuesta a una consulta, lo correcto es:');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Inventar una respuesta para no quedar mal' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Colgar la llamada' AS texto, 0 AS correcta, 1 AS pos UNION ALL SELECT 'Informar al cliente que verificarás la información y darle un tiempo de respuesta' AS texto, 1 AS correcta, 2 AS pos UNION ALL SELECT 'Decirle que no es tu responsabilidad' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'Si no conoces la respuesta a una consulta, lo correcto es:'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'La escucha activa consiste en:' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'La escucha activa consiste en:');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Hablar más que el cliente' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Prestar atención, confirmar lo entendido y responder a lo que el cliente necesita' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Tomar nota solo del número de documento' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Esperar a que el cliente termine para leer un guion' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'La escucha activa consiste en:'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, '¿Cuál es una buena práctica al cerrar una llamada?' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = '¿Cuál es una buena práctica al cerrar una llamada?');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Colgar apenas se resuelve el caso' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Resumir la solución y preguntar si hay algo más en que ayudar' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Pedir al cliente que califique mal la atención' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Dar el número personal del asesor' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = '¿Cuál es una buena práctica al cerrar una llamada?'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'Un cliente usa palabras ofensivas. ¿Cómo debes actuar?' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'Un cliente usa palabras ofensivas. ¿Cómo debes actuar?');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Responder con el mismo tono' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Mantener la calma, marcar límites con respeto y seguir el protocolo' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Colgar sin avisar' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Ignorar su consulta' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'Un cliente usa palabras ofensivas. ¿Cómo debes actuar?'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, '¿Por qué es importante registrar cada caso en el sistema?' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = '¿Por qué es importante registrar cada caso en el sistema?');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Para cumplir un trámite sin utilidad' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Para dar seguimiento y que cualquier asesor conozca el historial del cliente' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Para aumentar el tiempo de llamada' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'No es importante si el caso se resolvió' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = '¿Por qué es importante registrar cada caso en el sistema?'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'El indicador TMO (tiempo medio operativo) mide:' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'El indicador TMO (tiempo medio operativo) mide:');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'La cantidad de ventas del día' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'El tiempo promedio que dura la atención de un caso' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'El número de reclamos' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'La satisfacción del cliente' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'El indicador TMO (tiempo medio operativo) mide:'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, '¿Qué es un reclamo?' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = '¿Qué es un reclamo?');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Una consulta sobre precios' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'La expresión de insatisfacción del cliente respecto a un producto o servicio' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Una felicitación al asesor' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Una solicitud de baja voluntaria' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = '¿Qué es un reclamo?'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'Al validar la identidad de un cliente debes:' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'Al validar la identidad de un cliente debes:');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Pedir su contraseña bancaria' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Seguir el procedimiento de validación establecido por la empresa' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Aceptar cualquier nombre' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Omitir la validación si hay prisa' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'Al validar la identidad de un cliente debes:'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'Si el cliente habla muy rápido y no entiendes, lo adecuado es:' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'Si el cliente habla muy rápido y no entiendes, lo adecuado es:');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Adivinar lo que quiso decir' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Pedirle amablemente que repita la información' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Terminar la llamada' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Continuar sin confirmar' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'Si el cliente habla muy rápido y no entiendes, lo adecuado es:'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'La empatía en la atención al cliente significa:' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'La empatía en la atención al cliente significa:');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Estar de acuerdo con todo lo que dice el cliente' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Ponerse en el lugar del cliente y comprender su situación' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Hablar de problemas personales' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Prometer beneficios que no existen' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'La empatía en la atención al cliente significa:'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, '¿Qué debes hacer si un caso supera tus atribuciones?' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = '¿Qué debes hacer si un caso supera tus atribuciones?');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Resolverlo igual aunque no esté permitido' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Escalarlo al área o nivel correspondiente según el protocolo' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Pedir al cliente que lo olvide' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Cerrarlo como resuelto' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = '¿Qué debes hacer si un caso supera tus atribuciones?'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, '¿Cuál de estas frases transmite mejor profesionalismo?' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = '¿Cuál de estas frases transmite mejor profesionalismo?');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Eso no se puede, señor' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Entiendo su situación, voy a revisar las opciones disponibles para usted' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'No sé, llame otro día' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Ese no es mi problema' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = '¿Cuál de estas frases transmite mejor profesionalismo?'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'En la atención por chat, una buena práctica es:' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'En la atención por chat, una buena práctica es:');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Responder con abreviaturas y sin puntuación' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Escribir con claridad, buena ortografía y tono cordial' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Enviar respuestas muy largas sin orden' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Dejar al cliente esperando sin avisar' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'En la atención por chat, una buena práctica es:'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, '¿Qué información debe contener el registro de un reclamo?' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = '¿Qué información debe contener el registro de un reclamo?');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Solo el nombre del cliente' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Datos del cliente, descripción del problema, fecha y acciones realizadas' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Únicamente la fecha' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'La opinión personal del asesor' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = '¿Qué información debe contener el registro de un reclamo?'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'Si ofreciste devolver la llamada al cliente, debes:' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'Si ofreciste devolver la llamada al cliente, debes:');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Olvidarlo si hay mucho trabajo' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Cumplir con el compromiso en el tiempo indicado' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Esperar a que el cliente vuelva a llamar' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Pedir a un compañero que diga que no estás' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'Si ofreciste devolver la llamada al cliente, debes:'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'La satisfacción del cliente suele medirse con:' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'La satisfacción del cliente suele medirse con:');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Encuestas posteriores a la atención' AS texto, 1 AS correcta, 0 AS pos UNION ALL SELECT 'El número de llamadas perdidas' AS texto, 0 AS correcta, 1 AS pos UNION ALL SELECT 'La cantidad de asesores en turno' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'El horario de atención' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'La satisfacción del cliente suele medirse con:'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, '¿Qué actitud ayuda a mantener la calidad en un turno con muchas llamadas?' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = '¿Qué actitud ayuda a mantener la calidad en un turno con muchas llamadas?');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Atender rápido sin importar la solución' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Organizarse, mantener la calma y seguir los procedimientos' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Saltarse la validación de datos' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Transferir todas las llamadas' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = '¿Qué actitud ayuda a mantener la calidad en un turno con muchas llamadas?'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'La confidencialidad de los datos del cliente implica:' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'La confidencialidad de los datos del cliente implica:');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Compartir sus datos con amigos' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'No divulgar su información y usarla solo para la gestión' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Publicarlos en redes sociales' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Guardarlos en papeles sueltos' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'La confidencialidad de los datos del cliente implica:'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;

SET @ev := (SELECT id FROM evaluaciones WHERE area_id = 2 ORDER BY id LIMIT 1);
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, '¿Cuál es el primer paso de una venta consultiva?' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = '¿Cuál es el primer paso de una venta consultiva?');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Ofrecer el producto más caro' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Identificar las necesidades del cliente' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Dar un descuento inmediato' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Cerrar la venta sin preguntar' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = '¿Cuál es el primer paso de una venta consultiva?'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'Una objeción del cliente debe tratarse como:' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'Una objeción del cliente debe tratarse como:');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Un motivo para terminar la llamada' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Una oportunidad para aclarar dudas y reforzar beneficios' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Una ofensa personal' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Algo que se debe ignorar' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'Una objeción del cliente debe tratarse como:'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, '¿Qué significa cumplir la meta comercial?' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = '¿Qué significa cumplir la meta comercial?');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Atender muchas llamadas' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Alcanzar la cantidad de ventas o ingresos establecidos en el periodo' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Llegar temprano al trabajo' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Hablar con todos los clientes' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = '¿Qué significa cumplir la meta comercial?'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'En la retención de clientes, lo más efectivo es:' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'En la retención de clientes, lo más efectivo es:');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Aceptar la baja sin preguntar' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Conocer el motivo de la baja y ofrecer una alternativa de valor' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Presionar al cliente para que se quede' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Negar la solicitud de baja' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'En la retención de clientes, lo más efectivo es:'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'Un beneficio se diferencia de una característica porque:' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'Un beneficio se diferencia de una característica porque:');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'El beneficio explica cómo el producto resuelve una necesidad del cliente' AS texto, 1 AS correcta, 0 AS pos UNION ALL SELECT 'Son exactamente lo mismo' AS texto, 0 AS correcta, 1 AS pos UNION ALL SELECT 'La característica siempre es más importante' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'El beneficio es el precio' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'Un beneficio se diferencia de una característica porque:'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, '¿Qué técnica ayuda a cerrar una venta?' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = '¿Qué técnica ayuda a cerrar una venta?');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Resumir los beneficios y proponer el siguiente paso' AS texto, 1 AS correcta, 0 AS pos UNION ALL SELECT 'Repetir el precio muchas veces' AS texto, 0 AS correcta, 1 AS pos UNION ALL SELECT 'Hablar de otro tema' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Esperar a que el cliente decida solo' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = '¿Qué técnica ayuda a cerrar una venta?'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'La venta cruzada consiste en:' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'La venta cruzada consiste en:');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Vender el mismo producto dos veces' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Ofrecer productos complementarios al que el cliente adquiere' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Vender a la competencia' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Cambiar el producto sin avisar' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'La venta cruzada consiste en:'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'Si el cliente dice que el precio es alto, lo adecuado es:' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'Si el cliente dice que el precio es alto, lo adecuado es:');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Bajar el precio sin autorización' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Explicar el valor y los beneficios frente a su costo' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Colgar' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Decirle que busque otra empresa' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'Si el cliente dice que el precio es alto, lo adecuado es:'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, '¿Por qué es importante registrar cada gestión comercial?' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = '¿Por qué es importante registrar cada gestión comercial?');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Para dar seguimiento a las oportunidades y medir resultados' AS texto, 1 AS correcta, 0 AS pos UNION ALL SELECT 'No tiene importancia' AS texto, 0 AS correcta, 1 AS pos UNION ALL SELECT 'Solo para el supervisor' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Para ocupar tiempo' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = '¿Por qué es importante registrar cada gestión comercial?'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'La persuasión ética en ventas implica:' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'La persuasión ética en ventas implica:');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Engañar al cliente para vender' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Informar con veracidad y ayudar al cliente a decidir' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Prometer beneficios inexistentes' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Presionar hasta que acepte' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'La persuasión ética en ventas implica:'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;

SET @ev := (SELECT id FROM evaluaciones WHERE area_id = 3 ORDER BY id LIMIT 1);
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, '¿Qué es lo primero que se debe hacer ante una incidencia técnica?' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = '¿Qué es lo primero que se debe hacer ante una incidencia técnica?');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Formatear el equipo' AS texto, 0 AS correcta, 0 AS pos UNION ALL SELECT 'Identificar y registrar el problema reportado' AS texto, 1 AS correcta, 1 AS pos UNION ALL SELECT 'Reemplazar el hardware' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Cerrar el ticket' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = '¿Qué es lo primero que se debe hacer ante una incidencia técnica?'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'Un ticket de soporte sirve para:' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'Un ticket de soporte sirve para:');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Registrar, dar seguimiento y documentar una incidencia' AS texto, 1 AS correcta, 0 AS pos UNION ALL SELECT 'Enviar publicidad' AS texto, 0 AS correcta, 1 AS pos UNION ALL SELECT 'Reemplazar al correo personal' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Nada en particular' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'Un ticket de soporte sirve para:'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'Si un usuario no tiene internet, una verificación básica es:' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'Si un usuario no tiene internet, una verificación básica es:');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Revisar la conexión del cable o la red Wi-Fi' AS texto, 1 AS correcta, 0 AS pos UNION ALL SELECT 'Cambiar la placa madre' AS texto, 0 AS correcta, 1 AS pos UNION ALL SELECT 'Reinstalar el sistema operativo' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Comprar otro equipo' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'Si un usuario no tiene internet, una verificación básica es:'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, '¿Qué comando de Windows muestra la configuración IP?' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = '¿Qué comando de Windows muestra la configuración IP?');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'ipconfig' AS texto, 1 AS correcta, 0 AS pos UNION ALL SELECT 'format' AS texto, 0 AS correcta, 1 AS pos UNION ALL SELECT 'shutdown' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'notepad' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = '¿Qué comando de Windows muestra la configuración IP?'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'La diferencia entre hardware y software es:' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'La diferencia entre hardware y software es:');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'El hardware son componentes físicos y el software los programas' AS texto, 1 AS correcta, 0 AS pos UNION ALL SELECT 'Son lo mismo' AS texto, 0 AS correcta, 1 AS pos UNION ALL SELECT 'El software es el monitor' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'El hardware es el antivirus' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'La diferencia entre hardware y software es:'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'Antes de escalar una incidencia al segundo nivel debes:' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'Antes de escalar una incidencia al segundo nivel debes:');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Documentar las pruebas realizadas y el diagnóstico' AS texto, 1 AS correcta, 0 AS pos UNION ALL SELECT 'Escalar sin revisar' AS texto, 0 AS correcta, 1 AS pos UNION ALL SELECT 'Esperar a que el usuario lo olvide' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Cerrar el caso' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'Antes de escalar una incidencia al segundo nivel debes:'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, '¿Para qué sirve un antivirus?' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = '¿Para qué sirve un antivirus?');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Detectar y eliminar software malicioso' AS texto, 1 AS correcta, 0 AS pos UNION ALL SELECT 'Aumentar la velocidad de internet' AS texto, 0 AS correcta, 1 AS pos UNION ALL SELECT 'Imprimir documentos' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Crear usuarios' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = '¿Para qué sirve un antivirus?'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'Una buena práctica de seguridad para contraseñas es:' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'Una buena práctica de seguridad para contraseñas es:');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Usar contraseñas largas y no compartirlas' AS texto, 1 AS correcta, 0 AS pos UNION ALL SELECT 'Anotarlas en el monitor' AS texto, 0 AS correcta, 1 AS pos UNION ALL SELECT 'Usar la misma para todo' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Compartirlas con el equipo' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'Una buena práctica de seguridad para contraseñas es:'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, '¿Qué indica una dirección IP?' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = '¿Qué indica una dirección IP?');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'La identificación de un equipo dentro de una red' AS texto, 1 AS correcta, 0 AS pos UNION ALL SELECT 'El nombre del usuario' AS texto, 0 AS correcta, 1 AS pos UNION ALL SELECT 'La marca del equipo' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'La versión de Windows' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = '¿Qué indica una dirección IP?'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;
INSERT INTO preguntas (evaluacion_id, enunciado) SELECT @ev, 'Al atender a un usuario sin conocimientos técnicos, conviene:' FROM DUAL
  WHERE @ev IS NOT NULL AND NOT EXISTS (SELECT 1 FROM preguntas WHERE evaluacion_id = @ev AND enunciado = 'Al atender a un usuario sin conocimientos técnicos, conviene:');
INSERT INTO opciones (pregunta_id, texto, correcta)
  SELECT q.id, x.texto, x.correcta FROM preguntas q JOIN (SELECT 'Explicar con lenguaje claro y guiarlo paso a paso' AS texto, 1 AS correcta, 0 AS pos UNION ALL SELECT 'Usar términos técnicos complejos' AS texto, 0 AS correcta, 1 AS pos UNION ALL SELECT 'Burlarse de su desconocimiento' AS texto, 0 AS correcta, 2 AS pos UNION ALL SELECT 'Resolver sin avisarle' AS texto, 0 AS correcta, 3 AS pos) x
  WHERE q.evaluacion_id = @ev AND q.enunciado = 'Al atender a un usuario sin conocimientos técnicos, conviene:'
    AND NOT EXISTS (SELECT 1 FROM opciones o WHERE o.pregunta_id = q.id) ORDER BY x.pos;

-- ============================================================
--  LISTO. Revisa el menú Evaluaciones para ver y editar el banco.
-- ============================================================
