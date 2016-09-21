<?php
/**
 * Контроллер 'Moderation'
 * @package     Esthetic_CS
 */
class Esthetic_CS_ControllerPublic_Moderation extends XenForo_ControllerPublic_Abstract {
    
    /**
     * Номер версии скрипта
     * @var     int
     */
    public static $est_version_id   = 1082;
    
    /**
     * Идентификатор продукта
     * @var     string
     */
    public static $est_addon_id     = 'estcs';
    
    
    
    /**
     * Вывод списка заявок на организацию покупки
     * @return  XenForo_ControllerResponse_View
     */
    public function actionOrganizeRequests ( ) {
    
        $this->_assertRegistrationRequired();
        
        $visitor = XenForo_Visitor::getInstance();
        if (!$visitor->hasPermission('estcs', 'estcs_can_approve_org')) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_manage_no_permission'), 403);
        }

        $page = $this->_input->filterSingle('page', XenForo_Input::UINT);
        $threads_per_page = XenForo_Application::get('options')->discussionsPerPage;
        
        $threads = $this->_getOrganizeRequestModel()->getThreadsWithOrganizersAvaitingApproval(array (
            'join'                      => XenForo_Model_Thread::FETCH_FORUM | XenForo_Model_Thread::FETCH_USER,
            'readUserId'                => $visitor['user_id'],
            'postCountUserId'           => $visitor['user_id'],
            'permissionCombinationId'   => $visitor['permission_combination_id'],
            'perPage'                   => $threads_per_page,
            'page'                      => $page
        ));
        
        $threads = $this->_getShoppingModel()->unserializePermissionsInList($threads, 'node_permission_cache');
        $threads = $this->_getShoppingModel()->getViewableThreadsFromList($threads);
        
        $threads = $this->_prepareThreads($threads);

        $total_threads = $this->_getShoppingModel()->countThreadsJoinedByUser($visitor['user_id']);
        
        $this->canonicalizePageNumber($page, $threads_per_page, $total_threads, 'shopping/list-joined');
        
        return $this->responseView('Esthetic_CS_ViewPublic_Shopping_List', 'estcs_shoppings_list', array (
            'content_type'      => 'requests',
            'threads'           => $threads,
            'page'              => $page,
            'threads_per_page'  => $threads_per_page,
            'total_threads'     => $total_threads
        ));
    }
    
    
    /**
     * Формирование отображаемого списка тем
     * @param   array   $threads
     * @return  array
     */
    protected function _prepareThreads (array $threads) {
    
        $visitor = XenForo_Visitor::getInstance();

        $thread_model = $this->_getThreadModel();
        
        foreach ($threads AS &$thread) {
            
            if (!$visitor->hasNodePermissionsCached($thread['node_id'])) {
                $visitor->setNodePermissions($thread['node_id'], $thread['permissions']);
            }

            $thread = $thread_model->prepareThread($thread, $thread);

            $thread['canInlineMod'] = false;
            $thread['canEditThread'] = false;
            $thread['isIgnored'] = false;
        }

        return $threads;
    }
    
    
    /**
     * Подготовка контента сообщения об ошибке
     * @param       XenForo_Phrase      $phrase
     * @param       int                 $code
     * @return      XenForo_ControllerResponse_View
     */
    protected function _getErrorContent (XenForo_Phrase $phrase, $code = 200) {
    
        $ftp_helper = $this->getHelper('ForumThreadPost');
        
        return $this->responseView(
            'Esthetic_CS_ViewPublic_Shopping_Error',
            'estcs_error_page',
            array (
                'message'               => $phrase,
                'thread'                => false,
                'forum'                 => false,
                'shopping'              => false,
                'node_bread_crumbs'     => false
            )
        );
    }
    
    
	/**
     * Получение модели Shopping
	 * @return Esthetic_CS_Model_Shopping
	 */
	protected function _getShoppingModel ( ) {
		return $this->getModelFromCache('Esthetic_CS_Model_Shopping');
	}
    

    /**
     * Получение модели OrganizeRequest
     * @return  Esthetic_CS_Model_OrganizeRequest
     */
    protected function _getOrganizeRequestModel ( ) {
        return $this->getModelFromCache('Esthetic_CS_Model_OrganizeRequest');
    }
    

    /**
     * Получение модели Thread
     * @return  XenForo_Model_Thread
     */
    protected function _getThreadModel ( ) {
        return $this->getModelFromCache('XenForo_Model_Thread');
    }
}