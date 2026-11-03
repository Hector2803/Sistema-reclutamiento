<?php
$tituloPagina = 'Centro de Ayuda';
$activo = 'ayuda';

require_once __DIR__ . '/includes/guardia.php';
require_once __DIR__ . '/includes/layout_top.php';
?>

<div class="panel-encabezado">
    <div>
        <h2>Centro de Ayuda</h2>
        <p>Encuentra respuestas a las consultas más frecuentes sobre el proceso de reclutamiento.</p>
    </div>
</div>

<section class="tarjeta" style="padding:25px; margin-bottom:20px;">
    <h3>1. ¿Cómo se calcula el puntaje del CV?</h3>
    <p>El sistema evalúa el currículum del candidato comparando su información con las palabras clave configuradas para el área de trabajo.</p>
    <p>Cada palabra clave tiene un peso asignado. El puntaje se calcula según el peso de las palabras encontradas respecto al peso total.</p>
    <p>Para que el CV sea considerado apto debe alcanzar como mínimo un 50% de coincidencia.</p>
</section>

<section class="tarjeta" style="padding:25px; margin-bottom:20px;">
    <h3>2. ¿Cuáles son las seis etapas del proceso de reclutamiento?</h3>
    <ol>
        <li>Postulación.</li>
        <li>Evaluación (test).</li>
        <li>Validación de datos, CV y documentos.</li>
        <li>Entrevista presencial.</li>
        <li>Capacitaciones.</li>
        <li>Proceso finalizado.</li>
    </ol>
    <p>El candidato debe cumplir las condiciones de cada etapa para poder avanzar en el proceso.</p>
</section>

<section class="tarjeta" style="padding:25px; margin-bottom:20px;">
    <h3>3. ¿Cuáles son los motivos de descarte?</h3>
    <p>Los motivos de descarte son las razones por las cuales un candidato no continúa en el proceso de selección.</p>
    <p>Al registrar un descarte, el reclutador debe seleccionar el motivo correspondiente del catálogo disponible en el sistema y registrar una observación cuando sea necesario.</p>
    <p>Es importante seleccionar el motivo correcto para mantener la información ordenada y generar reportes confiables.</p>
</section>

<?php require_once __DIR__ . '/includes/layout_bottom.php'; ?>
