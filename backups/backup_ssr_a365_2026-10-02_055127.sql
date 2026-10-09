-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: ssr_a365
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `areas`
--

DROP TABLE IF EXISTS `areas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `areas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `descripcion` varchar(200) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `areas`
--

LOCK TABLES `areas` WRITE;
/*!40000 ALTER TABLE `areas` DISABLE KEYS */;
INSERT INTO `areas` VALUES (1,'Atención al Cliente','Atención y soporte al cliente (SAC/ATC)',1,'2026-09-22 03:59:03'),(2,'Ventas','Ventas, retenciones y seguros',1,'2026-09-22 03:59:03'),(3,'Técnica','Soporte y operaciones técnicas',1,'2026-09-22 03:59:03'),(4,'Marketing','Campañas y contenidos',1,'2026-09-22 03:59:03'),(5,'Operaciones','Coordinación y operaciones logísticas',1,'2026-09-22 03:59:03');
/*!40000 ALTER TABLE `areas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blacklist`
--

DROP TABLE IF EXISTS `blacklist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blacklist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dni` varchar(15) NOT NULL,
  `motivo` varchar(200) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_blacklist_dni` (`dni`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blacklist`
--

LOCK TABLES `blacklist` WRITE;
/*!40000 ALTER TABLE `blacklist` DISABLE KEYS */;
/*!40000 ALTER TABLE `blacklist` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `campanas`
--

DROP TABLE IF EXISTS `campanas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `campanas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(20) NOT NULL,
  `area_id` int(11) NOT NULL,
  `puesto` varchar(120) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tareas` text DEFAULT NULL,
  `requisitos` text DEFAULT NULL,
  `beneficios` text DEFAULT NULL,
  `vacantes` int(11) NOT NULL DEFAULT 1,
  `ubicacion` varchar(120) DEFAULT NULL,
  `modalidad` enum('Presencial','Híbrido','Remoto') NOT NULL DEFAULT 'Presencial',
  `horario` varchar(120) DEFAULT NULL,
  `remuneracion` decimal(10,2) DEFAULT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `prioridad` enum('Alta','Media','Baja') NOT NULL DEFAULT 'Media',
  `tipo` enum('Nueva Posición','Reemplazo') NOT NULL DEFAULT 'Nueva Posición',
  `estado` enum('Abierto','En Proceso','Cerrado') NOT NULL DEFAULT 'Abierto',
  `publicado` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_sol` date DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `fk_campana_area` (`area_id`),
  CONSTRAINT `fk_campana_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `campanas`
--

LOCK TABLES `campanas` WRITE;
/*!40000 ALTER TABLE `campanas` DISABLE KEYS */;
INSERT INTO `campanas` VALUES (1,'REQ-2026-001',1,'SAC – Asesora de Atención al Cliente','Buscamos talento para brindar soporte y atención al cliente por canales digitales y telefónicos, resolviendo consultas con calidad, rapidez y enfoque en la experiencia del usuario.','Atender consultas, solicitudes y reclamos por teléfono, chat y correo.\nRegistrar casos en el sistema y dar seguimiento oportuno.\nBrindar información clara sobre servicios, procesos y soluciones.\nCumplir indicadores de servicio, calidad y tiempos de respuesta.\nEscalar incidencias según el protocolo de atención.','Experiencia previa en call center, SAC o servicio al cliente.\nManejo básico de herramientas digitales y sistemas de registro.\nComunicación clara, redacción correcta y orientación al servicio.\nDisponibilidad para laborar en sede.\nSecundaria completa o estudios técnicos en curso o concluidos.','Ingreso a planilla según políticas de la empresa.\nCapacitación constante en atención al cliente y gestión SAC.\nBuen clima laboral y acompañamiento del equipo.\nOportunidades de desarrollo y línea de carrera.',5,'Magdalena, Lima','Presencial','Full time rotativo',1130.00,'2026-02-01','Alta','Nueva Posición','Abierto',1,'2026-01-10','2026-09-22 03:59:03');
/*!40000 ALTER TABLE `campanas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `entrevistas`
--

DROP TABLE IF EXISTS `entrevistas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `entrevistas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `postulacion_id` int(11) NOT NULL,
  `entrevistador_id` int(11) DEFAULT NULL,
  `entrevistador` varchar(120) NOT NULL,
  `rol_entrevistador` varchar(80) DEFAULT NULL,
  `tipo` varchar(60) NOT NULL DEFAULT 'Entrevista RH',
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `sala` varchar(80) DEFAULT NULL,
  `estado` enum('Programada','En Curso','Completada','Cancelada') NOT NULL DEFAULT 'Programada',
  `observaciones` varchar(255) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_entrevistas_fecha` (`fecha`),
  KEY `idx_entrevistas_estado` (`estado`),
  KEY `fk_ent_postulacion` (`postulacion_id`),
  KEY `fk_ent_usuario` (`entrevistador_id`),
  CONSTRAINT `fk_ent_postulacion` FOREIGN KEY (`postulacion_id`) REFERENCES `postulaciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ent_usuario` FOREIGN KEY (`entrevistador_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `entrevistas`
--

LOCK TABLES `entrevistas` WRITE;
/*!40000 ALTER TABLE `entrevistas` DISABLE KEYS */;
/*!40000 ALTER TABLE `entrevistas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `etapas`
--

DROP TABLE IF EXISTS `etapas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `etapas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  `orden` int(11) NOT NULL,
  `color` varchar(20) DEFAULT '#2f6fed',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `etapas`
--

LOCK TABLES `etapas` WRITE;
/*!40000 ALTER TABLE `etapas` DISABLE KEYS */;
INSERT INTO `etapas` VALUES (1,'Postulación',1,'#2f6fed'),(2,'Validación (SARA)',2,'#3b82f6'),(3,'Contacto telefónico',3,'#7c4ddb'),(4,'Entrevista / Role Play',4,'#f5a623'),(5,'Acepta propuesta',5,'#0ea5a4'),(6,'Evaluación (test)',6,'#22a06b'),(7,'Antecedentes',7,'#6366f1'),(8,'Ingresa',8,'#16a34a');
/*!40000 ALTER TABLE `etapas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `evaluaciones`
--

DROP TABLE IF EXISTS `evaluaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evaluaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `area_id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `nota_minima` int(11) NOT NULL DEFAULT 70,
  `tiempo_limite` int(11) NOT NULL DEFAULT 20,
  `total_preguntas` int(11) NOT NULL DEFAULT 20,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_eval_area` (`area_id`),
  CONSTRAINT `fk_eval_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evaluaciones`
--

LOCK TABLES `evaluaciones` WRITE;
/*!40000 ALTER TABLE `evaluaciones` DISABLE KEYS */;
INSERT INTO `evaluaciones` VALUES (1,1,'Evaluación de Aptitud - Atención al Cliente',70,20,20,1),(2,2,'Evaluación de Aptitud Comercial - Ventas',70,20,10,1),(3,3,'Evaluación Técnica - Soporte',70,20,10,1);
/*!40000 ALTER TABLE `evaluaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historial_backups`
--

DROP TABLE IF EXISTS `historial_backups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `historial_backups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `archivo` varchar(200) NOT NULL,
  `tamano_kb` int(11) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_bk_user` (`usuario_id`),
  CONSTRAINT `fk_bk_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historial_backups`
--

LOCK TABLES `historial_backups` WRITE;
/*!40000 ALTER TABLE `historial_backups` DISABLE KEYS */;
/*!40000 ALTER TABLE `historial_backups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historial_etapas`
--

DROP TABLE IF EXISTS `historial_etapas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `historial_etapas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `postulacion_id` int(11) NOT NULL,
  `etapa_origen` int(11) DEFAULT NULL,
  `etapa_destino` int(11) NOT NULL,
  `motivo_id` int(11) DEFAULT NULL,
  `observacion` varchar(255) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_hist_post` (`postulacion_id`),
  KEY `fk_hist_orig` (`etapa_origen`),
  KEY `fk_hist_dest` (`etapa_destino`),
  KEY `fk_hist_motivo` (`motivo_id`),
  KEY `fk_hist_user` (`usuario_id`),
  CONSTRAINT `fk_hist_dest` FOREIGN KEY (`etapa_destino`) REFERENCES `etapas` (`id`),
  CONSTRAINT `fk_hist_motivo` FOREIGN KEY (`motivo_id`) REFERENCES `motivos_descarte` (`id`),
  CONSTRAINT `fk_hist_orig` FOREIGN KEY (`etapa_origen`) REFERENCES `etapas` (`id`),
  CONSTRAINT `fk_hist_post` FOREIGN KEY (`postulacion_id`) REFERENCES `postulaciones` (`id`),
  CONSTRAINT `fk_hist_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historial_etapas`
--

LOCK TABLES `historial_etapas` WRITE;
/*!40000 ALTER TABLE `historial_etapas` DISABLE KEYS */;
INSERT INTO `historial_etapas` VALUES (5,3,NULL,1,NULL,NULL,NULL,'2026-10-02 03:49:14'),(6,4,NULL,1,NULL,NULL,NULL,'2026-10-02 03:50:13');
/*!40000 ALTER TABLE `historial_etapas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historial_restauraciones`
--

DROP TABLE IF EXISTS `historial_restauraciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `historial_restauraciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `archivo` varchar(200) NOT NULL,
  `resultado` enum('Exitosa','Fallida') NOT NULL DEFAULT 'Exitosa',
  `detalle` varchar(255) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_rs_user` (`usuario_id`),
  CONSTRAINT `fk_rs_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historial_restauraciones`
--

LOCK TABLES `historial_restauraciones` WRITE;
/*!40000 ALTER TABLE `historial_restauraciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `historial_restauraciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `motivos_descarte`
--

DROP TABLE IF EXISTS `motivos_descarte`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `motivos_descarte` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `descripcion` varchar(120) NOT NULL,
  `etapa_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_motivo_etapa` (`etapa_id`),
  CONSTRAINT `fk_motivo_etapa` FOREIGN KEY (`etapa_id`) REFERENCES `etapas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `motivos_descarte`
--

LOCK TABLES `motivos_descarte` WRITE;
/*!40000 ALTER TABLE `motivos_descarte` DISABLE KEYS */;
INSERT INTO `motivos_descarte` VALUES (1,'En blacklist',2),(2,'Activo en otra campaña',2),(3,'Postulación reiterada',2),(4,'No interesado por remuneración',3),(5,'No interesado por horario',3),(6,'No interesado por distancia / ubicación',3),(7,'No contesta / número equivocado',3),(8,'No cumple el perfil',4),(9,'Competencias comunicativas insuficientes',4),(10,'No asistió a la entrevista',4),(11,'Rechaza la propuesta',5),(12,'No alcanzó el puntaje mínimo (70%)',6),(13,'Observación en antecedentes',7),(14,'Desistió del proceso',NULL);
/*!40000 ALTER TABLE `motivos_descarte` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `opciones`
--

DROP TABLE IF EXISTS `opciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `opciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pregunta_id` int(11) NOT NULL,
  `texto` varchar(300) NOT NULL,
  `correcta` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_opcion_preg` (`pregunta_id`),
  CONSTRAINT `fk_opcion_preg` FOREIGN KEY (`pregunta_id`) REFERENCES `preguntas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=280 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `opciones`
--

LOCK TABLES `opciones` WRITE;
/*!40000 ALTER TABLE `opciones` DISABLE KEYS */;
INSERT INTO `opciones` VALUES (1,1,'Escucharlo sin interrumpir y mostrar empatía',1),(2,1,'Transferirlo a otra área de inmediato',0),(3,1,'Explicarle que el sistema no se equivoca',0),(4,1,'Pedirle que llame más tarde',0),(8,2,'Atender en menos de un minuto',0),(9,2,'Resolver la consulta del cliente sin que tenga que volver a llamar',1),(10,2,'Derivar siempre al supervisor',0),(11,2,'Registrar el caso sin resolverlo',0),(15,3,'Inventar una respuesta para no quedar mal',0),(16,3,'Colgar la llamada',0),(17,3,'Informar al cliente que verificarás la información y darle un tiempo de respuesta',1),(18,3,'Decirle que no es tu responsabilidad',0),(22,4,'Hablar más que el cliente',0),(23,4,'Prestar atención, confirmar lo entendido y responder a lo que el cliente necesita',1),(24,4,'Tomar nota solo del número de documento',0),(25,4,'Esperar a que el cliente termine para leer un guion',0),(29,5,'Colgar apenas se resuelve el caso',0),(30,5,'Resumir la solución y preguntar si hay algo más en que ayudar',1),(31,5,'Pedir al cliente que califique mal la atención',0),(32,5,'Dar el número personal del asesor',0),(36,6,'Responder con el mismo tono',0),(37,6,'Mantener la calma, marcar límites con respeto y seguir el protocolo',1),(38,6,'Colgar sin avisar',0),(39,6,'Ignorar su consulta',0),(43,7,'Para cumplir un trámite sin utilidad',0),(44,7,'Para dar seguimiento y que cualquier asesor conozca el historial del cliente',1),(45,7,'Para aumentar el tiempo de llamada',0),(46,7,'No es importante si el caso se resolvió',0),(50,8,'La cantidad de ventas del día',0),(51,8,'El tiempo promedio que dura la atención de un caso',1),(52,8,'El número de reclamos',0),(53,8,'La satisfacción del cliente',0),(57,9,'Una consulta sobre precios',0),(58,9,'La expresión de insatisfacción del cliente respecto a un producto o servicio',1),(59,9,'Una felicitación al asesor',0),(60,9,'Una solicitud de baja voluntaria',0),(64,10,'Pedir su contraseña bancaria',0),(65,10,'Seguir el procedimiento de validación establecido por la empresa',1),(66,10,'Aceptar cualquier nombre',0),(67,10,'Omitir la validación si hay prisa',0),(71,11,'Adivinar lo que quiso decir',0),(72,11,'Pedirle amablemente que repita la información',1),(73,11,'Terminar la llamada',0),(74,11,'Continuar sin confirmar',0),(78,12,'Estar de acuerdo con todo lo que dice el cliente',0),(79,12,'Ponerse en el lugar del cliente y comprender su situación',1),(80,12,'Hablar de problemas personales',0),(81,12,'Prometer beneficios que no existen',0),(85,13,'Resolverlo igual aunque no esté permitido',0),(86,13,'Escalarlo al área o nivel correspondiente según el protocolo',1),(87,13,'Pedir al cliente que lo olvide',0),(88,13,'Cerrarlo como resuelto',0),(92,14,'Eso no se puede, señor',0),(93,14,'Entiendo su situación, voy a revisar las opciones disponibles para usted',1),(94,14,'No sé, llame otro día',0),(95,14,'Ese no es mi problema',0),(99,15,'Responder con abreviaturas y sin puntuación',0),(100,15,'Escribir con claridad, buena ortografía y tono cordial',1),(101,15,'Enviar respuestas muy largas sin orden',0),(102,15,'Dejar al cliente esperando sin avisar',0),(106,16,'Solo el nombre del cliente',0),(107,16,'Datos del cliente, descripción del problema, fecha y acciones realizadas',1),(108,16,'Únicamente la fecha',0),(109,16,'La opinión personal del asesor',0),(113,17,'Olvidarlo si hay mucho trabajo',0),(114,17,'Cumplir con el compromiso en el tiempo indicado',1),(115,17,'Esperar a que el cliente vuelva a llamar',0),(116,17,'Pedir a un compañero que diga que no estás',0),(120,18,'Encuestas posteriores a la atención',1),(121,18,'El número de llamadas perdidas',0),(122,18,'La cantidad de asesores en turno',0),(123,18,'El horario de atención',0),(127,19,'Atender rápido sin importar la solución',0),(128,19,'Organizarse, mantener la calma y seguir los procedimientos',1),(129,19,'Saltarse la validación de datos',0),(130,19,'Transferir todas las llamadas',0),(134,20,'Compartir sus datos con amigos',0),(135,20,'No divulgar su información y usarla solo para la gestión',1),(136,20,'Publicarlos en redes sociales',0),(137,20,'Guardarlos en papeles sueltos',0),(141,21,'Ofrecer el producto más caro',0),(142,21,'Identificar las necesidades del cliente',1),(143,21,'Dar un descuento inmediato',0),(144,21,'Cerrar la venta sin preguntar',0),(148,22,'Un motivo para terminar la llamada',0),(149,22,'Una oportunidad para aclarar dudas y reforzar beneficios',1),(150,22,'Una ofensa personal',0),(151,22,'Algo que se debe ignorar',0),(155,23,'Atender muchas llamadas',0),(156,23,'Alcanzar la cantidad de ventas o ingresos establecidos en el periodo',1),(157,23,'Llegar temprano al trabajo',0),(158,23,'Hablar con todos los clientes',0),(162,24,'Aceptar la baja sin preguntar',0),(163,24,'Conocer el motivo de la baja y ofrecer una alternativa de valor',1),(164,24,'Presionar al cliente para que se quede',0),(165,24,'Negar la solicitud de baja',0),(169,25,'El beneficio explica cómo el producto resuelve una necesidad del cliente',1),(170,25,'Son exactamente lo mismo',0),(171,25,'La característica siempre es más importante',0),(172,25,'El beneficio es el precio',0),(176,26,'Resumir los beneficios y proponer el siguiente paso',1),(177,26,'Repetir el precio muchas veces',0),(178,26,'Hablar de otro tema',0),(179,26,'Esperar a que el cliente decida solo',0),(183,27,'Vender el mismo producto dos veces',0),(184,27,'Ofrecer productos complementarios al que el cliente adquiere',1),(185,27,'Vender a la competencia',0),(186,27,'Cambiar el producto sin avisar',0),(190,28,'Bajar el precio sin autorización',0),(191,28,'Explicar el valor y los beneficios frente a su costo',1),(192,28,'Colgar',0),(193,28,'Decirle que busque otra empresa',0),(197,29,'Para dar seguimiento a las oportunidades y medir resultados',1),(198,29,'No tiene importancia',0),(199,29,'Solo para el supervisor',0),(200,29,'Para ocupar tiempo',0),(204,30,'Engañar al cliente para vender',0),(205,30,'Informar con veracidad y ayudar al cliente a decidir',1),(206,30,'Prometer beneficios inexistentes',0),(207,30,'Presionar hasta que acepte',0),(211,31,'Formatear el equipo',0),(212,31,'Identificar y registrar el problema reportado',1),(213,31,'Reemplazar el hardware',0),(214,31,'Cerrar el ticket',0),(218,32,'Registrar, dar seguimiento y documentar una incidencia',1),(219,32,'Enviar publicidad',0),(220,32,'Reemplazar al correo personal',0),(221,32,'Nada en particular',0),(225,33,'Revisar la conexión del cable o la red Wi-Fi',1),(226,33,'Cambiar la placa madre',0),(227,33,'Reinstalar el sistema operativo',0),(228,33,'Comprar otro equipo',0),(232,34,'ipconfig',1),(233,34,'format',0),(234,34,'shutdown',0),(235,34,'notepad',0),(239,35,'El hardware son componentes físicos y el software los programas',1),(240,35,'Son lo mismo',0),(241,35,'El software es el monitor',0),(242,35,'El hardware es el antivirus',0),(246,36,'Documentar las pruebas realizadas y el diagnóstico',1),(247,36,'Escalar sin revisar',0),(248,36,'Esperar a que el usuario lo olvide',0),(249,36,'Cerrar el caso',0),(253,37,'Detectar y eliminar software malicioso',1),(254,37,'Aumentar la velocidad de internet',0),(255,37,'Imprimir documentos',0),(256,37,'Crear usuarios',0),(260,38,'Usar contraseñas largas y no compartirlas',1),(261,38,'Anotarlas en el monitor',0),(262,38,'Usar la misma para todo',0),(263,38,'Compartirlas con el equipo',0),(267,39,'La identificación de un equipo dentro de una red',1),(268,39,'El nombre del usuario',0),(269,39,'La marca del equipo',0),(270,39,'La versión de Windows',0),(274,40,'Explicar con lenguaje claro y guiarlo paso a paso',1),(275,40,'Usar términos técnicos complejos',0),(276,40,'Burlarse de su desconocimiento',0),(277,40,'Resolver sin avisarle',0);
/*!40000 ALTER TABLE `opciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `origenes`
--

DROP TABLE IF EXISTS `origenes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `origenes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `origenes`
--

LOCK TABLES `origenes` WRITE;
/*!40000 ALTER TABLE `origenes` DISABLE KEYS */;
INSERT INTO `origenes` VALUES (7,'Base anterior'),(2,'Computrabajo'),(3,'PAND PE'),(1,'Portal web'),(4,'Redes sociales'),(5,'Referido'),(6,'Trabajo de campo');
/*!40000 ALTER TABLE `origenes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `palabras_clave`
--

DROP TABLE IF EXISTS `palabras_clave`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `palabras_clave` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `area_id` int(11) NOT NULL,
  `palabra` varchar(60) NOT NULL,
  `peso` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `fk_pc_area` (`area_id`),
  CONSTRAINT `fk_pc_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `palabras_clave`
--

LOCK TABLES `palabras_clave` WRITE;
/*!40000 ALTER TABLE `palabras_clave` DISABLE KEYS */;
INSERT INTO `palabras_clave` VALUES (1,1,'atención al cliente',2),(2,1,'call center',2),(3,1,'servicio',1),(4,1,'comunicación',1),(5,1,'reclamos',1),(6,1,'excel',1),(7,2,'ventas',2),(8,2,'comercial',2),(9,2,'negociación',1),(10,2,'metas',1),(11,2,'clientes',1),(12,2,'retención',1),(13,3,'soporte',2),(14,3,'técnico',2),(15,3,'hardware',1),(16,3,'software',1),(17,3,'redes',1),(18,3,'windows',1);
/*!40000 ALTER TABLE `palabras_clave` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `postulaciones`
--

DROP TABLE IF EXISTS `postulaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `postulaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `postulante_id` int(11) NOT NULL,
  `campana_id` int(11) NOT NULL,
  `etapa_id` int(11) NOT NULL,
  `reclutador_id` int(11) DEFAULT NULL,
  `origen_id` int(11) DEFAULT NULL,
  `codigo_seguimiento` varchar(20) NOT NULL,
  `estado` enum('En Proceso','Seleccionado','Descartado') NOT NULL DEFAULT 'En Proceso',
  `fecha_postulacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `puntaje_cv` int(11) DEFAULT NULL,
  `cv_detalle` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_seguimiento` (`codigo_seguimiento`),
  UNIQUE KEY `uq_postulante_campana` (`postulante_id`,`campana_id`),
  KEY `fk_post_campana` (`campana_id`),
  KEY `fk_post_etapa` (`etapa_id`),
  KEY `fk_post_reclutador` (`reclutador_id`),
  KEY `fk_post_origen` (`origen_id`),
  CONSTRAINT `fk_post_campana` FOREIGN KEY (`campana_id`) REFERENCES `campanas` (`id`),
  CONSTRAINT `fk_post_etapa` FOREIGN KEY (`etapa_id`) REFERENCES `etapas` (`id`),
  CONSTRAINT `fk_post_origen` FOREIGN KEY (`origen_id`) REFERENCES `origenes` (`id`),
  CONSTRAINT `fk_post_postulante` FOREIGN KEY (`postulante_id`) REFERENCES `postulantes` (`id`),
  CONSTRAINT `fk_post_reclutador` FOREIGN KEY (`reclutador_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `postulaciones`
--

LOCK TABLES `postulaciones` WRITE;
/*!40000 ALTER TABLE `postulaciones` DISABLE KEYS */;
INSERT INTO `postulaciones` VALUES (3,3,1,1,NULL,1,'A365-7C477','En Proceso','2026-10-02 03:49:14',NULL,NULL),(4,4,1,1,NULL,1,'A365-243FD','En Proceso','2026-10-02 03:50:13',50,'atención al cliente, comunicación, excel');
/*!40000 ALTER TABLE `postulaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `postulantes`
--

DROP TABLE IF EXISTS `postulantes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `postulantes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dni` varchar(15) NOT NULL,
  `nombres` varchar(120) NOT NULL,
  `correo` varchar(120) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `distrito` varchar(80) DEFAULT NULL,
  `clave` varchar(255) DEFAULT NULL,
  `cv_ruta` varchar(255) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `dni` (`dni`),
  KEY `idx_postulante_dni` (`dni`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `postulantes`
--

LOCK TABLES `postulantes` WRITE;
/*!40000 ALTER TABLE `postulantes` DISABLE KEYS */;
INSERT INTO `postulantes` VALUES (3,'9022729990','Prueba Portal','prueba.portal.2005055738@test.com','999888777','Magdalena','$2y$10$QbZEdnycK68k1T0BcdQpLOD4Th2vaPDW0A/AYNY72pVvaj7DKy9..',NULL,'2026-10-02 03:49:14'),(4,'9145735102','Prueba CV','prueba.cv.1791364173@test.com','911222333','Lima','$2y$10$TDEluYNbZ9Um5hAfqOwEzeEiRoo6AGw0YfwF8/Q06iKh/UkgFyinm','uploads/cv/cv_9145735102_1790913013.docx','2026-10-02 03:50:13');
/*!40000 ALTER TABLE `postulantes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `preguntas`
--

DROP TABLE IF EXISTS `preguntas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `preguntas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evaluacion_id` int(11) NOT NULL,
  `enunciado` varchar(400) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_preg_eval` (`evaluacion_id`),
  CONSTRAINT `fk_preg_eval` FOREIGN KEY (`evaluacion_id`) REFERENCES `evaluaciones` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `preguntas`
--

LOCK TABLES `preguntas` WRITE;
/*!40000 ALTER TABLE `preguntas` DISABLE KEYS */;
INSERT INTO `preguntas` VALUES (1,1,'Un cliente llama molesto por un cobro indebido. ¿Qué es lo primero que debes hacer?'),(2,1,'¿Qué significa brindar una solución en el primer contacto?'),(3,1,'Si no conoces la respuesta a una consulta, lo correcto es:'),(4,1,'La escucha activa consiste en:'),(5,1,'¿Cuál es una buena práctica al cerrar una llamada?'),(6,1,'Un cliente usa palabras ofensivas. ¿Cómo debes actuar?'),(7,1,'¿Por qué es importante registrar cada caso en el sistema?'),(8,1,'El indicador TMO (tiempo medio operativo) mide:'),(9,1,'¿Qué es un reclamo?'),(10,1,'Al validar la identidad de un cliente debes:'),(11,1,'Si el cliente habla muy rápido y no entiendes, lo adecuado es:'),(12,1,'La empatía en la atención al cliente significa:'),(13,1,'¿Qué debes hacer si un caso supera tus atribuciones?'),(14,1,'¿Cuál de estas frases transmite mejor profesionalismo?'),(15,1,'En la atención por chat, una buena práctica es:'),(16,1,'¿Qué información debe contener el registro de un reclamo?'),(17,1,'Si ofreciste devolver la llamada al cliente, debes:'),(18,1,'La satisfacción del cliente suele medirse con:'),(19,1,'¿Qué actitud ayuda a mantener la calidad en un turno con muchas llamadas?'),(20,1,'La confidencialidad de los datos del cliente implica:'),(21,2,'¿Cuál es el primer paso de una venta consultiva?'),(22,2,'Una objeción del cliente debe tratarse como:'),(23,2,'¿Qué significa cumplir la meta comercial?'),(24,2,'En la retención de clientes, lo más efectivo es:'),(25,2,'Un beneficio se diferencia de una característica porque:'),(26,2,'¿Qué técnica ayuda a cerrar una venta?'),(27,2,'La venta cruzada consiste en:'),(28,2,'Si el cliente dice que el precio es alto, lo adecuado es:'),(29,2,'¿Por qué es importante registrar cada gestión comercial?'),(30,2,'La persuasión ética en ventas implica:'),(31,3,'¿Qué es lo primero que se debe hacer ante una incidencia técnica?'),(32,3,'Un ticket de soporte sirve para:'),(33,3,'Si un usuario no tiene internet, una verificación básica es:'),(34,3,'¿Qué comando de Windows muestra la configuración IP?'),(35,3,'La diferencia entre hardware y software es:'),(36,3,'Antes de escalar una incidencia al segundo nivel debes:'),(37,3,'¿Para qué sirve un antivirus?'),(38,3,'Una buena práctica de seguridad para contraseñas es:'),(39,3,'¿Qué indica una dirección IP?'),(40,3,'Al atender a un usuario sin conocimientos técnicos, conviene:');
/*!40000 ALTER TABLE `preguntas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `respuestas`
--

DROP TABLE IF EXISTS `respuestas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `respuestas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resultado_id` int(11) NOT NULL,
  `pregunta_id` int(11) NOT NULL,
  `opcion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_resp_result` (`resultado_id`),
  KEY `fk_resp_preg` (`pregunta_id`),
  KEY `fk_resp_opcion` (`opcion_id`),
  CONSTRAINT `fk_resp_opcion` FOREIGN KEY (`opcion_id`) REFERENCES `opciones` (`id`),
  CONSTRAINT `fk_resp_preg` FOREIGN KEY (`pregunta_id`) REFERENCES `preguntas` (`id`),
  CONSTRAINT `fk_resp_result` FOREIGN KEY (`resultado_id`) REFERENCES `resultados_eval` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `respuestas`
--

LOCK TABLES `respuestas` WRITE;
/*!40000 ALTER TABLE `respuestas` DISABLE KEYS */;
/*!40000 ALTER TABLE `respuestas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resultados_eval`
--

DROP TABLE IF EXISTS `resultados_eval`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resultados_eval` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `postulacion_id` int(11) NOT NULL,
  `evaluacion_id` int(11) NOT NULL,
  `puntaje` int(11) NOT NULL DEFAULT 0,
  `aprobado` tinyint(1) NOT NULL DEFAULT 0,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_res_post` (`postulacion_id`),
  KEY `fk_res_eval` (`evaluacion_id`),
  CONSTRAINT `fk_res_eval` FOREIGN KEY (`evaluacion_id`) REFERENCES `evaluaciones` (`id`),
  CONSTRAINT `fk_res_post` FOREIGN KEY (`postulacion_id`) REFERENCES `postulaciones` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resultados_eval`
--

LOCK TABLES `resultados_eval` WRITE;
/*!40000 ALTER TABLE `resultados_eval` DISABLE KEYS */;
/*!40000 ALTER TABLE `resultados_eval` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Administrador'),(3,'Reclutador'),(2,'Supervisor');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rol_id` int(11) NOT NULL,
  `area_id` int(11) DEFAULT NULL,
  `usuario` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `correo` varchar(120) DEFAULT NULL,
  `clave` varchar(255) NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_acceso` datetime DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  KEY `fk_usuario_rol` (`rol_id`),
  KEY `fk_usuario_area` (`area_id`),
  CONSTRAINT `fk_usuario_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`),
  CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,1,NULL,'admin','Administrador del Sistema','admin@a365.com','$2y$10$P91sByA2UP1rksjysT29b..G33WY8k0oWRjE.Ddr8n1Qa82hiGsMu',1,'2026-10-01 21:46:08','2026-09-22 03:59:03'),(2,2,1,'supervisor','Supervisor de Reclutamiento','supervisor@a365.com','$2y$10$P91sByA2UP1rksjysT29b..G33WY8k0oWRjE.Ddr8n1Qa82hiGsMu',1,'2026-10-01 21:36:33','2026-09-22 03:59:03'),(3,3,1,'reclutador','Reclutador de Campaña','reclutador@a365.com','$2y$10$P91sByA2UP1rksjysT29b..G33WY8k0oWRjE.Ddr8n1Qa82hiGsMu',1,'2026-10-01 21:45:52','2026-09-22 03:59:03'),(4,1,NULL,'admin2','Hector Crisostomo','h.crisostomo@a365.com','$2y$10$TbEpuuE1FSPGknN7UAv/q.H0kSuqZ2DmrSgFPRzDG7PHGoNbZNfC2',1,NULL,'2026-10-02 02:22:35'),(5,2,1,'prueba','prueba','prueba@a365.com','$2y$10$ZDNLc0ZiTH95iW/S72nAau/C6bukBTgJxzKMtGM/9tX1XwFOz8agm',1,NULL,'2026-10-02 02:26:46');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `versiones_sistema`
--

DROP TABLE IF EXISTS `versiones_sistema`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `versiones_sistema` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version` varchar(20) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `archivo` varchar(200) DEFAULT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_ver_user` (`usuario_id`),
  CONSTRAINT `fk_ver_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `versiones_sistema`
--

LOCK TABLES `versiones_sistema` WRITE;
/*!40000 ALTER TABLE `versiones_sistema` DISABLE KEYS */;
INSERT INTO `versiones_sistema` VALUES (1,'1.0.0','Versión inicial del Sistema de Gestión y Seguimiento de Reclutamiento (SSR).',NULL,NULL,'2026-09-23 00:32:46');
/*!40000 ALTER TABLE `versiones_sistema` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-01 22:51:27
