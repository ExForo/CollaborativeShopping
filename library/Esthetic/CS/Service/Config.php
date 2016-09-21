<?php
/**
 * Установки приложения
 * @package     Esthetic_CS
 */
class Esthetic_CS_Service_Config {

    /**
     * Номер версии скрипта
     * @var     int
     */
    public static $est_version_id   = 1051;
    
    /**
     * Идентификатор продукта
     * @var     string
     */
    public static $est_addon_id     = 'estcs';
    
    
    
    /**
     * Использовать упрощенный алгоритм значений наценки организатора.
     * Внимание! При использовании упрощенного алгоритма поддерживаются только целые числа!
     * @var bool
     */
    public static $organizer_fee_extend_use_simple_mode                     = false;
}