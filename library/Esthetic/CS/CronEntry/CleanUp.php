<?php
/**
 * Функции планировщика задач
 * @package     Esthetic_CS
 */
class Esthetic_CS_CronEntry_CleanUp {

    /**
     * Номер версии скрипта
     * @var     int
     */
    public static $est_version_id   = 100;
    
    /**
     * Идентификатор продукта
     * @var     string
     */
    public static $est_addon_id     = 'estcs';



    /**
     * Ежедневная очистка
     */
    public static function runDailyCleanUp ( ) {
    
        $shopping_model = XenForo_Model::create('Esthetic_CS_Model_Shopping');
        $shopping_model->removeBrokenRecords();
    }
}