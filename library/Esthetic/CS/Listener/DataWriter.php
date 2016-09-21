<?php
/**
 * Обработчик событий вызова шаблонов моделей
 * @package     Esthetic_CS
 */
class Esthetic_CS_Listener_DataWriter {

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
     * Обработка вызова класса
     * @param   string  &$class
     * @param   array   &$extends
     * @return  bool
     */
    public static function listen ($class, array &$extend) {
        
        switch ($class) {
            case 'XenForo_DataWriter_Forum':
                $extend[] = 'Esthetic_CS_DataWriter_Forum';
                break;
                
            case 'XenForo_DataWriter_Discussion_Thread':
                $extend[] = 'Esthetic_CS_DataWriter_Discussion_Thread';
                break;
                
            case 'XenForo_DataWriter_User':
                $extend[] = 'Esthetic_CS_DataWriter_User';
                break;
                
            default:
        }
        
        return true;
    }
}