<?php
/**
 * Шаблон модели Forum
 * @package     Esthetic_CS
 */
class Esthetic_CS_DataWriter_Forum extends XFCP_Esthetic_CS_DataWriter_Forum {

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
     * Получение массива свойств модели
     * @return  array
     */
    protected function _getFields ( ) {
    
        $response   = parent::_getFields();
        $response['xf_node']['estcs_type'] = array ('type' => self::TYPE_UINT, 'default' => 0);
        
        return $response;
    }
    
    
    /**
     * Обработка параметра estcs_type перед сохранением
     * @return  null
     */
    protected function _preSave ( ) {
    
        if (!XenForo_Application::isRegistered('estcs_type')) {
            return parent::_preSave();
        }

        $this->set('estcs_type', (int)XenForo_Application::get('estcs_type'));
        
        return parent::_preSave();
    }
}