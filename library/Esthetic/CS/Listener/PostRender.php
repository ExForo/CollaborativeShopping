<?php
/**
 * Обработчик событий вызова рендера шаблонов
 * @package     Esthetic_CS
 */
class Esthetic_CS_Listener_PostRender {

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
     * Обработка пост-рендеринговых контейнеров
     * @param   string                      $templateName
     * @param   string                      &$content
     * @param   array                       &$containerData
     * @param   XenForo_Template_Abstract   $template
     */
    public static function listen ($templateName, &$content, &$containerData, $template) {
        
        switch ($templateName) {
            case 'forum_edit':
                
                if (!$session = $template->getParam('controllerName')) return false;
                if ($session != 'XenForo_ControllerAdmin_Forum') return false;

                $data = $template->getParam('forum');
                self::caseForumEdit ($content, $containerData, $data);
                break;
                
            default:
        }
        return true;
    }
    

    /**
     * Обработка формы регистрации категории
     * @param   string  &$contents
     * @param   array   &$containerData
     * @param   array   $data
     */
    public static function caseForumEdit (&$contents, &$containerData, $data) {
        
        $estcs_type    = !empty ($data['estcs_type']) ? $data['estcs_type'] : 0;

        $template   = new XenForo_Template_Admin ('estcs_edit_pane', array (
            'estcs_allow_cs'        => $estcs_type > 0,
            'estcs_allow_regular'   => $estcs_type > 1
        ));
        
        $insert     = $template->render();
        
        $contents   = preg_replace ('/\<\!\-\-\@estcs\:pane\-\-\>/', $insert, $contents);
        
        return true;
    }
}