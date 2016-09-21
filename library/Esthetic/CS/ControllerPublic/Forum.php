<?php
/**
 * Контроллер Forum
 * @package     Esthetic_CS
 */
class Esthetic_CS_ControllerPublic_Forum extends XFCP_Esthetic_CS_ControllerPublic_Forum {

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
     * Массив текущей ветки форума
     * @var     array
     */
    protected $estcs_forum          = false;
    
    
    /**
     * Дополнительные установки диспетчера
     * @param   string      $action
     */
    protected function _preDispatch ($action) {
        
        $response = parent::_preDispatch($action);
        
		$forum_id       = $this->_input->filterSingle('node_id', XenForo_Input::UINT);
		$forum_name     = $this->_input->filterSingle('node_name', XenForo_Input::STRING);
		$ftp_helper     = $this->getHelper('ForumThreadPost');
        
        if (!$forum_id && !$forum_name) {
            return $response;
        }
        
		if (!$forum = $ftp_helper->assertForumValidAndViewable($forum_id ? $forum_id : $forum_name)) {
            return $response;
        }
        
        $this->estcs_forum  = $forum;
        
        return $response;
    }
    
    
    /**
     * Дополнительные параметры страницы отображения списка тем
     * @return  XenForo_ControllerResponse_View
     */
    public function actionForum ( ) {
        
        $response = parent::actionForum();

        if (empty ($response->params['forum'])) {
            return $response;
        }
        
        if (empty ($response->params['forum']['estcs_type'])) {
            return $response;
        }
        
        $visitor = XenForo_Visitor::getInstance();
        
        $response->params['canPostEstCSThread'] = false;
        if ($response->params['forum']['estcs_type'] > 0 && ($visitor->hasPermission('estcs', 'estcs_can_create') || $visitor->hasPermission('estcs', 'estcs_can_manage'))) {
            $response->params['canPostEstCSThread'] = true;
        }
        
        if ($response->params['forum']['estcs_type'] == 1) {
            $response->params['canPostThread'] = false;
        }

        return $response;
    }
    
    
    /**
     * Диалог выбора типа создаваемой темы
     * @return  XenForo_ControllerResponse_View
     */
    public function actionThreadTypeDialog ( ) {
        
        $ftp_helper = $this->getHelper('ForumThreadPost');

        return $this->responseView(
            'Esthetic_CS_ViewPublic_Thread_CreateDialog',
            'estcs_thread_type_dialog',
            array (
                'nodeBreadCrumbs'       => $ftp_helper->getNodeBreadCrumbs($this->estcs_forum, false),
                'forum'                 => $this->estcs_forum
            )
        );
    }
    
    
    /**
     * Форма создания новой темы
     * @return  XenForo_ControllerResponse_View
     */
    public function actionCreateShoppingThread ( ) {
        
        $response = parent::actionCreateThread();

        if (empty ($this->estcs_forum['estcs_type'])) {
            return $response;
        }
        
        $visitor = XenForo_Visitor::getInstance();
        
        /**
         * Проверка права открывать новые совместные покупки
         */
        if (!$visitor->hasPermission('estcs', 'estcs_can_create') && !$visitor->hasPermission('estcs', 'estcs_can_manage')) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_no_rights_to_create_cs'), 403);
        }
        
        $options = XenForo_Application::get('options');
        
        
        $response->params['estcs'] = array (
            'currency_title'    => Esthetic_CS_Helper_Shopping::getCurrencyTitle(),
            'minimum_payment'   => $options->estcs_minimum_payment
        );
        
        $response->templateName = 'estcs_thread_create';
        
        return $response;
    }
    
    
    /**
     * Сохранение новой темы
     * @return  XenForo_ControllerResponse_View
     */
    public function actionAddThread ( ) {
        
        $visitor = XenForo_Visitor::getInstance();
        $options = XenForo_Application::get('options');
        
        if (!$this->_input->filterSingle('is_estcs_thread', XenForo_Input::UINT)) {
            return parent::actionAddThread();
        }
        
        /**
         * Проверка права открывать новые совместные покупки
         */
        if (!$visitor->hasPermission('estcs', 'estcs_can_create') && !$visitor->hasPermission('estcs', 'estcs_can_manage')) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_no_rights_to_create_cs'), 403);
        }
        
        $writer = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Shopping');
        $writer->bulkSet(array (
            'organizer_id'      => 0,
            'thread_id'         => 0,
            'price'             => round ($this->_input->filterSingle('estcs_price', XenForo_Input::FLOAT), 2),
            'participants'      => $this->_input->filterSingle('estcs_participants', XenForo_Input::UINT),
            'stage'             => 'preopen',
            'extended_data'     => array (
                'payment_details'           => false,
                'payment_conversation_id'   => 0,
                'product_details'           => false,
                'delivery_conversation_id'  => 0,
                'deny_reserve'              => false,
                'allow_post_buy'            => false,
                'organizer_role_id'         => 1,
                'private_thread_id'         => 0,
                'sort_members_list'         => false
            )
        ));
        
        $writer->preSave();
        $writer->save();
        
        if (false === ($shopping_id = $writer->get('shopping_id'))) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_shopping_create_error'));
        }
        
        unset ($writer);
        
        XenForo_Application::set('estcs_type', true);
        XenForo_Application::set('estcs_thread_id', 0);
        
        $response = parent::actionAddThread();
        
        /**
         * Если тема создана без ошибок, привязать ее к совместной покупке
         */
        $thread_id = (int)XenForo_Application::get('estcs_thread_id');
        if ($thread_id > 0) {
            $writer = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Shopping');
            $writer->setExistingData($shopping_id);
            $writer->set('thread_id', $thread_id);
            $writer->set('stage', 'open');
            $writer->save();
            
            /**
             * Внесение в список организатора
             */
            $writer = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Participant');
            $writer->bulkSet(array (
                'shopping_id'       => $shopping_id,
                'user_id'           => $visitor['user_id'],
                'is_primary'        => 1,
                'is_payed'          => 0
            ));
            $writer->save();
        } else {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_thread_create_error'));
        }
        
        return $response;
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
                'forum'                 => $this->estcs_forum,
                'shopping'              => false,
                'node_bread_crumbs'     => $this->estcs_forum != false ? $ftp_helper->getNodeBreadCrumbs($this->estcs_forum) : false
            )
        );
    }
}