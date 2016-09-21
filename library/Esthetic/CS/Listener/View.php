<?php
/**
 * Обработчик событий вызова рендера шаблонов
 * @package     Esthetic_CS
 */
class Esthetic_CS_Listener_View {

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
        
            case 'XenForo_ViewPublic_Thread_View':
                $extend[] = 'Esthetic_CS_ViewPublic_Thread_View';
                break;
                
            case 'XenForo_ViewPublic_Forum_View':
                $extend[] = 'Esthetic_CS_ViewPublic_Forum_View';
                break;
                
            default:
        }
        
        return true;
    }
}