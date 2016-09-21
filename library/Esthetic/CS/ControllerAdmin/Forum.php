<?php
/**
 * Контроллер Forum
 * @package     Esthetic_CS
 */
class Esthetic_CS_ControllerAdmin_Forum extends XFCP_Esthetic_CS_ControllerAdmin_Forum {

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
     * Замена функции сохранения настроек
     * @return  bool
     */
    public function actionSave ( ) {
        
        $estcs_type = intval ($this->_input->filterSingle('estcs_allow_cs', XenForo_Input::UINT));
        
        if ($estcs_type > 0 && $this->_input->filterSingle('estcs_allow_regular', XenForo_Input::UINT) != false) {
            $estcs_type = 2;
        }

        XenForo_Application::set('estcs_type', $estcs_type);
        
        return parent::actionSave();
    }
}