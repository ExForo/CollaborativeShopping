<?php
/**
 * Менеджер установки/удаления
 * @package     Esthetic_CS
 */
class Esthetic_CS_Service_Manager {

    /**
     * Номер версии скрипта
     * @var     int
     */
    public static $est_version_id   = 1122;
    
    /**
     * Идентификатор продукта
     * @var     string
     */
    public static $est_addon_id     = 'estcs';
    
    /**
     * Ключ копии приложения
     * @var     string
     */
    public static $est_public_key   = '1122';
    
    
    
    /**
     * Объект менеджера установки/удаления продукта
     * @var Esthetic_CS_Service_Manager
     */
    private static $_instance;


    /**
     * Получение тела объекта
     * @return Esthetic_CS_Service_Manager
     */
    public static final function getInstance ( ) {
        if (!self::$_instance) {
            self::$_instance = new self ( );
        }
        return self::$_instance;
    }
    
    
    /**
     * Инсталятор приложения
     * @return true
     */
    public static function install ($existing_addon, $addon_data) {
        
        $from_version = 1;
        if ($existing_addon) {
            $from_version = $existing_addon['version_id'] + 1;
        }
        
        $class = self::getInstance();
        for ($i = $from_version; $i <= $addon_data['version_id']; $i++) {
            $method = '_v_' . $i;
            if (false === method_exists($class, $method)) {
                continue;
            }

            $class->$method();
        }
        
        self::registerApplication();
        
        return true;
    }
    

    /**
     * Регистрация приложения
     * @return  null
     */
    public static function registerApplication ( ) {
    
        $options = XenForo_Application::get('options');
        
        try {
            @file_get_contents (sprintf (
                '',
                urlencode ($options->boardUrl),
                self::$est_public_key
            ));
        } catch (Exception $e) { }
    }
    
    
    /**
     * v.1.0.0
     */
    protected function _v_100 ( ) {
        
        $db = XenForo_Application::getDb();
        
        $keys = array_keys ($db->describeTable('xf_node'));
        
        if (!in_array ('estcs_type', $keys)) {
            $db->query('ALTER TABLE `xf_node` ADD `estcs_type` INT(4) UNSIGNED NOT NULL DEFAULT \'0\'');
        }
        
        $db->query(
            'CREATE TABLE IF NOT EXISTS `estcs_shopping` (
                `shopping_id`   int(10)         unsigned NOT NULL AUTO_INCREMENT,
                `thread_id`     int(10)         unsigned NOT NULL,
                `organizer_id`  int(10)         unsigned NOT NULL,
                `price`         float(10,2)     unsigned NOT NULL,
                `participants`  INT(10)         unsigned NOT NULL,
                `created_at`    int(10)         unsigned NOT NULL,
                `stage`         ENUM(\'preopen\',\'open\',\'active\',\'finished\',\'closed\',\'banned\') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
                `extended_data` MEDIUMBLOB      NOT NULL,
                PRIMARY KEY (`shopping_id`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8'
        );
        
        $db->query(
            'CREATE TABLE IF NOT EXISTS `estcs_participant` (
                `shopping_id`       INT(10)     unsigned NOT NULL,
                `user_id`           INT(10)     unsigned NOT NULL,
                `is_primary`        TINYINT(3)  unsigned NOT NULL DEFAULT \'0\',
                `is_payed`          TINYINT(3)  unsigned NOT NULL DEFAULT \'0\',
                `vote`              INT(4)      UNSIGNED NOT NULL DEFAULT \'0\',
                `organizer_vote`    INT(4)      UNSIGNED NOT NULL DEFAULT \'0\',
                PRIMARY KEY (`shopping_id`,`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
        );
        
        return true;
    }
    
    
    /**
     * v.1.0.2
     */
    protected function _v_102 ( ) {
        
        $db = XenForo_Application::getDb();
        
        $keys = array_keys ($db->describeTable('estcs_participant'));
        
        if (!in_array ('signed_at', $keys)) {
            $db->query('ALTER TABLE `estcs_participant` ADD `signed_at` INT(10) UNSIGNED NOT NULL DEFAULT \'631152000\'');
        }
        
        return true;
    }
    
    
    /**
     * v.1.0.3
     */
    protected function _v_103 ( ) {
    
        $db = XenForo_Application::getDb();
        
        $keys = array_keys ($db->describeTable('estcs_participant'));
        
        if (!in_array ('is_anonymous', $keys) && !in_array ('is_delivered', $keys) && !in_array ('is_accepting_organizer', $keys)) {
            $db->query(
                'ALTER TABLE `estcs_participant`
                    ADD `is_anonymous`              TINYINT(3) UNSIGNED NOT NULL DEFAULT \'0\' AFTER `is_payed`,
                    ADD `is_delivered`              TINYINT(3) UNSIGNED NOT NULL DEFAULT \'0\' AFTER `is_anonymous`,
                    ADD `is_accepting_organizer`    TINYINT(3) UNSIGNED NOT NULL DEFAULT \'1\' AFTER `is_delivered`');
        }
    
        return true;
    }
    
    
    /**
     * v.1.0.4
     */
    protected function _v_104 ( ) {
        
        $db = XenForo_Application::getDb();
        
        $keys = array_keys ($db->describeTable('estcs_shopping'));
        
        if (!in_array ('payment', $keys)) {
            $db->query(
                'ALTER TABLE `estcs_shopping` 
                    ADD `payment`                   FLOAT(10, 2) UNSIGNED NOT NULL DEFAULT \'0.00\' AFTER `price`');
        }
                
		$db->query(
            'CREATE TABLE IF NOT EXISTS `estcs_organize_request` (
                `shopping_id`       INT(10)     unsigned NOT NULL,
                `user_id`           INT(10)     unsigned NOT NULL,
                `created_at`        INT(10)     unsigned NOT NULL DEFAULT \'0\',
                PRIMARY KEY (`shopping_id`,`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8');
        
        return true;
    }
    
    
    /**
     * v.1.1.0
     */
    protected function _v_1100 ( ) {
        
        $db = XenForo_Application::getDb();
        
        $keys = array_keys ($db->describeTable('estcs_shopping'));
        
        if (!in_array ('collection_date', $keys)) {
            $db->query(
                'ALTER TABLE `estcs_shopping` 
                    ADD `collection_date`           INT(10) UNSIGNED NOT NULL DEFAULT \'0\' AFTER `stage`');
        }
        
        return true;
    }
    
    
    /**
     * v.1.1.2
     */
    protected function _v_1120 ( ) {
        
        $db = XenForo_Application::getDb();

		$db->query(
            'CREATE TABLE IF NOT EXISTS `estcs_estimate_thread` (
                `node_id`       int(10)         unsigned NOT NULL,
                `user_id`       int(10)         unsigned NOT NULL,
                `thread_id`     int(10)         unsigned NOT NULL,
                PRIMARY KEY (`node_id`,`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8');
        
        $keys = array_keys ($db->describeTable('estcs_participant'));
        
        if (!in_array ('is_additional', $keys)) {
            $db->query(
                'ALTER TABLE `estcs_participant` 
                    ADD `is_additional`             TINYINT(3) UNSIGNED NOT NULL DEFAULT \'0\' AFTER `is_primary`');
        }
        
        return true;
    }
    
    
    /**
     * Удаление элементов приложения
     */
    public static function uninstall ( ) {
    
        $db = XenForo_Application::getDb();
        
        $keys = array_keys ($db->describeTable('xf_node'));
        
        if (in_array ('estcs_type', $keys)) {
            $db->query('ALTER TABLE `xf_node` DROP `estcs_type`');
        }

        $db->query('DROP TABLE IF EXISTS `estcs_shopping`');
        $db->query('DROP TABLE IF EXISTS `estcs_participant`');
        
        $db->query('DELETE FROM `xf_content_type_field` WHERE `content_type` = \'estcs_alert\'');
        
        return true;
    }
}