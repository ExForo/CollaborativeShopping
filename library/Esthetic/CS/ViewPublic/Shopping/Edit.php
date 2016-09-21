<?php
/**
 * Рендер шаблона "Esthetic_CS_ViewPublic_Shopping_Edit"
 * @package     Esthetic_CS
 */
class Esthetic_CS_ViewPublic_Shopping_Edit extends XenForo_ViewPublic_Base {

    /**
     * Номер версии скрипта
     * @var     int
     */
    public static $est_version_id   = 103;
    
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
        
        $this->_params['shopping']['price'] = round ($this->_params['shopping']['price'], 2);
    }
}