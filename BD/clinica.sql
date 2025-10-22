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

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuarios` int(15) NOT NULL,
  `Nombre_completo` varchar(50) NOT NULL,
  `Correo_electronico` varchar(50) NOT NULL,
  `Usuario` varchar(50) NOT NULL,
  `Contrasena` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuarios`, `Nombre_completo`, `Correo_electronico`, `Usuario`, `Contrasena`) VALUES
(1, 'David Pacheco', 'Dpacheco@pruebas.hn', 'Dpacheco', '1234'),
(2, 'Pacheco David', 'Pdavid@Pruebas.hn', 'Pdavid', '1234'),
(3, 'matteito', 'matteito@prueba.hn', 'matteito@prueba.hn', '$2y$10$PwgQ6aQjtQxyhvEtCiI4veLRZmPfwDj7juO86Y/ANPsByUjo2BQoO'),
(4, 'Dhenriquez', 'Dhenriquez@nutri.hn', 'Dhenriquez@nutri.hn', '$2y$10$AkxaFKWlr4tbEKTyPI5h1.l39ohhlNG0eP4RWHmekX872bYjEIA9C'),
(5, 'Ppacheco', 'Ppacheco@nutri.hn', 'Ppacheco@nutri.hn', '$2y$10$qeryNlI2wr6sr47Qi/ozDO8TtvazzaRzTFC9UrmZhNiX3ov.QvFS2'),
(6, 'Hhenriquez', 'Hhenriquez@nutri.hn', 'Hhenriquez@nutri.hn', '$2y$10$fpMxyFgwxFAvHOTPII1TZ.LSg.S8obHg3erHq32nv1SaIgkWdwQcS');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuarios`),
  ADD UNIQUE KEY `idx_usuario` (`Usuario`(20)),
  ADD UNIQUE KEY `idx_correo` (`Correo_electronico`(30));

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuarios` int(15) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
