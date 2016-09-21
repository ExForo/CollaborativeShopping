<?php
/**
 * Обработчик событий вызова контроллеров
 * @package     Esthetic_CS
 */
class Esthetic_CS_Listener_Controller {

    /**
     * Номер версии скрипта
     * @var     int
     */
    public static $est_version_id   = 102;
    
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
            case 'XenForo_ControllerAdmin_Forum':
                $extend[] = 'Esthetic_CS_ControllerAdmin_Forum';
                break;
                
            case 'XenForo_ControllerPublic_Forum':
                $extend[] = 'Esthetic_CS_ControllerPublic_Forum';
                break;
                
            case 'XenForo_ControllerPublic_Thread':
                $extend[] = 'Esthetic_CS_ControllerPublic_Thread';
                break;
                
            case 'XenForo_ControllerPublic_Member':
                $extend[] = 'Esthetic_CS_ControllerPublic_Member';
                break;
                
            default:
        }
        
        return true;
    }
}