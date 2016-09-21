<?php
/**
 * Рендер шаблона "Esthetic_CS_ViewPublic_Shopping_Inline_MarkPaid"
 * @package     Esthetic_CS
 */
class Esthetic_CS_ViewPublic_Shopping_Inline_MarkPaid extends XenForo_ViewPublic_Base {

    /**
     * Номер версии скрипта
     * @var     int
     */
    public static $est_version_id   = 1120;
    
    /**
     * Идентификатор продукта
     * @var     string
     */
    public static $est_addon_id     = 'estcs';
    
    
    
    /**
     * Обработка параметров рендера
     * @return  NULL
     */
	public function renderJson ( ) {
        return XenForo_ViewRenderer_Json::jsonEncodeForOutput(array (
            'participant'   => $this->_params['participant']
        ));
    }
}