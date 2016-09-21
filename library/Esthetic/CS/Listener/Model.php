<?php
/**
 * Обработчик событий вызова моделей
 * @package     Esthetic_CS
 */
class Esthetic_CS_Listener_Model {

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
            case 'XenForo_Model_User':
                $extend[] = 'Esthetic_CS_Model_User';
                break;
                
            case 'XenForo_Model_Thread':
                $extend[] = 'Esthetic_CS_Model_Thread';
                break;
                
            case 'XenForo_Model_Forum':
                $extend[] = 'Esthetic_CS_Model_Forum';
                break;
                
            case 'XenForo_Model_Conversation':
                $extend[] = 'Esthetic_CS_Model_Conversation';
                break;
                
            case 'XenForo_Model_Warning':
                self::caseModelWarning();
                break;
                
            case 'XenForo_Model_Alert':
                self::caseModelAlert();
                break;
                
            default:
        }
        
        return true;
    }
    
    
    /**
     * Регистрация обработчика сообщений о нарушениях
     * @return null
     */
    protected static function caseModelWarning ( ) {
    
        if (!XenForo_Application::isRegistered('contentTypes')) {
            return false;
        }
        
        $content_types = XenForo_Application::get('contentTypes');

        if (empty ($content_types)) {
            $content_types = array ( );
        }
        
        $content_types['estcs_shopping'] = array ('warning_handler_class' => 'Esthetic_CS_WarningHandler_Shopping');
        
        XenForo_Application::set('contentTypes', $content_types);
    }
    
    
    /**
     * Регистрация обработчика пользовательских уведомлений
     * @return null
     */
    protected static function caseModelAlert ( ) {
    
        if (!XenForo_Application::isRegistered('contentTypes')) {
            return false;
        }
        
        $content_types = XenForo_Application::get('contentTypes');

        if (empty ($content_types)) {
            $content_types = array ( );
        }
        
        $content_types['estcs_alert'] = array ('alert_handler_class' => 'Esthetic_CS_AlertHandler_Shopping');
        
        XenForo_Application::set('contentTypes', $content_types);
    }
}