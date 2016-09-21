<?php
/**
 * Рендер шаблона "Esthetic_CS_ViewPublic_Forum_View"
 * @package     Esthetic_CS
 */
class Esthetic_CS_ViewPublic_Forum_View extends XFCP_Esthetic_CS_ViewPublic_Forum_View {

    /**
     * Номер версии скрипта
     * @var     int
     */
    public static $est_version_id   = 1100;
    
    /**
     * Идентификатор продукта
     * @var     string
     */
    public static $est_addon_id     = 'estcs';
    
    
    
    /**
     * Обработка параметров рендера
     * @return  NULL
     */
	public function renderHtml ( ) {
        
        $this->_params['estcs_current_time'] = XenForo_Application::$time;

        return parent::renderHtml();
    }
}