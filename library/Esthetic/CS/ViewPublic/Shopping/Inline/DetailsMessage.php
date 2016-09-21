<?php
/**
 * Рендер шаблона "Esthetic_CS_ViewPublic_Shopping_Inline_DetailsMessage"
 * @package     Esthetic_CS
 */
class Esthetic_CS_ViewPublic_Shopping_Inline_DetailsMessage extends XenForo_ViewPublic_Base {

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
    public function renderJson ( ) {
        
        $bb_code_parser = new XenForo_BbCode_Parser (XenForo_BbCode_Formatter_Base::create('Base', array ('view' => $this)));
        $bb_code_options = array (
            'states' => array (
            'viewAttachments' => false
        ));
        
        $this->_params['visibility_status'] = 0;
        if (!empty ($this->_params['details_message'])) {
            $this->_params['details_message_html'] = new XenForo_BbCode_TextWrapper (
                $this->_params['details_message'], $bb_code_parser, $bb_code_options
            );
        } else {
            $this->_params['visibility_status'] = 1;
            $this->_params['details_message_html'] = sprintf (
                '<p class="estcs-extends-details-not-set">%s</p>',
                new XenForo_Phrase ('estcs_details_not_set')
            );
        }
        
        if (
            Esthetic_CS_Helper_Shopping::getStageId($this->_params['shopping']['stage']) >= 5 ||
            (Esthetic_CS_Helper_Shopping::getStageId($this->_params['shopping']['stage']) <= 1 && $this->_params['content_type'] == 'payment')||
            (Esthetic_CS_Helper_Shopping::getStageId($this->_params['shopping']['stage']) <= 2 && $this->_params['content_type'] == 'delivery')
        ) {
            $this->_params['visibility_status'] = 2;
        }
    }
}