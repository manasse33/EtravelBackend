CREATE TABLE IF NOT EXISTS `city_tour_inclusions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `city_tour_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `city_tour_inclusions_city_tour_id_index` (`city_tour_id`),
    CONSTRAINT `city_tour_inclusions_city_tour_id_foreign`
        FOREIGN KEY (`city_tour_id`) REFERENCES `city_tours` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
