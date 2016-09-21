<?php
/**
 * Рендер шаблона "Esthetic_CS_ViewPublic_Thread_View"
 * @package     Esthetic_CS
 */
class Esthetic_CS_ViewPublic_Thread_View extends XFCP_Esthetic_CS_ViewPublic_Thread_View {

    /**
     * Номер версии скрипта
     * @var     int
     */
    public static $est_version_id   = 1070;
    
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
        
        $bb_code_parser = new XenForo_BbCode_Parser (XenForo_BbCode_Formatter_Base::create('Base', array ('view' => $this)));
        $bb_code_options = array (
            'states' => array (
            'viewAttachments' => $this->_params['canViewAttachments']
        ));

        if (!empty ($this->_params['shopping']['extended_data']['payment_details'])) {
        
            $this->_params['shopping']['extended_data']['payment_details'] = preg_replace (
                '/\{amount\}/', 
                $this->_params['shopping_data']['payment'],
                $this->_params['shopping']['extended_data']['payment_details']
            );
        
            $this->_params['shopping']['extended_data']['payment_details_html'] = new XenForo_BbCode_TextWrapper (
                $this->_params['shopping']['extended_data']['payment_details'], $bb_code_parser, $bb_code_options
            );
        }
        
        if (!empty ($this->_params['shopping']['extended_data']['product_details'])) {
            
            $this->_params['shopping']['extended_data']['payment_details'] = preg_replace (
                '/\{amount\}/', 
                $this->_params['shopping_data']['payment'],
                $this->_params['shopping']['extended_data']['payment_details']
            );
            
            $this->_params['shopping']['extended_data']['product_details_html'] = new XenForo_BbCode_TextWrapper (
                $this->_params['shopping']['extended_data']['product_details'], $bb_code_parser, $bb_code_options
            );
        }
        
        return parent::renderHtml();
    }
}