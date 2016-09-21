<?php
/**
 * Обработчик событий вызова хуков
 * @package     Esthetic_CS
 */
class Esthetic_CS_Listener_Hook {

    /**
     * Номер версии скрипта
     * @var     int
     */
    public static $est_version_id   = 1062;
    
    /**
     * Идентификатор продукта
     * @var     string
     */
    public static $est_addon_id     = 'estcs';
    
    
    

    /**
     * Обработка хуков
     * @param   string                      $hook_name
     * @param   string                      &$contents
     * @param   array                       $hook_params
     * @param   XenForo_Template_Abstract   $template
     * @return  bool
     */
    public static function listen ($hook_name, &$contents, array $hook_params, XenForo_Template_Abstract $template) {
        
        switch ($hook_name) {
            case 'admin_forum_edit_panes':
                self::caseForumPanes($contents, $hook_params);
                break;

            case 'admin_forum_edit_tabs':
                self::caseForumTabs($contents, $hook_params);
                break;
                
            case 'estcs_currency_title':
                self::caseCurrencyTitle($contents, $hook_params);
                break;
                
            case 'navigation_visitor_tab_links2':
                self::caseNavigationVisitorTabLinks2($contents, $hook_params);
                break;
                
            case 'page_container_notices':
                self::casePageContainerNotices($contents, $hook_params);
                break;
                
            case 'moderator_bar':
                self::caseModeratorBar($contents, $hook_params);
                break;
                
            case 'page_container_head':
                self::casePageContainerHead($contents, $hook_params);
                break;

            default:
        }
        
        return true;
    }
    
    
    /**
     * Панель установок раздела
     * @param   string                      $contents
     * @param   array                       $params
     * @return  bool
     */
    protected static function caseForumPanes (&$contents, &$params) {
        $contents .= '<!--@estcs:pane-->';
        return true;
    }
    
    
    /**
     * Вкладки установок раздела
     * @param   string                      $contents
     * @param   array                       $params
     * @return  bool
     */
    protected static function caseForumTabs (&$contents, &$params) {
        
        $template = new XenForo_Template_Admin ('estcs_edit_tabs', $params);
        $contents .= $template->render();
        
        return true;
    }
    
    
    /**
     * Вкладки установок раздела
     * @param   string                      $contents
     * @param   array                       $params
     * @return  bool
     */
    protected static function caseCurrencyTitle (&$contents, &$params) {
        $contents .= Esthetic_CS_Helper_Shopping::getCurrencyTitle();
        return true;
    }
    
    
    /**
     * Вкладка меню пользователя
     * @param   string                      $contents
     * @param   array                       $params
     * @return  bool
     */
    protected static function caseNavigationVisitorTabLinks2 (&$contents, &$params) {
        
        if (false === ($visitor = XenForo_Visitor::getInstance())) {
            return false;
        }
        
        $template = new XenForo_Template_Public ('estcs_navigation_tab', $params + array (
            'add_joined_shoppings'      => true,
            'add_organized_shoppings'   => $visitor->hasPermission('estcs', 'estcs_can_organize')
        ));
        $contents .= $template->render();
        
        return true;
    }
    
    
    /**
     * Обработка новостей
     * @param   string                      $contents
     * @param   array                       $params
     * @return  bool
     */
    protected static function casePageContainerNotices (&$contents, &$params) {
        
        if (empty ($params)) {
            return false;
        }
        
        $options = XenForo_Application::get('options');

        foreach ($params['block'] as $key => &$param) {
        
            if (!isset ($param['message'])) {
                continue;
            }
            
            if ($key == $options->estcs_notice_new) {
                $param['message'] = Esthetic_CS_Helper_Shopping::prepareNotice('estcs_notice_type_new', 's.stage NOT IN (\'banned\', \'closed\')');
            } else if ($key == $options->estcs_notice_org) {
                $param['message'] = Esthetic_CS_Helper_Shopping::prepareNotice('estcs_notice_type_orgenizer_required', 's.organizer_id = 0 AND s.stage = \'open\'');
            } else if ($key == $options->estcs_notice_pay) {
                $param['message'] = Esthetic_CS_Helper_Shopping::prepareNotice('estcs_notice_type_active', 's.organizer_id > 0 AND s.stage = \'active\'');
            }

            if (empty ($param['message'])) {
                unset ($params['block'][$key]);
            }
        }
        
        $template = new XenForo_Template_Public('notices', array('notices' => $params));
        $contents = $template->render();
        
        
        return true;
    }
    
    
    /**
     * Обработка полосы уведомлений модератора
     * @param   string                      $contents
     * @param   array                       $params
     * @return  bool
     */
    protected static function caseModeratorBar (&$contents, $params) {
        
        $visitor = XenForo_Visitor::getInstance();
        if (!$visitor->hasPermission('estcs', 'estcs_can_approve_org')) {
            return false;
        }
        
        $template = new XenForo_Template_Public ('estcs_moderator_bar', array (
            'organizers_count'  => (int)XenForo_Model::create('Esthetic_CS_Model_OrganizeRequest')->getTotalRequestsCount()
        ));
        $contents = $template->render();
    }
    
    
    /**
     * Добавление мета-тега
     * @param   string                      $contents
     * @param   array                       $params
     * @return  bool
     */
    protected static function casePageContainerHead (&$contents, $params) {
        $contents = '<meta name="keywords" content="Совместные покупки" />' . $contents;
    }
}