-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 22-10-2025 a las 03:13:44
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `clinica`
--

-- --------------------------------------------------------
-- Estructura de tabla para la tabla `usuarios`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id_usuarios` int(15) NOT NULL,
  `Nombre_completo` varchar(50) NOT NULL,
  `Correo_electronico` varchar(50) NOT NULL,
  `Usuario` varchar(50) NOT NULL,
  `Contrasena` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Volcado de datos para la tabla `usuarios`
-- --------------------------------------------------------
INSERT INTO `usuarios` (`id_usuarios`, `Nombre_completo`, `Correo_electronico`, `Usuario`, `Contrasena`) VALUES
(1, 'David Pacheco', 'Dpacheco@pruebas.hn', 'Dpacheco', '1234'),
(2, 'Pacheco David', 'Pdavid@Pruebas.hn', 'Pdavid', '1234'),
(3, 'matteito', 'matteito@prueba.hn', 'matteito@prueba.hn', '$2y$10$PwgQ6aQjtQxyhvEtCiI4veLRZmPfwDj7juO86Y/ANPsByUjo2BQoO'),
(4, 'Dhenriquez', 'Dhenriquez@nutri.hn', 'Dhenriquez@nutri.hn', '$2y$10$AkxaFKWlr4tbEKTyPI5h1.l39ohhlNG0eP4RWHmekX872bYjEIA9C'),
(5, 'Ppacheco', 'Ppacheco@nutri.hn', 'Ppacheco@nutri.hn', '$2y$10$qeryNlI2wr6sr47Qi/ozDO8TtvazzaRzTFC9UrmZhNiX3ov.QvFS2'),
(6, 'Hhenriquez', 'Hhenriquez@nutri.hn', 'Hhenriquez@nutri.hn', '$2y$10$fpMxyFgwxFAvHOTPII1TZ.LSg.S8obHg3erHq32nv1SaIgkWdwQcS');

-- --------------------------------------------------------
-- Índices y AUTO_INCREMENT para la tabla `usuarios`
-- --------------------------------------------------------
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuarios`),
  ADD UNIQUE KEY `idx_usuario` (`Usuario`(20)),
  ADD UNIQUE KEY `idx_correo` (`Correo_electronico`(30));

ALTER TABLE `usuarios`
  MODIFY `id_usuarios` int(15) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

-- --------------------------------------------------------
-- Estructura de tabla para la tabla `pacientes`
-- (nueva tabla agregada)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pacientes` (
  `id_paciente` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(15) NOT NULL,
  `contacto_emergencia_telefono` varchar(20) DEFAULT NULL,
  `tipo_paciente` varchar(30) NOT NULL,
  `historial_inicial` text,
  `expediente_unique` varchar(50) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_registro` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_paciente`),
  KEY `idx_usuario_id` (`usuario_id`),
  UNIQUE KEY `uq_expediente_unique` (`expediente_unique`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Relación (clave foránea) pacientes -> usuarios
-- --------------------------------------------------------
ALTER TABLE `pacientes`
  ADD CONSTRAINT `fk_pacientes_usuarios`
    FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id_usuarios`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE;

-- --------------------------------------------------------
-- Insert de ejemplo en `pacientes` (varios registros de prueba)
-- Asegúrate de que los id_usuarios 1..6 existan (en este dump sí existen)
-- --------------------------------------------------------
INSERT INTO `pacientes`
  (usuario_id, contacto_emergencia_telefono, tipo_paciente, historial_inicial, expediente_unique, activo)
VALUES
  (1, '+504 9999-8888', 'Pérdida de peso', 'Paciente con sobrepeso leve, sin antecedentes mayores.', 'EXP-20251022-0001', 1),
  (2, '+504 8888-7777', 'Seguimiento', 'Consulta de control mensual. Ha reducido 3kg.', 'EXP-20251022-0002', 0),
  (3, '+504 7777-6666', 'Control metabólico', 'Paciente con antecedentes de hipotiroidismo. Iniciar plan de seguimiento.', 'EXP-20251022-0003', 1),
  (4, '+504 6666-5555', 'Mejora de hábitos', 'Objetivo de mejorar hábitos alimenticios y sueño. Primer control a 1 mes.', 'EXP-20251022-0004', 1),
  (5, '+504 5555-4444', 'Ganancia muscular', 'Paciente enfocado en ganancia de masa muscular. Evaluar ingesta proteica.', 'EXP-20251022-0005', 1),
  (6, '+504 4444-3333', 'Seguimiento', 'Paciente en control postoperatorio; ajustar plan nutricional.', 'EXP-20251022-0006', 1);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
