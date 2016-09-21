<?php
/**
 * Рендер шаблона "Esthetic_CS_ViewPublic_Shopping_Inline_DetailsEdit"
 * @package     Esthetic_CS
 */
class Esthetic_CS_ViewPublic_Shopping_Inline_DetailsEdit extends XenForo_ViewPublic_Base {

    /**
     * Номер версии скрипта
     * @var     int
     */
    public static $est_version_id   = 1130;
    
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
        
        switch ($this->_params['type']) {
            case 'payment':
                $message = (string)$this->_params['shopping']['extended_data']['payment_details'];
                break;
                
            case 'delivery':
                $message = (string)$this->_params['shopping']['extended_data']['product_details'];
                break;
                
            default:
                $message = '';
        }
        
        $this->_params['editorTemplate'] = XenForo_ViewPublic_Helper_Editor::getEditorTemplate(
            $this, $this->_params['type'],
            $message,
            array (
                'editorId' => $this->_params['type'] . '_' . substr (md5 (microtime (true)), -8)
            )
        );
    }
}