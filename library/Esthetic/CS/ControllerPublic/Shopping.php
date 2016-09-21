<?php
/**
 * Контроллер 'Shopping'
 * @package     Esthetic_CS
 */
class Esthetic_CS_ControllerPublic_Shopping extends XenForo_ControllerPublic_Abstract {
    
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
     * @var array
     */
    protected $_shopping            = false;
    
    /**
     * @var array
     */
    protected $_participants        = false;
    
    /**
     * @var array
     */
    protected $_forum               = false;
    
    /**
     * @var array
     */
    protected $_thread              = false;
    
    /**
     * @var array
     */
    protected $_node_bread_crumbs   = false;
    
    
    
    
    /**
     * Диспетчер контроллера
     * @param   string      $action
     * @return  null
     */
    protected function _preDispatch ($action) {
        
        $this->_shopping = $this->_getShoppingModel()->getShoppingById($this->_input->filterSingle('shopping_id', XenForo_Input::UINT));
        
        if (!empty ($this->_shopping)) {
            $ftp_helper = $this->getHelper('ForumThreadPost');
            
            list ($thread_fetch_options, $forum_fetch_options) = $this->_getThreadForumFetchOptions();
            list ($this->_thread, $this->_forum) = $ftp_helper->assertThreadValidAndViewable($this->_shopping['thread_id'], $thread_fetch_options, $forum_fetch_options);
            
            $this->_node_bread_crumbs = $ftp_helper->getNodeBreadCrumbs($this->_forum);
        }
        
        return parent::_preDispatch($action);
    }
    
    
    /**
     * Вывод списка покупок, в которых пользователь принимает(ал) участие
     * @return  XenForo_ControllerResponse_View
     */
    public function actionListJoined ( ) {
    
        $this->_assertRegistrationRequired();
        
        $visitor = XenForo_Visitor::getInstance();

        $page = $this->_input->filterSingle('page', XenForo_Input::UINT);
        $threads_per_page = XenForo_Application::get('options')->discussionsPerPage;
        
        $threads = $this->_getShoppingModel()->getThreadsJoinedByUser($visitor['user_id'], array(
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
            'content_type'      => 'joined',
            'threads'           => $threads,
            'page'              => $page,
            'threads_per_page'  => $threads_per_page,
            'total_threads'     => $total_threads
        ));
    }
    
    
    /**
     * Вывод списка покупок, в которых пользователь является(лся) организатором
     * @return  XenForo_ControllerResponse_View
     */
    public function actionListOrganized ( ) {
    
        $this->_assertRegistrationRequired();
        
        $visitor = XenForo_Visitor::getInstance();

        $page = $this->_input->filterSingle('page', XenForo_Input::UINT);
        $threads_per_page = XenForo_Application::get('options')->discussionsPerPage;
        
        $threads = $this->_getShoppingModel()->getThreadsOrganizedByUser($visitor['user_id'], array(
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

        $total_threads = $this->_getShoppingModel()->countThreadsOrganizedByUser($visitor['user_id']);
        
        $this->canonicalizePageNumber($page, $threads_per_page, $total_threads, 'shopping/list-organized');
        
        return $this->responseView('Esthetic_CS_ViewPublic_Shopping_List', 'estcs_shoppings_list', array (
            'content_type'      => 'organized',
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
     * Вывод диалога регистрации в совместной покупке
     * @return  XenForo_ControllerResponse_View
     */
    public function actionSignupDialog ( ) {
        
        $this->_assertRegistrationRequired();
        
        $signup_state = $this->_isSignupAllowed();
        if (true !== $signup_state) {
            return $signup_state;
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $visitor = XenForo_Visitor::getInstance();
        
        $force_reserve = false;
        if ($visitor->hasPermission('estcs', 'estcs_can_join_reserve') && !$visitor->hasPermission('estcs', 'estcs_can_join_primary')) {
            $force_reserve = true;
        }
        
        if (Esthetic_CS_Helper_Shopping::getStageId($this->_shopping) > 1) {
            $force_reserve = true;
        }
        
        /**
         * Если пользователь может записаться только в резерв, но таков запрещен - перейти на страницу покупки
         */
        if (!empty ($this->_shopping['extended_data']['deny_reserve']) && $force_reserve) {
            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildPublicLink('threads', $this->_thread)
            );
        }

        return $this->responseView(
            'Esthetic_CS_ViewPublic_Shopping_Signup',
            'estcs_signup_dialog',
            array (
                'shopping'              => $this->_shopping,
                'forum'                 => $this->_forum,
                'thread'                => $this->_thread,
                'node_bread_crumbs'     => $this->_node_bread_crumbs,
                'can_join_anonymous'    => $visitor->hasPermission('estcs', 'estcs_can_join_anonymous'),
                'can_join_reserve'      => $visitor->hasPermission('estcs', 'estcs_can_join_reserve'),
                'anonymous_fee'         => round (XenForo_Application::get('options')->estcs_anonymous_fee, 2),
                'currency_title'        => Esthetic_CS_Helper_Shopping::getCurrencyTitle(),
                'force_reserve'         => $force_reserve
            )
        );
    }
    
    
    /**
     * Вывод диалога покупки доступа в завершенную покупку
     * @return  XenForo_ControllerResponse_View
     */
    public function actionPostBuyDialog ( ) {
        
        $this->_assertRegistrationRequired();
        
        $signup_state = $this->_isSignupAllowed(true);
        if (true !== $signup_state) {
            return $signup_state;
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $visitor = XenForo_Visitor::getInstance();

        return $this->responseView(
            'Esthetic_CS_ViewPublic_Shopping_PostBuy',
            'estcs_post_buy_dialog',
            array (
                'shopping'              => $this->_shopping,
                'forum'                 => $this->_forum,
                'thread'                => $this->_thread,
                'node_bread_crumbs'     => $this->_node_bread_crumbs,
                'can_join_anonymous'    => $visitor->hasPermission('estcs', 'estcs_can_join_anonymous'),
                'anonymous_fee'         => round (XenForo_Application::get('options')->estcs_anonymous_fee, 2),
                'currency_title'        => Esthetic_CS_Helper_Shopping::getCurrencyTitle()
            )
        );
    }
    
    
    /**
     * Запись в список участников покупки
     * @return  XenForo_ControllerResponse_View
     */
    public function actionSignup ( ) {
        
        $signup_state = $this->_isSignupAllowed();
        if (true !== $signup_state) {
            return $signup_state;
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $visitor = XenForo_Visitor::getInstance();
        $options = XenForo_Application::get('options');
        
        $participants = $this->_getParticipantModel()->prepareParticipantsList($this->_getParticipants());
        
        /**
         * Определение списка
         */
        $is_primary = true;
        if ($this->_shopping['stage'] != 'open' || (count ($participants['general']) >= $this->_shopping['participants'] && $this->_shopping['participants'] > 0)) {
            $is_primary = false;
        }
        
        if (!$visitor->hasPermission('estcs', 'estcs_can_join_primary')) {
            $is_primary = false;
        }
        
        if ($this->_input->filterSingle('sign_to_reserve', XenForo_Input::STRING)) {
            $is_primary = false;
        }
        
        if (!$is_primary && $this->_getShoppingData('deny_reserve')) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_reserve_denied'), 400);
        }
        
        if (!$is_primary && !$visitor->hasPermission('estcs', 'estcs_can_join_reserve')) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_reserve_denied_for_user'), 400);
        }
        
        /**
         * Определение анонимности
         */
        $is_anonymous = false;
        if ($this->_input->filterSingle('is_anonymous', XenForo_Input::STRING)) {
            $is_anonymous = true;
        }
        
        if (!XenForo_Visitor::getInstance()->hasPermission('estcs', 'estcs_can_join_anonymous')) {
            $is_anonymous = false;
        }
        
        $writer = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Participant');
        $writer->bulkSet(array (
            'shopping_id'       => $this->_shopping['shopping_id'],
            'user_id'           => XenForo_Visitor::getUserId(),
            'is_primary'        => $is_primary,
            'is_payed'          => 0,
            'is_anonymous'      => $is_anonymous,
            'vote'              => 1,
            'organizer_vote'    => 1
        ));
        $writer->save();
        
        $this->_sendNewParticipantAlert($is_anonymous);
        
        /**
         * Проверка прав доступа к приватным темам
         */
        if ($options->estPD_enabled && $options->estcs_allow_pd) {
            Esthetic_CS_Crossover_PD::addUserByRule($this->_shopping, $visitor['user_id']);
        }
        
        /**
         * Проверка прав доступа к переписке
         */
        if (!empty ($this->_shopping['extended_data']['payment_conversation_id']) && !empty ($this->_shopping['organizer_id'])) {
            $dw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');
            $dw->setExistingData($this->_shopping['extended_data']['payment_conversation_id']);
            $dw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_ACTION_USER, $this->_getUserModel()->getUserById($this->_shopping['organizer_id']));
            $dw->addRecipientUserIds(array ($visitor['user_id']));
            $dw->save();
        }
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Запись в дополнительный список участников покупки
     * @return  XenForo_ControllerResponse_View
     */
    public function actionPostBuySignup ( ) {
        
        $signup_state = $this->_isSignupAllowed(true);
        if (true !== $signup_state) {
            return $signup_state;
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $visitor = XenForo_Visitor::getInstance();
        $options = XenForo_Application::get('options');
        
        /**
         * Определение анонимности
         */
        $is_anonymous = false;
        if ($this->_input->filterSingle('is_anonymous', XenForo_Input::STRING)) {
            $is_anonymous = true;
        }
        
        if (!XenForo_Visitor::getInstance()->hasPermission('estcs', 'estcs_can_join_anonymous')) {
            $is_anonymous = false;
        }
        
        $writer = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Participant');
        $writer->bulkSet(array (
            'shopping_id'       => $this->_shopping['shopping_id'],
            'user_id'           => XenForo_Visitor::getUserId(),
            'is_primary'        => 0,
            'is_additional'     => 1,
            'is_payed'          => 0,
            'is_anonymous'      => $is_anonymous,
            'vote'              => 1,
            'organizer_vote'    => 1
        ));
        $writer->save();
        
        $this->_sendNewParticipantAlert(true);
        
        /**
         * Проверка прав доступа к приватным темам
         */
        if ($options->estPD_enabled && $options->estcs_allow_pd) {
            Esthetic_CS_Crossover_PD::addUserByRule($this->_shopping, $visitor['user_id']);
        }
        
        /**
         * Проверка прав доступа к переписке
         */
        if (!empty ($this->_shopping['extended_data']['payment_conversation_id']) && !empty ($this->_shopping['organizer_id'])) {
            $dw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');
            $dw->setExistingData($this->_shopping['extended_data']['payment_conversation_id']);
            $dw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_ACTION_USER, $this->_getUserModel()->getUserById($this->_shopping['organizer_id']));
            $dw->addRecipientUserIds(array ($visitor['user_id']));
            $dw->save();
        }
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Отправка уведомлений о новом участнике покупки
     * @param   boolean     $to_organizer_only
     * @return  null
     */
    protected function _sendNewParticipantAlert ($to_organizer_only = false) {
        
        if ($to_organizer_only && !$this->_shopping['organizer_id']) {
            return;
        }
        
        if ($to_organizer_only) {
            Esthetic_CS_Helper_Alert::createNewParticipantAlert($this->_shopping, false, $this->_thread);
        } else {
            Esthetic_CS_Helper_Alert::createNewParticipantAlert($this->_shopping, $this->_getParticipants(), $this->_thread);
        }
    }
    
    
    /**
     * Оценка работы организатора
     * @return  XenForo_ControllerResponse_Redirect
     */
    public function actionSwitchAnonymous ( ) {
    
        $visitor = XenForo_Visitor::getInstance();
        
        $participant_state = $this->_participantCheckout();
        if (true !== $participant_state) {
            return $participant_state;
        }
        
        if (Esthetic_CS_Helper_Shopping::getStageId($this->_shopping['stage']) > 2) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_unnable_change_anonymous_mode'), 400);
        }
        
        $participant = $this->_getParticipantModel()->getParticipantRecord($this->_shopping['shopping_id'], $visitor['user_id']);
        
        if ($participant['is_payed']) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_unnable_change_anonymous_when_paid'), 400);
        }
        
        $is_anonymous = true;
        if ($participant['is_anonymous']) {
            $is_anonymous = false;
        }
        
        $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Participant');
        $dw->setExistingData(array ('shopping_id' => $this->_shopping['shopping_id'], 'user_id' => $visitor['user_id']));
        $dw->set('is_anonymous', intval ($is_anonymous));
        $dw->save();

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Оценка работы организатора
     * @return  XenForo_ControllerResponse_Redirect
     */
    public function actionVoteOrganizer ( ) {
    
        $visitor = XenForo_Visitor::getInstance();
        
        $participant_state = $this->_participantCheckout();
        if (true !== $participant_state) {
            return $participant_state;
        }
        
        if ($this->_shopping['stage'] != 'finished' || !$this->_shopping['organizer_id']) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_unnable_vote_now'), 400);
        }
        
        $vote = $this->_input->filterSingle('vote', XenForo_Input::UINT);
        
        $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Participant');
        $dw->setExistingData(array ('shopping_id' => $this->_shopping['shopping_id'], 'user_id' => $visitor['user_id']));
        $dw->set('organizer_vote', intval (!empty ($vote)));
        $dw->save();

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread),
            array (
                'vote'      => intval (!empty ($vote)),
                'message'   => new XenForo_Phrase ('estcs_opinion_updated')
            )
        );
    }
    
    
    /**
     * Переход в основную группу
     * @return  XenForo_ControllerResponse_Redirect
     */
    public function actionChangeToPrimary ( ) {
    
        $visitor = XenForo_Visitor::getInstance();
        $options = XenForo_Application::get('options');
        
        $participant_state = $this->_participantCheckout();
        if (true !== $participant_state) {
            return $participant_state;
        }
        
        if ($this->_shopping['stage'] != 'open') {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_unnable_to_change_list'), 400);
        }
        
        if (!$visitor->hasPermission('estcs', 'estcs_can_join_primary')) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_join_primary_denied'), 400);
        }
        
        $participants = $this->_getParticipantModel()->prepareParticipantsList($this->_getParticipants());
        
        if ($this->_shopping['participants'] <= count ($participants['general']) && $this->_shopping['participants'] > 0) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_primary_list_full'), 400);
        }
        
        $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Participant');
        $dw->setExistingData(array ('shopping_id' => $this->_shopping['shopping_id'], 'user_id' => $visitor['user_id']));
        $dw->set('is_primary', 1);
        $dw->save();
    
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Переход в резерв
     * @return  XenForo_ControllerResponse_Redirect
     */
    public function actionChangeToReserve ( ) {
    
        $visitor = XenForo_Visitor::getInstance();
        
        $participant_state = $this->_participantCheckout();
        if (true !== $participant_state) {
            return $participant_state;
        }
        
        if ($this->_shopping['stage'] != 'open') {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_unnable_to_change_list'), 400);
        }
        
        if (!$visitor->hasPermission('estcs', 'estcs_can_join_reserve')) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_join_reserve_denied'), 400);
        }
        
        if ($this->_getShoppingData('deny_reserve')) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_reserve_denied'), 400);
        }
        
        if ($this->_shopping['organizer_id'] != $visitor['user_id']) {
            $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Participant');
            $dw->setExistingData(array ('shopping_id' => $this->_shopping['shopping_id'], 'user_id' => $visitor['user_id']));
            $dw->set('is_primary', 0);
            $dw->save();
        }
    
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Выход из группы
     * @return  XenForo_ControllerResponse_Redirect
     */
    public function actionLeave ( ) {
    
        $visitor = XenForo_Visitor::getInstance();
        $options = XenForo_Application::get('options');
        
        $participant_state = $this->_participantCheckout();
        if (true !== $participant_state) {
            return $participant_state;
        }
        
        if ($this->_shopping['stage'] != 'open') {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_unnable_to_leave'), 400);
        }
        
        if ($this->_shopping['organizer_id'] != $visitor['user_id']) {
            $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Participant');
            $dw->setExistingData(array ('shopping_id' => $this->_shopping['shopping_id'], 'user_id' => $visitor['user_id']));
            $dw->delete();
        }
        
        if ($options->estPD_enabled && $options->estcs_allow_pd && !empty($this->_shopping['extended_data']['private_thread_id'])) {
            Esthetic_CS_Crossover_PD::removeUserFromDiscussion($this->_shopping['extended_data']['private_thread_id'], $visitor['user_id']);
        }
    
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Отметка о получении продукта
     * @return  XenForo_ControllerResponse_Redirect
     */
    public function actionDelivered ( ) {
    
        $visitor = XenForo_Visitor::getInstance();
        $options = XenForo_Application::get('options');
        
        $participant_state = $this->_participantCheckout();
        if (true !== $participant_state) {
            return $participant_state;
        }
        
        if ($this->_shopping['stage'] != 'finished') {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_unnable_to_mark_as_delivered'), 400);
        }
        
        $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Participant');
        $dw->setExistingData(array ('shopping_id' => $this->_shopping['shopping_id'], 'user_id' => $visitor['user_id']));
        $dw->set('is_delivered', 1);
        $dw->save();
        
        if ($options->estPD_enabled && $options->estcs_allow_pd) {
            Esthetic_CS_Crossover_PD::addUserByRule($this->_shopping, $visitor['user_id']);
        }
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Одобрение организатора
     * @return  XenForo_ControllerResponse_Redirect
     */
    public function actionOrganizerApprovement ( ) {
    
        $visitor = XenForo_Visitor::getInstance();
        
        $participant_state = $this->_participantCheckout();
        if (true !== $participant_state) {
            return $participant_state;
        }
        
        if ($this->_shopping['stage'] != 'open' || empty ($this->_shopping['organizer_id'])) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_unnable_to_approve_organizer'), 400);
        }
        
        $vote = $this->_input->filterSingle('vote', XenForo_Input::UINT);
        
        $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Participant');
        $dw->setExistingData(array ('shopping_id' => $this->_shopping['shopping_id'], 'user_id' => $visitor['user_id']));
        $dw->set('is_accepting_organizer', $vote);
        $dw->save();
        
        $disapproved_by = intval ($this->_getParticipantModel()->getDisapprovedCount($this->_shopping['shopping_id']));
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread),
            array (
                'vote'      => intval (!empty ($vote)),
                'message'   => $vote > 0 ? new XenForo_Phrase ('estcs_organizer_approved') : new XenForo_Phrase ('estcs_organizer_disapproved'),
                'text'      => $disapproved_by > 0 ? new XenForo_Phrase ('estcs_disapproved_by_x', array ('x' => $disapproved_by)) : ''
            )
        );
    }
    
    
    /**
     * Проверка параметров участника покупки
     * @return  true|object
     */
    protected function _participantCheckout ( ) {
        
        $this->_assertRegistrationRequired();
        
        if (empty ($this->_shopping)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_shopping_not_found'), 404);
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $participants = $this->_getParticipants();
        
        if (!Esthetic_CS_Helper_Shopping::isParticipantOf($participants)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_not_participant_of_shopping'), 404);
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        return true;
    }
    
    
    /**
     * Проверка доступности регистрации в совместной покупке
     * @param   boolean     $is_post_bue
     * @return  true|object
     */
    protected function _isSignupAllowed ($is_post_buy = false) {
    
        if (empty ($this->_shopping)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_shopping_not_found'), 404);
        }
        
        $visitor = XenForo_Visitor::getInstance();
        
        if (
            !$visitor->hasPermission('estcs', 'estcs_can_join_primary') && 
            !$visitor->hasPermission('estcs', 'estcs_can_join_reserve') && 
            !$visitor->hasPermission('estcs', 'estcs_can_post_buy')) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_join_permission_required'), 403);
        }
        
        if ($visitor['user_id'] == $this->_shopping['organizer_id']) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_organizers_cant_signup'), 403);
        }
        
        if (Esthetic_CS_Helper_Shopping::getStageId($this->_shopping) > 3 && !$is_post_buy) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_signup_denied'), 403);
        }
        
        if (Esthetic_CS_Helper_Shopping::getStageId($this->_shopping) < 4 && $is_post_buy) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_post_buy_not_allowed_yet'), 403);
        }
        
        if ($is_post_buy && empty ($this->_shopping['extended_data']['allow_post_buy'])) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_post_buy_denied_by_organizer'), 403);
        }
        
        if ($p_list = $this->_getParticipantModel()->getByShoppingId($this->_shopping['shopping_id'])) {
            if (Esthetic_CS_Helper_Shopping::isParticipantOf($p_list)) {
                return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_already_signed'), 403);
            }
        }
        
        return true;
    }
    
    
    /**
     * Вывод диалога подтверждения организации покупки
     * @return  XenForo_ControllerResponse_View
     */
    public function actionOrganizeDialog ( ) {
    
        $this->_assertRegistrationRequired();
        
        $organize_state = $this->_isOrganizeAllowed($this->_shopping);
        if (true !== $organize_state) {
            return $organize_state;
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $options = XenForo_Application::get('options');
        
        return $this->responseView(
            'Esthetic_CS_ViewPublic_Shopping_Organize',
            'estcs_organize_dialog',
            array (
                'shopping'          => $this->_shopping,
                'thread'            => $this->_thread,
                'forum'             => $this->_forum,
                'node_bread_crumbs' => $this->_node_bread_crumbs,
                
                'organizer_fee'     => Esthetic_CS_Helper_Shopping::getOrganizerFee($this->_shopping, XenForo_Visitor::getInstance()),
                'currency_title'    => Esthetic_CS_Helper_Shopping::getCurrencyTitle()
            )
        );
    }
    
    
    /**
     * Регистрация организатора покупки
     * @return  XenForo_ControllerResponse_Redirect
     */
    public function actionOrganize ( ) {
    
        $this->_assertRegistrationRequired();
        
        $organize_state = $this->_isOrganizeAllowed($this->_shopping);
        if (true !== $organize_state) {
            return $organize_state;
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $visitor = XenForo_Visitor::getInstance();
        
        /**
         * Проверка организатора на необходимость подтверждения администратором
         */
        if ($visitor->hasPermission('estcs', 'estcs_moderator_confirm') || $visitor->hasPermission('estcs', 'estcs_can_manage')) {
            $this->_assignOrganizer(XenForo_Visitor::getUserId(), true);
        } else if (!$this->_getOrganizeRequestModel()->getOrganizerRecord($this->_shopping['shopping_id'], $visitor['user_id'])) {
            
            $organizers = $this->_getOrganizeRequestModel()->getOrganizersByShoppingId($this->_shopping['shopping_id'], false);
            
            $options = XenForo_Application::get('options');
            
            if (count ($organizers) >= $options->estcs_org_request_limit) {
                return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_organizer_requests_limit'), 404);
            }
            
            /**
             * Формирование заявки на органимзацию
             */
            $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_OrganizeRequest');
            $dw->set('shopping_id', $this->_shopping['shopping_id']);
            $dw->set('user_id', $visitor['user_id']);
            $dw->save();
        }
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Отмена заявки на организацию
     * @return  XenForo_ControllerResponse_Redirect
     */
    public function actionCancelOrganizeRequest ( ) {
    
        $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_OrganizeRequest');
        $dw->setExistingData(array (
            'shopping_id'       => $this->_shopping['shopping_id'],
            'user_id'           => XenForo_Visitor::getUserId()
        ), true);
        $dw->delete();
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Проверка кандидатуры организатора покупки
     * @return  true|object
     */
    protected function _isOrganizeAllowed ($shopping) {
        
        if (empty ($shopping)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_shopping_not_found'), 404);
        }
        
        $visitor = XenForo_Visitor::getInstance();
        
        if (!$visitor->hasPermission('estcs', 'estcs_can_organize')) { 
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_organize_permission_required'), 403);
        }
        
        if ($visitor->hasPermission('estcs', 'estcs_organizer_limit') > 0 && !$visitor->hasPermission('estcs', 'estcs_can_manage')) {
            if ($this->_getShoppingModel()->getCurrentOrganizedShoppings($visitor['user_id']) >= $visitor->hasPermission('estcs', 'estcs_organizer_limit'))
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_shoppings_limit_reached', array ('limit' => $visitor->hasPermission('estcs', 'estcs_organizer_limit'))), 400);
        }
        
        if (Esthetic_CS_Helper_Shopping::getStageId($this->_shopping['stage']) > 3) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_unnable_organize_clossed_shopping'), 400);
        }
        
        if ($shopping['organizer_id'] > 0) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_organizer_already_exists'), 403);
        }
        
        return true;
    }
    
    
    /**
     * Параметры получения данных о форуме и теме
     * @return  array
     */
	protected function _getThreadForumFetchOptions ( ) {
    
        $visitor = XenForo_Visitor::getInstance();

        $thread_fetch_options = array (
            'readUserId' => $visitor['user_id']
        );
        
        $forum_fetch_options = array(
            'readUserId' => $visitor['user_id']
        );

        return array ($thread_fetch_options, $forum_fetch_options);
	}
    
    
    /**
     * Отправка уведомлений указанным пользователям
     * @return  XenForo_ControllerResponse_View
     */
    public function actionSendAlert ( ) {
    
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $organizer_state;
        }
    
        $visitor = XenForo_Visitor::getInstance();
        
        $message = $this->_input->filterSingle('estcs_alert_message', XenForo_Input::STRING);
        
        if (strlen ($message) <= 0) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_alert_enter_message'));
        }
        
        if (strlen (utf8_decode ($message)) > 250) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_alert_message_to_long'));
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $participants = $this->_getCheckedParticipants();
        
        if (empty ($participants)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_recepients_not_found'), 404);
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        Esthetic_CS_Helper_Alert::sendById($participants, array (
            'user_id'           => $visitor['user_id'],
            'username'          => $visitor['username'],
            'extra_data'        => array (
                'alert_type'        => 'custom',
                'message'           => sprintf ('<a href="%s">%s</a>', XenForo_Link::buildPublicLink('threads',  $this->_thread), $message)
            )
        ));
        
        return $this->responseView(
            'Esthetic_CS_ViewPublic_Messages_View',
            'estcs_message_page',
            array (
                'title'             => new XenForo_Phrase ('estcs_alert_send_title'),
                'message'           => new XenForo_Phrase ('estcs_alert_sent'),
                'forum'             => $this->_forum,
                'thread'            => $this->_thread,
                'node_bread_crumbs' => $this->_node_bread_crumbs,
                'redirect'          => array (
                    'delay'             => 5,
                    'link'              => XenForo_Link::buildPublicLink('full:threads',  $this->_thread)
                )
            )
        );
    }
    
    
    /**
     * Перемещение указанных пользователей в основной список
     * @return  XenForo_ControllerResponse_Redirect
     */
    public function actionMoveToPrimary ( ) {
    
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $organizer_state;
        }
    
        $visitor = XenForo_Visitor::getInstance();
        
        $participants = $this->_getCheckedParticipants();
        
        if (empty ($participants)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_recepients_not_found'), 404);
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $this->_getParticipantModel()->moveToPrimary($participants, $this->_shopping['shopping_id']);
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Перемещение указанных пользователей в резерв
     * @return  XenForo_ControllerResponse_Redirect
     */
    public function actionMoveToReserve ( ) {
    
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $organizer_state;
        }
    
        $visitor = XenForo_Visitor::getInstance();
        
        $participants = $this->_getCheckedParticipants();
        
        if (empty ($participants)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_recepients_not_found'), 404);
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }

        foreach ($participants as $user) {
            if ($user == $this->_shopping['organizer_id']) {
                return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_unnable_to_apply_with_organizer'), 400);
            }
        }
        
        $this->_getParticipantModel()->moveToReserve($participants, $this->_shopping['shopping_id']);
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Установка статуса "оплачено" указанным пользователям
     * @return  XenForo_ControllerResponse_Redirect
     */
    public function actionMarkAsPaid ( ) {
    
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $organizer_state;
        }
        
        $participants = $this->_getCheckedParticipants();
        $participants_current = $this->_getParticipants();
        
        /**
         * Удаление из списка пользователей, у которых уже установлена отметка об оплате
         */
        if (!empty ($participants_current)) {
            foreach ($participants_current as $participant) {
                
                if (!$participant['is_payed']) {
                    continue;
                }
                
                if (isset ($participants[$participant['user_id']])) {
                    unset ($participants[$participant['user_id']]);
                }
            }
        }
        
        if (empty ($participants)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_recepients_not_found'), 404);
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $this->_getParticipantModel()->updatePaymentStatus($participants, $this->_shopping['shopping_id'], true);
        
        Esthetic_CS_Helper_Alert::createMarkedPaidAlert($this->_shopping, $participants, $this->_thread);
        $this->_buildAdditionalEstimate($participants);
        
        $this->_privateDiscussionsCheck();
        $this->_addPaidToConversation($participants);
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Установка статуса "не оплачено" указанным пользователям
     * @return  XenForo_ControllerResponse_Redirect
     */
    public function actionMarkAsNotPaid ( ) {
    
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $organizer_state;
        }
        
        $participants = $this->_getCheckedParticipants();
        
        if (empty ($participants)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_recepients_not_found'), 404);
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $this->_getParticipantModel()->updatePaymentStatus($participants, $this->_shopping['shopping_id'], false);
        
        $this->_privateDiscussionsCheck();
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Удаление пользователей из списка совместной покупки
     * @return  XenForo_ControllerResponse_Redirect
     */
    public function actionKick ( ) {
    
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $organizer_state;
        }
    
        $visitor = XenForo_Visitor::getInstance();
        
        $participants = $this->_getCheckedParticipants(true);
        
        if (empty ($participants)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_recepients_not_found'), 404);
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        foreach ($participants as $user) {
            if ($user['is_payed']) {
                return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_list_has_payed_participants'), 400);
            }
            
            if ($user['user_id'] == $this->_shopping['organizer_id']) {
                return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_unnable_to_apply_with_organizer'), 400);
            }
        }

        $participants = $this->_getCheckedParticipants();
        $this->_getParticipantModel()->removeParticipants($participants, $this->_shopping['shopping_id']);
        Esthetic_CS_Helper_Alert::createKickAlert($this->_shopping, $this->_getCheckedParticipants(true));
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Оценка участников покупки
     * @return  XenForo_ControllerResponse_Redirect
     */
    public function actionVote ( ) {
    
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $organizer_state;
        }
    
        $visitor = XenForo_Visitor::getInstance();
        
        $participants = $this->_getCheckedParticipants();
        
        if (empty ($participants)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_recepients_not_found'), 404);
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $vote = $this->_input->filterSingle('estcs_vote', XenForo_Input::UINT);
        
        $this->_getParticipantModel()->voteParticipants($participants, $this->_shopping['shopping_id'], $vote);
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Установка статуса "получено" указанным пользователям
     * @return  XenForo_ControllerResponse_Redirect
     */
    public function actionMarkAsDelivered ( ) {

        $visitor = XenForo_Visitor::getInstance();
        
        if (!$visitor->hasPermission('estcs', 'estcs_can_manage')) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_manage_no_permission'), 403);
        }
        
        $participants = $this->_getCheckedParticipants();
        
        if (empty ($participants)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_recepients_not_found'), 404);
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $this->_getParticipantModel()->updateDeliveryStatus($participants, $this->_shopping['shopping_id'], true);
        
        $this->_privateDiscussionsCheck();
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Очистка статуса "получено" указанным пользователям
     * @return  XenForo_ControllerResponse_Redirect
     */
    public function actionMarkAsNotDelivered ( ) {

        $visitor = XenForo_Visitor::getInstance();
        
        if (!$visitor->hasPermission('estcs', 'estcs_can_manage')) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_manage_no_permission'), 403);
        }
        
        $participants = $this->_getCheckedParticipants();
        
        if (empty ($participants)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_recepients_not_found'), 404);
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $this->_getParticipantModel()->updateDeliveryStatus($participants, $this->_shopping['shopping_id'], false);
        
        $this->_privateDiscussionsCheck();
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Таблица начислений платежей пользователей
     * @return  XenForo_ControllerResponse_View
     */
    public function actionPaymentsTable ( ) {
        
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $organizer_state;
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $options = XenForo_Application::get('options');
        
        $participants = $this->_getParticipants(true, true);
        $estimate = $this->_getShoppingModel()->getEstimate($this->_shopping, false, $participants);
        
        foreach ($participants as &$participant) {
            $participant['payment'] = $estimate['estimated']['payment'];
            if ($participant['is_anonymous']) {
                $participant['payment'] += $options->estcs_anonymous_fee;
            }
            
            $participant['payment'] = ($participant['payment'] - (int)$participant['payment'] > 0) ? sprintf ('%0.2f', $participant['payment']) : sprintf ('%d', (int)$participant['payment']);
        }
        
        return $this->responseView(
            'Esthetic_CS_ViewPublic_Shopping_PaymentsTable',
            'estcs_shopping_payments_table',
            array (
                'shopping'          => $this->_shopping,
                'thread'            => $this->_thread,
                'forum'             => $this->_forum,
                'node_bread_crumbs' => $this->_node_bread_crumbs,
                
                'participants'      => $this->_getParticipantModel()->prepareParticipantsList($participants),
                'payment'           => ($estimate['estimated']['payment'] - (int)$estimate['estimated']['payment'] > 0) ? sprintf ('%0.2f', $estimate['estimated']['payment']) : sprintf ('%d', (int)$estimate['estimated']['payment']),
                'anonymous_fee'     => ($options->estcs_anonymous_fee - (int)$options->estcs_anonymous_fee > 0) ? sprintf ('%0.2f', $options->estcs_anonymous_fee) : sprintf ('%d', (int)$options->estcs_anonymous_fee),
                'currency_title'    => Esthetic_CS_Helper_Shopping::getCurrencyTitle()
            )
        );
    }
    
    
    /**
     * Установка отметок об оплате
     * @return  XenForo_ControllerResponse_Redirect
     */
    public function actionMarkPaidInline ( ) {
        
        $visitor = XenForo_Visitor::getInstance();
        
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $this->responseError(new XenForo_Phrase ('estcs_error_access_permission_violation'), 400);
        }
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->responseError(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        if (!$this->_request->isPost()) {
            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildPublicLink('shopping/payments-table', $this->_shopping)
            );
        }
        
        $data = $this->_input->filter(array (
            'user_id'   => XenForo_Input::UINT,
            'is_paid'   => XenForo_Input::UINT
        ));
        
        $participant = $this->_getParticipantModel()->getParticipantRecord($this->_shopping['shopping_id'], $data['user_id']);
        if (empty ($participant)) {
            return $this->responseError(new XenForo_Phrase ('estcs_error_participant_not_found'), 404);
        }
        
        if (!$participant['is_additional'] && !$visitor->hasPermission('estcs', 'estcs_can_manage')) {
            return $this->responseError(new XenForo_Phrase ('estcs_error_cant_change_payment_state_in_finished_shopping'), 400);
        }
        
        $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Participant');
        $dw->setExistingData($participant);
        $dw->set('is_payed', $data['is_paid']);
        $dw->save();
        
        $ftp_helper = $this->getHelper('ForumThreadPost');
        $participant['is_payed'] = $dw->get('is_payed');
        
        if ($participant['is_payed']) {
            $participants = array (
                $participant['user_id'] => $participant['user_id']
            );

            Esthetic_CS_Helper_Alert::createMarkedPaidAlert($this->_shopping, $participants, $this->_thread);
            $this->_buildAdditionalEstimate($participants);
            
            $this->_privateDiscussionsCheck();
            $this->_addPaidToConversation($participants);
        }
        
        return $this->responseView(
            'Esthetic_CS_ViewPublic_Shopping_Inline_MarkPaid',
            'estcs_error_page',
            array (
                'message'               => new XenForo_Phrase ('estcs_error_this_request_can_not_be_returned_as_page'),
                'thread'                => $this->_thread,
                'forum'                 => $this->_forum,
                'shopping'              => $this->_shopping,
                'node_bread_crumbs'     => $this->_forum != false ? $ftp_helper->getNodeBreadCrumbs($this->_forum) : false,
                
                'participant'           => $participant
            )
        );
    }
    
    
    /**
     * Редактирование установок совместной покупки
     * @return  XenForo_ControllerResponse_Redirect
     */
    public function actionEdit ( ) {
        
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $organizer_state;
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $visitor = XenForo_Visitor::getInstance();
        $options = XenForo_Application::get('options');
        
        if ($this->_shopping['payment']) {
            $this->_shopping['payment'] = round ($this->_shopping['payment'], 2);
        }
        
        $collection_date = array ('day' => 0, 'month' => 0, 'year' => 0);
        if (!empty ($this->_shopping['collection_date'])) {
            $collection_date = array (
                'day'       => (int)date ('d', $this->_shopping['collection_date']),
                'month'     => (int)date ('m', $this->_shopping['collection_date']),
                'year'      => (int)date ('Y', $this->_shopping['collection_date'])
            );
        }
        $this->_shopping['_collection_date'] = $collection_date;

        /**
         * Проверка разрешения на автоматическую генерацию приватного обсуждения
         */
        $can_autocreate_pd = false;
        if ($options->estPD_enabled && $options->estcs_allow_pd && empty ($this->_shopping['extended_data']['private_thread_id'])) {
            $can_autocreate_pd = Esthetic_CS_Crossover_PD::isAutoCreateThreadAvailable();
        }

        return $this->responseView(
            'Esthetic_CS_ViewPublic_Shopping_Edit',
            'estcs_shopping_edit_dialog',
            array (
                'shopping'          => $this->_shopping,
                'stage_id'          => Esthetic_CS_Helper_Shopping::getStageId($this->_shopping),
                'thread'            => $this->_thread,
                'forum'             => $this->_forum,
                'node_bread_crumbs' => $this->_node_bread_crumbs,
                'currency_title'    => Esthetic_CS_Helper_Shopping::getCurrencyTitle(),
                'can_manage'        => $visitor->hasPermission('estcs', 'estcs_can_manage'),
                'can_edit_payment'  => $visitor->hasPermission('estcs', 'estcs_can_edit_payment'),
                'can_get_product'   => $visitor->hasPermission('estcs', 'estcs_can_get_product'),
                'can_get_money'     => $visitor->hasPermission('estcs', 'estcs_can_get_money'),
                
                /* v1.0.5 beta 3 */
                'can_autocreate_pd' => $can_autocreate_pd,
                
                /* v1.1.0 beta 1 */
                'server_time'       => XenForo_Application::$time
            )
        );
    }
    
    
    /**
     * Сохранение настроек покупки
     * @return  XenForo_ControllerResponse_Redirect
     */
    public function actionSave ( ) {
        
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $organizer_state;
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $data = $this->_input->filter(array (
            'price'                 => XenForo_Input::FLOAT,
            'stage'                 => XenForo_Input::STRING,
            'participants'          => XenForo_Input::UINT,
            'payment'               => XenForo_Input::FLOAT,
            'organizer_role_id'     => XenForo_Input::UINT,
            
            'setup_single_field'    => XenForo_Input::STRING
        ));
        
        $extended_data = $this->_shopping['extended_data'];
        
        if ($data['setup_single_field'] != false) {
        
            $data = array (
                'price'                 => $this->_shopping['price'],
                'stage'                 => $this->_shopping['stage'],
                'participants'          => $this->_shopping['participants'],
                'payment'               => $this->_shopping['payment'],
                'organizer_role_id'     => $extended_data['organizer_role_id'],
                'setup_single_field'    => $this->_input->filterSingle('setup_single_field', XenForo_Input::STRING)
            );
            
            switch ($data['setup_single_field']) {
                
                case 'stage':
                    $data['stage'] = $this->_input->filterSingle('stage', XenForo_Input::STRING);
                    break;
                
                default:
            }
        }

        $current_stage_id = Esthetic_CS_Helper_Shopping::getStageId($this->_shopping);
        
        $options = XenForo_Application::get('options');
        $visitor = XenForo_Visitor::getInstance();
        
        /**
         * Определение права редактировать базовые установки
         */
        $can_edit_basics = ($visitor->hasPermission('estcs', 'estcs_can_manage') || $current_stage_id < 4);
        
        $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Shopping');
        $dw->setExistingData($this->_shopping['shopping_id']);
        
        if ($can_edit_basics) {
            $dw->set('price', round ($data['price'], 2));
            $stage = $data['stage'];
        } else {
            $stage = $this->_shopping['stage'];
        }
        
        if (!in_array ($stage, array ('open', 'active', 'finished', 'closed', 'banned'))) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_stage_not_match'), 400);
        }
        
        
        $participants = $data['participants'];
        $participants_array = $this->_getParticipantModel()->prepareParticipantsList($this->_getParticipants());
        
        /**
         * Обработка фиксированного взноса
         */
        if ($can_edit_basics) {
            $dw->set('payment', 0);
            $participant_payment = round ($data['payment'], 2);
            if ($participant_payment && ($visitor->hasPermission('estcs', 'estcs_can_edit_payment') || $visitor->hasPermission('estcs', 'estcs_can_manage'))) {
                if ($participant_payment < $options->estcs_minimum_payment) {
                    return $this->_getErrorContent(new XenForo_Phrase (
                        'estcs_error_payment_minimal_limit', 
                        array (
                            'limit'     => $options->estcs_minimum_payment, 
                            'currency'  => $options->estcs_currency_title
                        )), 400);
                }
                $dw->set('payment', $participant_payment);
            } else if ($participant_payment) {
                return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_no_permission_to_edit_payment'), 400);
            }
        }

        /**
         * Проверка параметров перехода к следующему этапу
         */
        $new_stage_id = Esthetic_CS_Helper_Shopping::getStageId($stage);
        if ($new_stage_id > $current_stage_id && $new_stage_id < 5) {
            
            if ($current_stage_id == 1 && $this->_organizerNotApproved()) {
                return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_organizer_not_approved', array ('limit' => $options->estcs_approvement_count)), 400);
            }
            
            if ($new_stage_id > 1 && count ($participants_array['general']) < $options->estcs_minimum_participants) {
                return $this->_getErrorContent(new XenForo_Phrase ('estcs_you_need_x_participants_at_last', array ('x' => $options->estcs_minimum_participants)), 400);
            }

            /**
             * Уравниваем фактическое количество участников
             */
            if ($new_stage_id == 2) {
                $participants = count ($participants_array['general']);
            }
            
            $_participants_set_value = 0;
            if ($participants != $this->_shopping['participants']) {
                $_participants_set_value = $participants == 0 ? false : $participants;
            }
            if ($new_stage_id == 3 && !$this->_isAmountDealed($_participants_set_value) && !$visitor->hasPermission('estcs', 'estcs_can_send_incomplete') && !$visitor->hasPermission('estcs', 'estcs_can_manage')) {
                return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_amount_not_dealed'), 400);
            }
            if ($new_stage_id > 3 && !$this->_isAmountDealed($_participants_set_value) && !$visitor->hasPermission('estcs', 'estcs_can_manage')) {
                return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_unnable_to_close_amount_not_dealed'), 400);
            }
            
            if ($new_stage_id == 3 && !$visitor->hasPermission('estcs', 'estcs_can_manage')) {
                if (!$this->_checkPayingPeriod()) {
                    return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_paying_period', array ('value' => $options->estcs_paying_period)), 400);
                }
            }
            
            if ($new_stage_id > 3 && !$visitor->hasPermission('estcs', 'estcs_can_manage')) {
                if (!$this->_checkOperatingPeriod()) {
                    return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_operating_period', array ('value' => $options->estcs_operating_period)), 400);
                }
                if ($this->_hasPaidNotDelivered()) {
                    return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_has_paid_not_delivered'), 400);
                }
            }
            
            /**
             * Обработка состояния принудительной установки даты сбора
             */
            if ($new_stage_id > 1 && $current_stage_id == 1 && !$visitor->hasPermission('estcs', 'estcs_can_manage') && $options->estcs_collection_date_force) {
                if (empty ($this->_shopping['collection_date'])) {
                    return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_collection_date_not_set_yet'), 400);
                }
                if ($this->_shopping['collection_date'] > XenForo_Application::$time) {
                    return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_collection_date_not_came_yet'), 400);
                }
            }
        }

        if ($can_edit_basics) {
        
            /**
             * Определение роли организатора
             */
            $organizer_role_id = $data['organizer_role_id'];
            if ($organizer_role_id <= 2 && $organizer_role_id != $this->_getShoppingData('organizer_role_id') && $current_stage_id == 1) {
                if ($current_stage_id != $new_stage_id) {
                    return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_role_with_stage_change'), 400);
                }
                
                /**
                 * Проверка прав организатора
                 */
                if ($visitor['user_id'] == $this->_shopping['organizer_id'] && !$visitor->hasPermission('estcs', 'estcs_can_manage')) {
                    if ($organizer_role_id == 1 && !$visitor->hasPermission('estcs', 'estcs_can_get_product')) {
                        return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_role_permission_to_low'), 400);
                    }
                
                    if ($organizer_role_id == 0 && !$visitor->hasPermission('estcs', 'estcs_can_get_money')) {
                        return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_role_permission_to_low'), 400);
                    }
                }
                
                $extended_data['organizer_role_id'] = $organizer_role_id;
            }
            
            /**
             * Установка значений количества участников
             */
            if ($participants > 0 && $participants < $options->estcs_minimum_participants && $new_stage_id > 1 && $new_stage_id < 5) {
                return $this->_getErrorContent(new XenForo_Phrase ('estcs_you_need_x_participants_at_last', array ('x' => $options->estcs_minimum_participants)), 400);
            }
            $dw->set('participants', $participants);
        }
        
        if ($data['setup_single_field'] == false) {
        
            /**
             * Привязка приватного обсуждения
             */
            $private_thread_id = $this->_input->filterSingle('private_thread_id', XenForo_Input::UINT);
            $private_thread_create_auto = $this->_input->filterSingle('create_pd_thread', XenForo_Input::STRING);
            
            if ($options->estPD_enabled && $options->estcs_allow_pd && ($private_thread_id || $private_thread_create_auto)) {
                
                if (!$private_thread_id && $private_thread_create_auto) {
                    $private_thread_id = Esthetic_CS_Crossover_PD::autoCreateThread($this->_shopping, $this->_thread);
                    
                    if (!is_int ($private_thread_id)) {
                        return $this->_getErrorContent($private_thread_id, 400);
                    }
                }
                
                $thread_checkout = Esthetic_CS_Crossover_PD::threadValidAndAvailable($this->_shopping, $private_thread_id);
                if ($thread_checkout !== true) {
                    return $this->_getErrorContent($thread_checkout, 400);
                }
                
                $extended_data['private_thread_id'] = $private_thread_id;
            } else if ($options->estPD_enabled && $options->estcs_allow_pd) {
                $extended_data['private_thread_id'] = 0;
            } else {
                unset ($extended_data['private_thread_id']);
                unset ($extended_data['private_thread_access_type']);
                unset ($extended_data['private_thread_access_extend_control']);
            }
            
            /**
             * Обработка прав доступа к приватному обсуждению
             */
            if (!empty ($extended_data['private_thread_id'])) {
                $extended_data['private_thread_access_type'] = $this->_input->filterSingle('private_thread_type', XenForo_Input::UINT);
                if ($extended_data['private_thread_access_type'] > 2) {
                    $extended_data['private_thread_access_type'] = 0;
                }
                
                $extended_data['private_thread_access_extend_control'] = $this->_input->filterSingle('private_thread_access_extend_control', XenForo_Input::STRING) ? true : false;

                Esthetic_CS_Crossover_PD::doParticipantsCheck(
                    $extended_data['private_thread_id'], 
                    $this->_shopping,
                    $extended_data['private_thread_access_type'],
                    $extended_data['private_thread_access_extend_control']
                );
            }
        }
        
        /**
         * Отправка уведомлений о возвращении к более раннему этапу
         */
        if ($new_stage_id < $current_stage_id && $new_stage_id < 3) {
            Esthetic_CS_Helper_Alert::createDowngradeAlert($this->_shopping, $this->_getParticipants(), $new_stage_id);
        }

        $visitor = XenForo_Visitor::getInstance();
        if ($new_stage_id == 5 && !$visitor->hasPermission('estcs', 'estcs_can_manage')) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_manage_no_permission'), 400);
        }
        
        /**
         * Завершение покупки
         */
        if ($new_stage_id > $current_stage_id && $new_stage_id == 4) {
            
            /**
             * Обработка списков
             */
            if ($options->estcs_bad_vote_not_paid_when_close) {
                $this->_getParticipantModel()->badVoteNotPaidPrimary($this->_shopping['shopping_id']);
            }
            
            if ($options->estcs_remove_anonymous_mark) {
                $this->_getParticipantModel()->clearAnonymousFromNotPaid($this->_shopping['shopping_id']);
            }
            
            /**
             * Обработка неблагожелательных пользователей
             */
            if ($options->estcs_warning_id) {
                $this->_applyPenalty();
            }
            
            /**
             * Отправка отчета
             */
            if ($options->estcs_estimate_content_id > 0 && $options->estcs_estimate_content_type > 0) {
                $this->_buildFinalEstimate();
            }
        }
        
        /**
         * Блокировка покупки
         */
        if ($new_stage_id > $current_stage_id && $new_stage_id == 5 && $options->estcs_disband_banned_shoppings) {
            $dw->set('organizer_id', 0);
            $this->_getParticipantModel()->disbandByShoppingId($this->_shopping['shopping_id']);
        }
        
        $extended_data['stage_' . $new_stage_id . '_started_at'] = XenForo_Application::$time;
        
        $dw->set('extended_data', $extended_data);
        $dw->set('stage', $stage);

        $dw->save();
        
        /**
         * Проверка отметки о оплате и получении продукта у организатора
         */
        $_participants = $this->_getParticipants();
        if (!empty ($_participants) && $new_stage_id > 1 && $new_stage_id < 5) {
            foreach ($_participants as $participant) {
                if ($participant['user_id'] != $this->_shopping['organizer_id']) {
                    continue;
                }
                if (empty ($participant['is_delivered']) && $new_stage_id > 2) {
                    $this->_getParticipantModel()->updateDeliveryStatus(array ($participant['user_id']), $this->_shopping['shopping_id'], true);
                    break;
                }
                if (empty ($participant['is_payed'])) {
                    $this->_getParticipantModel()->updatePaymentStatus(array ($participant['user_id']), $this->_shopping['shopping_id'], true);
                    break;
                }
            }
        }
        
        /**
         * Отправка уведомлений о переходе на следующий этап
         */
        if ($new_stage_id > $current_stage_id && $new_stage_id < 5) {
            Esthetic_CS_Helper_Alert::createUpgradeAlert($this->_shopping, $this->_getParticipants(), $new_stage_id);
        }
        
        /**
         * Добавление оплативших пользователей к переписке
         */
        $this->_addPaidToConversation();
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Смена этапа покупки
     * @return  XenForo_ControllerResponse_Abstract
     */
    public function actionStageChange ( ) {
        
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $organizer_state;
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        return $this->responseView(
            'Esthetic_CS_ViewPublic_Shopping_Dialog_StageChange',
            'estcs_stage_change_dialog',
            array (
                'shopping'          => $this->_shopping,
                'thread'            => $this->_thread,
                'forum'             => $this->_forum,
                'node_bread_crumbs' => $this->_node_bread_crumbs
            )
        );
    }
    
    
    /**
     * Установка даты сбора средств
     * @return  XenForo_ControllerResponse_Abstract
     */
    public function actionCollectionDate ( ) {
    
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $organizer_state;
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        if ($this->_shopping['stage'] != 'open') {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_unnable_to_interact_with_collection_date_now'), 400);
        }

        if (!$this->_input->filterSingle('confirm', XenForo_Input::UINT)) {
        
            $collection_date = array ('day' => 0, 'month' => 0, 'year' => 0);
            if (!empty ($this->_shopping['collection_date'])) {
                $collection_date = array (
                    'day'       => (int)date ('d', $this->_shopping['collection_date']),
                    'month'     => (int)date ('m', $this->_shopping['collection_date']),
                    'year'      => (int)date ('Y', $this->_shopping['collection_date'])
                );
            }
            
            $day_selector = '';
            for ($i = 0; $i <= 31; $i++) {
                $day_selector .= sprintf (
                    '<option value="%d" %s%s>%s</option>',
                    $i,
                    $i > 0 ? '' : ' disabled="disabled"',
                    $i == $collection_date['day'] ? ' selected="selected"' : '',
                    $i > 0 ? $i : '- ' . new XenForo_Phrase ('day') . ' -'
                );
            }
            
            $month_selector = '';
            for ($i = 0; $i <= 12; $i++) {
                $month_selector .= sprintf (
                    '<option value="%d" %s%s>%s</option>',
                    $i,
                    $i > 0 ? '' : ' disabled="disabled"',
                    $i == $collection_date['month'] ? ' selected="selected"' : '',
                    $i > 0 ? new XenForo_Phrase ('month_' . $i) : '- ' . new XenForo_Phrase ('estcs_month') . ' -'
                );
            }
            
            $year_now = (int)date ('Y', time ( ));
            $year_selector = sprintf ('<option value="0" disabled="disabled">- %s -</option>', new XenForo_Phrase ('year'));
            for ($i = $year_now; $i <= $year_now + 2; $i++) {
                $year_selector .= sprintf (
                    '<option value="%d" %s%s>%d</option>',
                    $i,
                    $i > 0 ? '' : ' disabled="disabled"',
                    $i == $collection_date['year'] ? ' selected="selected"' : '',
                    $i
                );
            }
            
            return $this->responseView(
                'Esthetic_CS_ViewPublic_Shopping_Dialog_CollectionDate',
                'estcs_collection_date_dialog',
                array (
                    'shopping'          => $this->_shopping,
                    'thread'            => $this->_thread,
                    'forum'             => $this->_forum,
                    'node_bread_crumbs' => $this->_node_bread_crumbs,
                    
                    'day_selector'      => $day_selector,
                    'month_selector'    => $month_selector,
                    'year_selector'     => $year_selector
                )
            );
        }
        
        $options = XenForo_Application::get('options');
        $visitor = XenForo_Visitor::getInstance();
        
        /**
         * Обработка установки даты сбора
         */
        $date_array = $this->_input->filter(array (
            'collection_date_day'       => XenForo_Input::UINT,
            'collection_date_month'     => XenForo_Input::UINT,
            'collection_date_year'      => XenForo_Input::UINT
        ));
        $collection_date = $this->_getDateFromArray(array ('day' => $date_array['collection_date_day'], 'month' => $date_array['collection_date_month'], 'year' => $date_array['collection_date_year']));
        if (false === $collection_date) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_collection_invalid_date_format'), 400);
        }
            
        /**
         * Проверка соответствия параметров даты сбора
         */
        if (!$visitor->hasPermission('estcs', 'estcs_can_manage') &&
            !empty ($options->estcs_collection_min_period) && 
            $collection_date > 0 &&
            $collection_date < XenForo_Application::$time + intval ($options->estcs_collection_min_period) * 86400) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_collection_date_min_x_period', array ('x' => $options->estcs_collection_min_period)), 400);
        }

        /**
         * Отправка уведомлений об установке даты сбора
         */
        if ($collection_date > XenForo_Application::$time) {
            Esthetic_CS_Helper_Alert::createCollectionDateSetAlert($this->_shopping, $this->_getParticipants(), $this->_thread);
        }
        
        $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Shopping');
        $dw->setExistingData($this->_shopping['shopping_id']);
        $dw->set('collection_date', $collection_date);
        $dw->save();
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Добавление новых пользователей
     * @return  XenForo_ControllerResponse_Abstract
     */
    public function actionAddUsers ( ) {
    
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $organizer_state;
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $visitor = XenForo_Visitor::getInstance();
        
        if ($this->_shopping['stage'] != 'open' && $this->_shopping['stage'] != 'active' && !$visitor->hasPermission('estcs', 'estcs_can_manage')) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_stage_open_required'), 404);
        }
        
        if (!$this->_input->filterSingle('confirm', XenForo_Input::UINT)) {
            return $this->responseView(
                'Esthetic_CS_ViewPublic_Shopping_Dialog_AddUsers',
                'estcs_addusers_dialog',
                array (
                    'shopping'          => $this->_shopping,
                    'thread'            => $this->_thread,
                    'forum'             => $this->_forum,
                    'node_bread_crumbs' => $this->_node_bread_crumbs
                )
            );
        }
        
        $users = explode (',', $this->_input->filterSingle('users', XenForo_Input::STRING));
        if (count ($users) > 40) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_to_much_usernames_specified'), 400);
        }
        if (!$users) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_invalid_users_specified'), 400);
        }
        
        $users_array = $this->_getParticipantModel()->getUsersByNames($users);
        
        if (empty ($users_array['exists'])) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_list_doesnt_include_users_can_be_joined'), 400);
        }
        
        $current = $this->_getParticipants();
        $current_ids = array ( );
        
        $users_array['already_joined'] = array ( );
        if (!empty ($current)) {
            foreach ($current as $participant) {
                $current_ids[] = $participant['user_id'];
            }
        }
        
        foreach ($users_array['exists'] as $key => $user) {
            if (in_array ($user['user_id'], $current_ids)) {
                $users_array['already_joined'][$user['user_id']] = $user;
                unset ($users_array['exists'][$key]);
            }
        }
        
        if (empty ($users_array['exists'])) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_list_doesnt_include_users_can_be_joined'), 400);
        }
        
        foreach ($users_array['exists'] as &$user) {
            
            $user['shopping_ratings'] = array (
                'organizer'     => array (
                    'efficiency'        => intval ($user['organizer_vote_total']) > 0 ? intval (intval ($user['organizer_vote_sum']) / intval ($user['organizer_vote_total']) * 100) : 0,
                    'deals'             => intval ($user['organizer_shoppings_total'])
                ),
                'participant'   => array (
                    'efficiency'        => intval ($user['participant_vote_total']) > 0 ? intval (intval ($user['participant_vote_sum']) / intval ($user['participant_vote_total']) * 100) : 0,
                    'deals'             => intval ($user['participant_shoppings_total'])
                )
            );
            
            unset ($user['organizer_vote_total'], $user['organizer_vote_sum'], $user['organizer_shoppings_total'], 
                    $user['participant_vote_total'], $user['participant_vote_sum'], $user['participant_shoppings_total']);
        }
        
        return $this->responseView(
            'Esthetic_CS_ViewPublic_Shopping_NewParticipants',
            'estcs_new_participants_confirmation',
            array (
                'shopping'          => $this->_shopping,
                'thread'            => $this->_thread,
                'forum'             => $this->_forum,
                'node_bread_crumbs' => $this->_node_bread_crumbs,
                
                'users'             => $users_array
            )
        );
    }
    
    
    /**
     * Добавление пользователей в список участников покупки
     * @return XenForo_ControllerResponse_Abstract
     */
    public function actionAddUsersConfirm ( ) {
        
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $organizer_state;
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $data = $this->_input->filter(array (
            'users'         => XenForo_Input::ARRAY_SIMPLE,
            'list_type'     => XenForo_Input::STRING,
            'alert_users'   => XenForo_Input::STRING
        ));
        
        $user_ids = array_keys ($data['users']);
        if (empty ($user_ids)) {
            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildPublicLink('threads', $this->_thread)
            );
        }
        $users = $this->_getUserModel()->getUsersByIds($user_ids);
        $current = $this->_getParticipants();
        
        foreach ($current as $user) {
            if (isset ($users[$user['user_id']])) {
                unset ($users[$user['user_id']]);
            }
        }
        
        if (!empty ($users)) {
            
            $options = XenForo_Application::get('options');
            $visitor = XenForo_Visitor::getInstance();
            
            $this->_getParticipantModel()->addParticipantsByIds($this->_shopping['shopping_id'], array_keys ($users), array (
                'is_primary'                => $data['list_type'] == 'general',
                'is_anonymous'              => 0,
                'vote'                      => 1,
                'organizer_vote'            => 1,
                'is_accepting_organizer'    => 1
            ));

            Esthetic_CS_Helper_Alert::usersForciblyJoinedAlert($this->_shopping, $users, $this->_thread);
        
            if ($options->estPD_enabled && $options->estcs_allow_pd) {
                Esthetic_CS_Crossover_PD::addUserByRule($this->_shopping, array_keys ($users));
            }
        
            if (!empty ($this->_shopping['extended_data']['payment_conversation_id']) && !empty ($this->_shopping['organizer_id'])) {
                $dw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');
                $dw->setExistingData($this->_shopping['extended_data']['payment_conversation_id']);
                $dw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_ACTION_USER, $this->_getUserModel()->getUserById($this->_shopping['organizer_id']));
                $dw->addRecipientUserIds(array_keys ($users));
                $dw->save();
            }
            
            $current =  $this->_getParticipantModel()->prepareParticipantsList($this->_getParticipants(true), false);
            if (count ($current['general']) > $this->_shopping['participants']) {
                $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Shopping');
                $dw->setExistingData($this->_shopping['shopping_id']);
                $dw->set('participants', count ($current['general']));
                $dw->save();
            }
        }
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Вывод диалогового окна о присоединении переписки
     * @return  XenForo_ControllerResponse_View
     */
    public function actionConversationDialog ( ) {
        
        if ($this->getResponseType() != 'json') {
            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildPublicLink('threads', $this->_thread)
            );
        }
        
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $organizer_state;
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $content_type = $this->_input->filterSingle('content_type', XenForo_Input::STRING);
        
        return $this->responseView(
            'Esthetic_CS_ViewPublic_Shopping_ConversationDialog',
            'estcs_conversation_dialog',
            array (
                'shopping'          => $this->_shopping,
                'thread'            => $this->_thread,
                'forum'             => $this->_forum,
                'node_bread_crumbs' => $this->_node_bread_crumbs,
                
                'content_type'      => $content_type
            )
        );
    }
    
    
    /**
     * Обработка запроса на присоединение переписки
     * @return  XenForo_ControllerResponse_Abstract
     */
    public function actionConversationCreate ( ) {
    
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $organizer_state;
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $content_type = strtolower ($this->_input->filterSingle('content_type', XenForo_Input::STRING));
        
        switch ($content_type) {
            case 'payment':
                $message = XenForo_Helper_String::autoLinkBbCode($this->getHelper('Editor')->getMessageText('payment_conversation', $this->_input));
                $prefix = 'payment';
                if ($this->_shopping['stage'] != 'active') {
                    return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_active_stage_required_for_conversation'), 400);
                }
                break;
                
            case 'delivery':
                $message = XenForo_Helper_String::autoLinkBbCode($this->getHelper('Editor')->getMessageText('delivery_conversation', $this->_input));
                $prefix = 'product';
                if ($this->_shopping['stage'] != 'finished') {
                    return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_finished_stage_required_for_conversation'), 400);
                }
                break;
                
            default:
                $message = false;
                $prefix = false;
        }
        
        if ($prefix === false) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_while_creating_conversation'), 200);
        }
        if (!$this->_shopping['organizer_id']) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_conversation_not_created_no_organizer'), 400);
        }
        if (!count ($this->_getParticipants())) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_no_participants_to_open_conversation'), 400);
        }
        if (!empty ($this->_shopping['extended_data']['organizer_role_id'])) {
            if ($this->_shopping['extended_data']['organizer_role_id'] == 2 && count ($this->_getParticipants()) <= 1) {
                return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_no_participants_to_open_conversation'), 400);
            }
        }
        if (preg_replace ('/[\r\n\s]+/', '', $message) == false) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_conversation_message_required'), 400);
        }
        if (!empty ($this->_shopping['extended_data'][$content_type . '_conversation_id'])) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_' . $content_type . '_conversation_already_set'), 200);
        }
        
        $extended_data = $this->_shopping['extended_data'];
        $conversation = $this->_createConversation($message, $content_type == 'delivery', new XenForo_Phrase ('estcs_' . $content_type));
        $extended_data[$content_type . '_conversation_id'] = $conversation['conversation_id'];
        
        $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Shopping');
        $dw->setExistingData($this->_shopping['shopping_id']);
        $dw->set('extended_data', $extended_data);
        $dw->save();
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread),
            array (
                'success'               => true,
                'content_type'          => $content_type,
                'conversation_link'     => XenForo_Link::buildPublicLink('full:conversations/unread', $conversation),
                'message'               => new XenForo_Phrase ('estcs_conversation_created')
            )
        );
    }
    
    
    /**
     * Отсоединение переписки
     * @return  XenForo_ControllerResponse_Abstract
     */
    public function actionConversationUnlink ( ) {
    
        if ($this->getResponseType() != 'json') {
            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildPublicLink('threads', $this->_thread)
            );
        }

        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $organizer_state;
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $content_type = strtolower ($this->_input->filterSingle('content_type', XenForo_Input::STRING));
        
        if ($content_type != 'payment' && $content_type != 'delivery') {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_unknown_conversation_type'), 400);
        }
        
        if (false == $this->_input->filterSingle('confirm', XenForo_Input::UINT)) {
            return $this->responseView(
                'Esthetic_CS_ViewPublic_Shopping_ConversationDialog',
                'estcs_conversation_unlink_dialog',
                array (
                    'shopping'          => $this->_shopping,
                    'thread'            => $this->_thread,
                    'forum'             => $this->_forum,
                    'node_bread_crumbs' => $this->_node_bread_crumbs,
                    
                    'content_type'      => $content_type
                )
            );
        }
        
        $extended_data = $this->_shopping['extended_data'];
        $extended_data[$content_type . '_conversation_id'] = 0;
        
        $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Shopping');
        $dw->setExistingData($this->_shopping['shopping_id']);
        $dw->set('extended_data', $extended_data);
        $dw->save();
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread),
            array (
                'success'               => true,
                'content_type'          => $content_type,
                'message'               => new XenForo_Phrase ('estcs_conversation_unlinked')
            )
        );
    }
    
    
    /**
     * Вывод текущего отчета
     * @return  XenForo_ControllerResponse_View
     */
    public function actionEstimate ( ) {
    
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $organizer_state;
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $values = $this->_getShoppingModel()->getEstimate($this->_shopping);
        
        return $this->responseView(
            'Esthetic_CS_ViewPublic_Shopping_Estimate',
            'estcs_estimate',
            array (
                'shopping'          => $this->_shopping,
                'thread'            => $this->_thread,
                'forum'             => $this->_forum,
                'node_bread_crumbs' => $this->_node_bread_crumbs,
                'currency_title'    => Esthetic_CS_Helper_Shopping::getCurrencyTitle(),
                'values'            => $values,
                'str'               => $this->_makeFloatingStrings($values)
            )
        );
    }
    
    
    /**
     * Превращение значений массива в строковые значения формата X.XX
     * @param   array   $values
     * @return  array
     */
    protected function _makeFloatingStrings (array $values) {
        if (empty ($values)) {
            return $values;
        }
        foreach ($values as &$value) {
            if (is_array ($value)) {
                $value = $this->_makeFloatingStrings($value);
            } else {
                $value = sprintf ('%0.2f', (float)$value);
            }
        }
        return $values;
    }
    
    
    /**
     * Переадресация пустых запросов
     * @return  XenForo_ControllerResponse_Redirect
     */
    public function actionNoAction ( ) {
        
        if ($this->getResponseType() == 'json') {
            return $this->responseMessage('inbox_indicator_refresh');
        }
        
        if ($this->_input->filterSingle('estcs_product_version_data', XenForo_Input::STRING)) {
            echo sprintf ("<pre>m_id: est-cs-2000\r\nv_id: %d\r\nu_id: %03d</pre>", self::$est_version_id, 025);
            exit;
        }
        
        if (empty ($this->_thread)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Увольнение организатора совместной покупки
     * @return XenForo_ControllerResponse_Redirect
     */
    public function actionKickOrganizer ( ) {
    
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $organizer_state;
        }
        
        $visitor = XenForo_Visitor::getInstance();
        
        if (!$visitor->hasPermission('estcs', 'estcs_can_manage') && $visitor['user_id'] != $this->_shopping['organizer_id']) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_manage_no_permission'), 403);
        } else if (!$visitor->hasPermission('estcs', 'estcs_can_manage')) {
        
            if (Esthetic_CS_Helper_Shopping::getStageId($this->_shopping) != 1) {
                return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_organizer_unnable_to_leave'), 400);
            }
            
            $paticipants = $this->_getParticipants();
            foreach ($paticipants as $paticipant) {
                if ($paticipant['is_payed']) {
                    return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_organizer_unnable_to_leave'), 400);
                }
            }
        }
        
        if ($visitor['user_id'] != $this->_shopping['organizer_id']) {
            Esthetic_CS_Helper_Alert::createOrganizerKickAlert($this->_shopping);
        }
        
        $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Shopping');
        $dw->setExistingData($this->_shopping['shopping_id']);
        $dw->set('organizer_id', 0);
        $dw->save();
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Подтверждение кандидатуры организатора
     * @return XenForo_ControllerResponse_Redirect
     */
    function actionAssignOrganizer ( ) {
    
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $organizer_state;
        }
        
        $visitor = XenForo_Visitor::getInstance();
        
        if (!$visitor->hasPermission('estcs', 'estcs_can_manage')) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_manage_no_permission'), 403);
        }

        $user_id = $this->_input->filterSingle('user_id', XenForo_Input::UINT);
        if ($this->_getOrganizeRequestModel()->getOrganizerRecord($this->_shopping['shopping_id'], $user_id)) {
            $this->_assignOrganizer($user_id);
        }
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread)
        );
    }
    
    
    /**
     * Назначение организатора покупки
     * @param   int     $organizer_id
     * @param   bool    $deny_alert
     * @return  bool
     */
    protected function _assignOrganizer ($organizer_id, $deny_alert = false) {

        $this->_getParticipantModel()->resetApprovements($this->_shopping['shopping_id']);
        
        $extended_data = $this->_shopping['extended_data'];
        $extended_data['deny_reserve'] = false;
        $extended_data['allow_post_buy'] = false;
        
        $extended_data['organizer_role_id'] = 2;
        
        $extended_data['payment_details'] = (string)new XenForo_Phrase ('estcs_payment_details_template');
        $extended_data['product_details'] = (string)new XenForo_Phrase ('estcs_product_details_template');
        
        $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Shopping');
        $dw->setExistingData($this->_shopping['shopping_id']);
        $dw->set('organizer_id', $organizer_id);
        $dw->set('extended_data', $extended_data);
        $dw->save();
        
        /**
         * Отправка уведомлений
         */
        $awaiting_approval = $this->_getOrganizeRequestModel()->getOrganizersByShoppingId($this->_shopping['shopping_id']);
        if (!empty ($awaiting_approval)) {
            Esthetic_CS_Helper_Alert::createCancelOrganizerApprovalAlert($this->_shopping, $awaiting_approval, $organizer_id);
        }
        if (!$deny_alert) {
            Esthetic_CS_Helper_Alert::createOrganizerApprovalAlert($this->_shopping, $organizer_id);
        }
        
        $this->_getOrganizeRequestModel()->cleanUpByShoppingId($this->_shopping['shopping_id']);
        
        return true;
    }
    
    
    /**
     * Проверка параметров организатора группы
     * @return  true|object
     */
    protected function _organizerCheckout ( ) {
        
        $visitor = XenForo_Visitor::getInstance();
        
        if (empty ($this->_shopping)) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_shopping_not_found'), 404);
        }
        
        if ($visitor->hasPermission('estcs', 'estcs_can_manage')) {
            return true;
        }
        
        if (!$visitor->hasPermission('estcs', 'estcs_can_organize')) { 
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_organize_permission_required'), 403);
        }
        
        if ($this->_shopping['organizer_id'] != $visitor['user_id']) {
            return $this->_getErrorContent(new XenForo_Phrase ('estcs_error_user_is_not_organizer'), 403);
        }
        
        return true;
    }
    
    
    /**
     * Получение значений дополнительных параметров
     * @param   string  $key
     * @return  mixed
     */
    protected function _getShoppingData ($key) {
        
        if (empty ($this->_shopping['extended_data'])) {
            return false;
        }
        
        if (empty ($this->_shopping['extended_data'][$key])) {
            return false;
        }
        
        return $this->_shopping['extended_data'][$key];
    }
    
    
    /**
     * Получение проверенного списка выбраных участников покупки(массив идентификаторов)
     * @param   bool        $full
     * @return  array|false
     */
    protected function _getCheckedParticipants ($full = false) {
        
        $checked = $this->_input->filterSingle('estcs_participant', XenForo_Input::ARRAY_SIMPLE);
        
        $loaded = $this->_getParticipants();
        if (empty ($loaded)) {
            return false;
        }
        
        $result = array ( );
        foreach ($loaded as $key => $value) {
            if (!isset ($checked[$value['user_id']])) {
                continue;
            }
            
            if (!$full) {
                $result[$value['user_id']] = $value['user_id'];
            } else {
                $result[$value['user_id']] = $value;
            }
        }
        
        return $result;
    }
    
    
    /**
     * Получение списка участников покупки
     * @param   bool    $force_reload
     * @param   bool    $include_ratings
     * @return  array|false
     */
    protected function _getParticipants ($force_reload = false, $include_ratings = false) {

        if (is_array ($this->_participants) && $force_reload == false) {
            return $this->_participants;
        }
        
        if (empty ($this->_shopping)) {
            throw $this->responseException($this->_getErrorContent(new XenForo_Phrase('estcs_error_shopping_not_found'), 404));
        }
        
        $this->_participants = $this->_getParticipantModel()->getByShoppingId($this->_shopping['shopping_id'], $include_ratings);
        
        if (empty ($this->_participants)) {
            $this->_participants = array ( );
        }
        
        return $this->_participants;
    }
    
    
    /**
     * Проверка наличия достаточного количества оплат
     * @param   int|false       $participants
     * @return bool
     */
    protected function _isAmountDealed ($participants) {
    
        $participants_array = $this->_getParticipantModel()->prepareParticipantsList($this->_getParticipants());
        
        $participants_paid = 0;
        if (!empty ($participants_array['general'])) {
            foreach ($participants_array['general'] as $participant) {
                if ($participant['is_payed']) {
                    $participants_paid++;
                }
            }
        }
        if (!empty ($participants_array['reserve'])) {
            foreach ($participants_array['reserve'] as $participant) {
                if ($participant['is_payed']) {
                    $participants_paid++;
                }
            }
        }
        
        if (!empty ($participants)) {
            $payments_needed = $participants;
        } else if ($this->_shopping['participants'] > 0 && $participants !== false) {
            $payments_needed = $this->_shopping['participants'];
        } else {
            $payments_needed = count ($participants_array['general']);
        }
        
        if ($participants_paid >= $payments_needed) {
            return true;
        }
        
        return false;
    }
    
    
    /**
     * Проверка соотношения одобрительных голосов участников
     * @return bool
     */
    protected function _organizerNotApproved ( ) {
    
        $participants = $this->_getParticipants();
        
        $visitor = XenForo_Visitor::getInstance();
        if ($visitor->hasPermission('estcs', 'estcs_can_manage')) {
            return false;
        }
        
        if ($visitor->hasPermission('estcs', 'estcs_users_confirm')) {
            return false;
        }
        
        if (empty ($participants)) {
            return true;
        }
        
        $approved_count = 0;
        foreach ($participants as $participant) {
            if ($participant['is_accepting_organizer']) {
                $approved_count++;
            }
        }

        if (100 * $approved_count / count ($participants) < XenForo_Application::get('options')->estcs_approvement_count) {
            return true;
        }
        
        return false;
    }
    
    
    /**
     * Проверка основного списка на наличие отметок о доставке
     * @return bool
     */
    protected function _hasPaidNotDelivered ( ) {
    
        if (!XenForo_Application::get('options')->estcs_delivery_confirm_required) {
            return false;
        }
    
        $participants = $this->_getParticipants();
        
        foreach ($participants as $participant) {
            if ($participant['is_payed'] == 1 && $participant['is_delivered'] == 0 && $this->_shopping['organizer_id'] != $participant['user_id']) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Проверка совпадения параметров операционного периода
     * @return  bool
     */
    protected function _checkOperatingPeriod ( ) {
        
        $data = $this->_shopping['extended_data'];
        
        if (empty ($data['stage_3_started_at'])) {
            return true;
        }
        
        $options = XenForo_Application::get('options');
        if (!$options->estcs_operating_period) {
            return true;
        }
        
        if (XenForo_Application::$time < $data['stage_3_started_at'] + 86400 * $options->estcs_operating_period) {
            return false;
        }
        
        return true;
    }
    
    
    /**
     * Проверка совпадения параметров периода оплаты взносов
     * @return  bool
     */
    protected function _checkPayingPeriod ( ) {
        
        $data = $this->_shopping['extended_data'];
        
        if (empty ($data['stage_2_started_at'])) {
            return true;
        }
        
        $options = XenForo_Application::get('options');
        if (!$options->estcs_paying_period) {
            return true;
        }
        
        if (XenForo_Application::$time < $data['stage_2_started_at'] + 86400 * $options->estcs_paying_period) {
            return false;
        }

        return true;
    }
    
    
    /**
     * Генерация финального отчета о покупке
     * @return  bool
     */
    protected function _buildFinalEstimate ( ) {

        $options = XenForo_Application::get('options');
        $visitor = XenForo_Visitor::getInstance();
        
        $estimate_content_id = $options->estcs_estimate_content_id;
        $estimate_content_type = $options->estcs_estimate_content_type;

        if (!$organizer = $this->_getShoppingModel()->getOrganizerByShoppingId($this->_shopping['shopping_id'])) {
            return false;
        }

        $permissions = array ( );
        $used_alternative_estimate = false;
        
        if (!empty ($organizer['global_permission_cache'])) {
            $permissions = unserialize ($organizer['global_permission_cache']);
        }
        if (!empty ($permissions['estcs'])) {
            $permissions = $permissions['estcs'];
        }
        if (!empty ($permissions['estcs_estimate_content_id']) && $permissions['estcs_estimate_content_id'] > 0) {
            $estimate_content_id = intval ($permissions['estcs_estimate_content_id']);
            $used_alternative_estimate = true;
        }
        
        if ($estimate_content_type < 1 || $estimate_content_type > 3) {
            return false;
        }
        
        $estimate_thread = false;
        if ($estimate_content_type == 2 || $estimate_content_type == 3) {
            
            /**
             * Проверка параметров записи в раздел отчета
             */
            $forum = $this->_getForumModel()->getForumById($estimate_content_id);
            if (empty ($forum) && $used_alternative_estimate) {
                $forum = $this->_getForumModel()->getForumById($options->estcs_estimate_content_id);
            }
            
            if (empty ($forum)) {
                return false;
            }
            
            if ($estimate_content_type == 3) {
                $estimate_thread = $this->_getEstimateThreadModel()->getEstimateThread($forum['node_id'], $organizer['user_id']);
            }
        }
        
        if ($estimate_content_type == 1 || $estimate_thread) {
        
            if ($estimate_thread) {
                $estimete_thread_id = $estimate_thread['thread_id'];
            } else {
                $estimete_thread_id = $estimate_content_id;
            }
            
            /** 
             * Проверка параметров записи в тему отчета
             */
            $thread = $this->_getThreadModel()->getThreadById($estimete_thread_id);
            if (empty ($thread) && $used_alternative_estimate && !$estimate_thread) {
                $thread = $this->_getThreadModel()->getThreadById($options->estcs_estimate_content_id);
            }

            if (empty ($thread) && !$estimate_thread) {
                return false;
            }
        }
        
        $estimate_type = $estimate_content_type;
        if ($estimate_content_type == 3 && $estimate_thread && !empty ($thread)) {
            $estimate_type = 1;
        } else {
            $estimate_type = 2;
        }
        
        $participants = $this->_getParticipants();
        $estimate = $this->_getShoppingModel()->getEstimate($this->_shopping, $organizer, $participants);
        
        /**
         * Получение списка не оплативших участников основного списка
         */
        $not_paid_array = array ( );
        if (!empty ($participants)) {
            foreach ($participants as $participant) {
                if (!$participant['is_primary'] || $participant['is_payed']) {
                    continue;
                }
                $not_paid_array[] = sprintf ('[url=%s]%s[/url]', XenForo_Link::buildPublicLink('full:members', $participant), $participant['username']);
            }
        }
        
        $estimate_str = htmlspecialchars_decode (new XenForo_Phrase ('estcs_final_estimate', array (
            'title'                     => $this->_thread['title'],
            'link'                      => XenForo_Link::buildPublicLink('full:threads', $this->_thread),
            'price'                     => $estimate['defined']['price'],
            'currency_title'            => $estimate['other']['currency_title'],
            'participants'              => $estimate['defined']['participants'],
            'participants_paid'         => $estimate['participants']['primary']['paid'],
            'paid_reserve'              => $estimate['participants']['reserve']['paid'],
            'resource_fee'              => $estimate['taxes']['service'],
            'organizer_fee'             => $estimate['taxes']['organizer'],
            'payment_amount'            => $estimate['calculated']['payment'],
            'payment_estimated'         => $estimate['estimated']['payment'],
            'reserve_bonus'             => $estimate['reserve_taxes']['bonus_rate'],
            'reserve_sum'               => $estimate['reserve_taxes']['amount'],
            'reserve_organizer_fee'     => $estimate['reserve_taxes']['organizer'],
            'paid_anonymous'            => $estimate['participants']['anonymous']['paid'],
            'anonymous_fee'             => $estimate['taxes']['anonymity'],
            'organizer_sum'             => $estimate['estimated']['amount']['organizer'],
            'resource_sum'              => $estimate['estimated']['amount']['service'],
            'organizer_link'            => XenForo_Link::buildPublicLink('full:members', $organizer),
            'organizer_username'        => $organizer['username'],
            'organizer_id'              => $organizer['user_id'],
            'total_sum'                 => $estimate['estimated']['amount']['total'],
            'additional_charge'         => $estimate['estimated']['additional_charge']['total'],
            'is_payment_changed'        => $estimate['flags']['is_payment_changed'] ? '[+]' : '[-]',
            'is_payment_changed_by_min' => $estimate['flags']['is_payment_changed_by_min'] ? '[+]' : '[-]',
            'anonymous_not_paid'        => $estimate['flags']['has_anonymous_not_paid'] ? '[+]' : '[-]',
            
            'not_paid_primary_str'      => !empty ($not_paid_array) ? implode (', ', $not_paid_array) : '-/-',
            
            /**
             * Дополнительные рассчетные данные
             */
            'amount_of_fees'            => round (($estimate['participants']['primary']['paid'] + $estimate['participants']['reserve']['paid']) * $estimate['estimated']['payment'], 2),
            'resource_final_sum'        => round ($estimate['estimated']['amount']['total'] - ($estimate['reserve_taxes']['organizer'] + $estimate['taxes']['organizer'] + $estimate['estimated']['price']), 2)
            
        )));

        if ($estimate_type == 1) {
        
            $writer = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
            $writer->set('user_id', $organizer['user_id']);
            $writer->set('username', $organizer['username']);
            $writer->set('message', $estimate_str);
            $writer->set('message_state', 'visible');
            $writer->set('thread_id', $thread['thread_id']);
            $writer->preSave();
            $writer->save();
            
            $thread_id = $thread['thread_id'];
            
            $estimate_link = XenForo_Link::buildPublicLink('full:posts', array ('post_id' => $writer->get('post_id')));
            
        } else {
        
            $title = $this->_thread['title'];
            if ($estimate_content_type == 3) {
                $title = new XenForo_Phrase ('estcs_estimates_of_x', array ('x' => $organizer['username']));
            }
            
            $writer = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
            $writer->bulkSet(array (
                'user_id'           => $organizer['user_id'],
                'username'          => $organizer['username'],
                'title'             => $title,
                'node_id'           => $forum['node_id'],
                'discussion_open'   => true,
                'sticky'            => false,
                'discussion_state'  => $this->getModelFromCache('XenForo_Model_Post')->getPostInsertMessageState(array ( ), $forum),
                
            ));
            
            $post_writer = $writer->getFirstMessageDw();
            $post_writer->set('message', $estimate_str);
            $post_writer->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forum);
            $writer->setExtraData(XenForo_DataWriter_Discussion_Thread::DATA_FORUM, $forum);
            
            $writer->preSave();
            
            $writer->save();
            
            $thread_id = $writer->get('thread_id');
            
            if ($estimate_content_type == 3) {
                
                $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_EstimateThread');
                if ($estimate_thread) {
                    $dw->setExistingData($estimate_thread);
                } else {
                    $dw->set('node_id', $writer->get('node_id'));
                    $dw->set('user_id', $organizer['user_id']);
                }
                $dw->set('thread_id', $thread_id);
                $dw->save();
                
                unset ($dw);
            }
            
            $estimate_link = XenForo_Link::buildPublicLink('full:threads', array ('thread_id' => $thread_id, 'title' => $writer->get('title')));
        }
        
        $extended_data = $this->_shopping['extended_data'];
        $extended_data['last_estimate_thread_id'] = $thread_id;
        
        $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Shopping');
        $dw->setExistingData($this->_shopping['shopping_id']);
        $dw->set('extended_data', $extended_data);
        $dw->save();
        
        if ($this->_shopping['organizer_id'] && $estimate_link) {
            Esthetic_CS_Helper_Alert::createEstimateAlert($this->_shopping, $this->_thread, $estimate_link);
        }
        
        return true;
    }
    
    
    /**
     * Подготовка дополнительного отчета
     * @param   array       $participant_ids
     * @return boolean
     */
    protected function _buildAdditionalEstimate ($participant_ids = array ( )) {
    
        $options = XenForo_Application::get('options');
        $visitor = XenForo_Visitor::getInstance();
        
        $estimate_content_id = $options->estcs_estimate_content_id;
        $estimate_content_type = $options->estcs_estimate_content_type;

        if (!$organizer = $this->_getShoppingModel()->getOrganizerByShoppingId($this->_shopping['shopping_id'])) {
            return false;
        }

        $permissions = array ( );
        $used_alternative_estimate = false;
        
        if (!empty ($organizer['global_permission_cache'])) {
            $permissions = unserialize ($organizer['global_permission_cache']);
        }
        if (!empty ($permissions['estcs'])) {
            $permissions = $permissions['estcs'];
        }
        if (!empty ($permissions['estcs_estimate_content_id']) && $permissions['estcs_estimate_content_id'] > 0) {
            $estimate_content_id = intval ($permissions['estcs_estimate_content_id']);
            $used_alternative_estimate = true;
        }
        
        if ($estimate_content_type < 1 || $estimate_content_type > 3) {
            return false;
        }
        
        $estimate_thread = false;
        if ($estimate_content_type == 2 || $estimate_content_type == 3) {
        
            /**
             * Проверка параметров записи в раздел отчета
             */
            if ($estimate_content_type == 2 && !empty ($this->_shopping['extended_data']['last_estimate_thread_id'])) {
                $thread = $this->_getThreadModel()->getThreadById((int)$this->_shopping['extended_data']['last_estimate_thread_id']);
            }
            
            if (empty ($thread)) {
                $forum = $this->_getForumModel()->getForumById($estimate_content_id);
                if (empty ($forum) && $used_alternative_estimate) {
                    $forum = $this->_getForumModel()->getForumById($options->estcs_estimate_content_id);
                }
                
                if (empty ($forum)) {
                    return false;
                }
                
                if ($estimate_content_type == 3) {
                    $estimate_thread = $this->_getEstimateThreadModel()->getEstimateThread($forum['node_id'], $organizer['user_id']);
                }
            } else {
                $estimate_content_type = 1;
                $forum = $this->_getForumModel()->getForumById($thread['node_id']);
                
                if (empty ($forum)) {
                    return false;
                }
            }
            
        }
        
        if (($estimate_content_type == 1 || $estimate_thread) && empty ($thread)) {
        
            if ($estimate_thread) {
                $estimete_thread_id = $estimate_thread['thread_id'];
            } else {
                $estimete_thread_id = $estimate_content_id;
            }
            
            /** 
             * Проверка параметров записи в тему отчета
             */
            $thread = $this->_getThreadModel()->getThreadById($estimete_thread_id);
            if (empty ($thread) && $used_alternative_estimate && !$estimate_thread) {
                $thread = $this->_getThreadModel()->getThreadById($options->estcs_estimate_content_id);
            }

            if (empty ($thread) && !$estimate_thread) {
                return false;
            }
        }
        
        $estimate_type = $estimate_content_type;
        if ($estimate_content_type == 3 && $estimate_thread && !empty ($thread)) {
            $estimate_type = 1;
        } else {
            $estimate_type = 2;
        }
        
        $participants = $this->_getParticipants();
        $estimate = $this->_getShoppingModel()->getEstimate($this->_shopping, $organizer, $participants);
        
        $anonymous_participants = 0;
        $additional_participants_array = array ( );
        
        foreach ($participants as $key => $participant) {
            
            if (!in_array ($participant['user_id'], $participant_ids) || !$participant['is_additional']) {
                unset ($participants[$key]);
            } else {
                if ($participant['is_anonymous']) {
                    $anonymous_participants++;
                }
                $additional_participants_array[] = sprintf ('[url=%s]%s[/url]', XenForo_Link::buildPublicLink('full:members', $participant), $participant['username']);
            }
        }
        
        if (empty ($participants)) {
            return false;
        }
        
        /**
         * Рассчет сумм платежей
         */
        $amount = array (
            'service'       => 0,
            'organizer'     => round (count ($participants) * $estimate['estimated']['payment'] * (float)$options->estcs_organizer_additional_bonus / 100, 2),
            'total'         => count ($participants) * $estimate['estimated']['payment'] + $anonymous_participants * $estimate['taxes']['anonymity']
        );
        
        $amount['service'] = $amount['total'] - $amount['organizer'];
        
        $estimate_str = htmlspecialchars_decode (new XenForo_Phrase ('estcs_additional_estimate', array (
            'title'                     => $this->_thread['title'],
            'link'                      => XenForo_Link::buildPublicLink('full:threads', $this->_thread),
            'price'                     => $estimate['defined']['price'],
            'currency_title'            => $estimate['other']['currency_title'],
            'participants'              => count ($participants),
            'paid_anonymous'            => $estimate['participants']['anonymous']['paid'],
            'anonymous_fee'             => $estimate['taxes']['anonymity'],
            
            'organizer_sum'             => $amount['organizer'],
            'resource_sum'              => $amount['service'],
            'total_sum'                 => $amount['total'],
            
            'organizer_link'            => XenForo_Link::buildPublicLink('full:members', $organizer),
            'organizer_username'        => $organizer['username'],
            'organizer_id'              => $organizer['user_id'],
            
            'additional_participants'   => !empty ($additional_participants_array) ? implode (', ', $additional_participants_array) : '-/-'
        )));
        
        if ($estimate_type == 1) {
        
            $writer = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
            $writer->set('user_id', $organizer['user_id']);
            $writer->set('username', $organizer['username']);
            $writer->set('message', $estimate_str);
            $writer->set('message_state', 'visible');
            $writer->set('thread_id', $thread['thread_id']);
            $writer->preSave();
            $writer->save();
            
            $thread_id = $thread['thread_id'];
            
            $estimate_link = XenForo_Link::buildPublicLink('full:posts', array ('post_id' => $writer->get('post_id')));
            
        } else {
            
            $title = $this->_thread['title'];
            if ($estimate_content_type == 3) {
                $title = new XenForo_Phrase ('estcs_estimates_of_x', array ('x' => $organizer['username']));
            }
            
            $writer = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
            $writer->bulkSet(array (
                'user_id'           => $organizer['user_id'],
                'username'          => $organizer['username'],
                'title'             => $title,
                'node_id'           => $forum['node_id'],
                'discussion_open'   => true,
                'sticky'            => false,
                'discussion_state'  => $this->getModelFromCache('XenForo_Model_Post')->getPostInsertMessageState(array ( ), $forum),
                
            ));
            
            $post_writer = $writer->getFirstMessageDw();
            $post_writer->set('message', $estimate_str);
            $post_writer->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forum);
            $writer->setExtraData(XenForo_DataWriter_Discussion_Thread::DATA_FORUM, $forum);
            
            $writer->preSave();
            
            $writer->save();
            
            $thread_id = $writer->get('thread_id');
            
            if ($estimate_content_type == 3) {
                
                $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_EstimateThread');
                if ($estimate_thread) {
                    $dw->setExistingData($estimate_thread);
                } else {
                    $dw->set('node_id', $writer->get('node_id'));
                    $dw->set('user_id', $organizer['user_id']);
                }
                $dw->set('thread_id', $thread_id);
                $dw->save();
                
                unset ($dw);
            }
            
            $estimate_link = XenForo_Link::buildPublicLink('full:threads', array ('thread_id' => $thread_id, 'title' => $writer->get('title')));
        }
        
        $extended_data = $this->_shopping['extended_data'];
        $extended_data['last_estimate_thread_id'] = $thread_id;
        
        $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Shopping');
        $dw->setExistingData($this->_shopping['shopping_id']);
        $dw->set('extended_data', $extended_data);
        $dw->save();
        
        if ($this->_shopping['organizer_id'] && $estimate_link) {
            Esthetic_CS_Helper_Alert::createEstimateAlert($this->_shopping, $this->_thread, $estimate_link);
        }
        
        return true;
    }

    
    /**
     * Проверка прав доступа к приватным дискуссиям
     * @return  bool
     */
    protected function _privateDiscussionsCheck ( ) {
        
        $options = XenForo_Application::get('options');
        
        if (!$options->estPD_enabled || !$options->estcs_allow_pd || empty ($this->_shopping['extended_data'])) {
            return false;
        }
        $extended_data = $this->_shopping['extended_data'];
        
        if (empty ($extended_data['private_thread_id'])) {
            return false;
        }
        
        $extend_control = false;
        if (!empty ($extended_data['private_thread_access_extend_control'])) { 
            $extend_control = $extended_data['private_thread_access_extend_control'];
        }
        
        Esthetic_CS_Crossover_PD::doParticipantsCheck(
            $extended_data['private_thread_id'],
            $this->_shopping,
            $extended_data['private_thread_access_type'],
            $extend_control
        );
    }
    
    
    /**
     * Создание переписки со всеми участниками покупки
     * @param   string      $message
     * @param   bool        $paid_only
     * @param   string      $title_prefix
     * @return  int
     */
    protected function _createConversation ($message, $paid_only = false, $title_prefix = '') {
        
        $conversation_data = $this->_getShoppingModel()->getConversationData($this->_shopping['shopping_id']);
        if (!$conversation_data) {
            return false;
        }
        
        $title = ($title_prefix ? (string)$title_prefix . ' ' : '') . $conversation_data['thread_title'];
        
        if (mb_strlen ($title) > 100) {
            $title = mb_substr ($title, 0, 97, 'UTF-8') . '...';
        }
        
        /**
         * TODO: Кеширование отчетов, пересмотреть процесс загрузки данных организатора
         */
        $estimate = $this->_getShoppingModel()->getEstimate($this->_shopping, false, $this->_getParticipants());
        $message = preg_replace ('/\{amount\}/', $estimate['estimated']['payment'], $message);
        
        $message = htmlspecialchars_decode (new XenForo_Phrase ('estcs_conversation_content_bb', array (
            'link'          => XenForo_Link::buildPublicLink ('full:threads', $this->_thread),
            'title'         => $this->_thread['title'],
            'content'       => $message
        )));

        $writer = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');
        $writer->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_ACTION_USER, $conversation_data);
        $writer->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_MESSAGE, $message);
        
        $writer->set('user_id', $conversation_data['user_id']);
        $writer->set('username', $conversation_data['username']);
        $writer->set('title', $title);
        $writer->set('open_invite', 0);
        $writer->set('conversation_open', 1);
        
        $participants = array ( );
        foreach ($this->_getParticipants() as $participant) {
            if ($paid_only && empty ($participant['is_payed'])) {
                continue;
            }
            if ($participant['user_id'] == $this->_shopping['organizer_id']) {
                continue;
            }
            $participants[$participant['user_id']] = $participant['user_id'];
        }
        
        if (empty ($participants)) {
            // TODO : Ошибка о невозможности формирования переписки
            return false;
        }
        
        $writer->addRecipientUserIds($participants);

        $message_dw = $writer->getFirstMessageDw();
        $message_dw->set('message', $message);
        $writer->preSave();
        $writer->save();
        
        return $writer->getMergedData();
    }
    
    
    /**
     * Добавление оплативших пользователей в переписку
     * @return bool
     */
    protected function _addPaidToConversation ($users = array ( )) {
        
        if (empty ($this->_shopping['extended_data']['delivery_conversation_id']) || empty ($this->_shopping['organizer_id'])) {
            return false;
        }
        
        if (empty ($users)) {
            foreach ($this->_getParticipants() as $user) {
                if ($user['is_payed']) {
                    $users[$user['user_id']] = $user['user_id'];
                }
            }
        }

        $dw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');
        $dw->setExistingData($this->_shopping['extended_data']['delivery_conversation_id']);
        $dw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_ACTION_USER, $this->_getUserModel()->getUserById($this->_shopping['organizer_id']));
        $dw->addRecipientUserIds($users);
        $dw->save();
        
        return true;
    }
    
    
    /**
     * Перемещение неблагожелательных пользователей в группу штрафников
     * @return bool
     */
    protected function _applyPenalty ( ) {
    
        $options = XenForo_Application::get('options');
        
        if (empty ($options->estcs_warning_id)) {
            return false;
        }
        
        $users = $this->_getParticipants(true);
        if (empty ($users)) {
            return false;
        }
        
        $warnings = $this->_getWarningModel()->prepareWarningDefinitions($this->_getWarningModel()->getWarningDefinitions());
        $warning = $this->_getWarningModel()->getWarningDefinitionById($options->estcs_warning_id);
        if (empty ($warning) || empty ($warnings)) {
            return false;
        }
        
        $dw = array (
            'warning_definition_id'     => $warning['warning_definition_id'],
            'points'                    => $warning['warning_definition_id'],
        );
        
        $warning = $this->_getWarningModel()->prepareWarningDefinition($warnings[$dw['warning_definition_id']]);
        $dw['title'] = (string)$warning['title'];
        
        $dw['points'] = $warning['points_default'];
        $dw['expiry_date'] = ($warning['expiry_type'] == 'never' ? 0 : min (pow (2, 32) - 1, strtotime ('+' . $warning['expiry_default'] . ' ' . $warning['expiry_type'])));
        
        $dw += array (
            'content_type'          => 'estcs_shopping',
            'content_id'            => $this->_thread['thread_id'],
            'content_title'         => $this->_thread['title'],
            'warning_user_id'       => $this->_shopping['organizer_id'],
            'extra_user_group_ids'  => $warning['extra_user_group_ids']
        );
        
		$warning_handler = $this->_getWarningModel()->getWarningHandler('estcs_shopping');
		$content = $warning_handler->getContent($this->_thread['thread_id']);
        
        /**
         * TODO: По возможности убрать циклические обращения к БД
         */
        foreach ($users as $user) {
            
            if ($user['user_id'] == $this->_shopping['organizer_id']) {
                continue;
            }
            
            if (!$user['vote'] && !$user['is_payed'] && $user['is_primary']) {
                
                $dw['user_id'] = $user['user_id'];
                
                $writer = XenForo_DataWriter::create('XenForo_DataWriter_Warning');
                $writer->bulkSet($dw);
                $writer->setExtraData(XenForo_DataWriter_Warning::DATA_CONTENT, $content);
                $writer->save();
            }
        }

        return true;
    }
    
    
    /**
     * Подготовка контента сообщения об ошибке
     * @param       XenForo_Phrase      $phrase
     * @param       int                 $code
     * @return      XenForo_ControllerResponse_View
     */
    protected function _getErrorContent (XenForo_Phrase $phrase, $code = 200) {
        
        if ($this->getResponseType() == 'json') {
            return $this->responseError($phrase, $code);
        }
        
        $ftp_helper = $this->getHelper('ForumThreadPost');
        
        return $this->responseView(
            'Esthetic_CS_ViewPublic_Shopping_Error',
            'estcs_error_page',
            array (
                'message'               => $phrase,
                'thread'                => $this->_thread,
                'forum'                 => $this->_forum,
                'shopping'              => $this->_shopping,
                'node_bread_crumbs'     => $this->_forum != false ? $ftp_helper->getNodeBreadCrumbs($this->_forum) : false
            )
        );
    }
    
    
    /**
     * Получение значения даты(timestamp)
     * @param   array       $filter
     * @return  int
     */
    protected function _getDateFromArray ($filter = array ( )) {
    
        if ($filter['day'] == 0 && $filter['month'] == 0 && $filter['year'] == 0) {
            return 0;
        }
        
        if ($filter['year'] > 2099 || $filter['month'] > 12 || $filter['day'] > 31) {
            return false;
        }
        if ($filter['year'] < 2010 || $filter['month'] < 1 || $filter['day'] < 1) {
            return false;
        }
        
        try {
            if (!$result = mktime (0, 0, 0, $filter['month'], $filter['day'], $filter['year'])) {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
        
        return $result;
    }
    
    
	/**
     * Получение модели UserGroup
	 * @return XenForo_Model_UserGroup
	 */
	protected function _getUserGroupModel ( ) {
		return $this->getModelFromCache('XenForo_Model_UserGroup');
	}
    
    
	/**
     * Получение модели Shopping
	 * @return Esthetic_CS_Model_Shopping
	 */
	protected function _getShoppingModel ( ) {
		return $this->getModelFromCache('Esthetic_CS_Model_Shopping');
	}
    

    /**
     * Получение модели Participant
     * @return  Esthetic_CS_Model_Participant
     */
    protected function _getParticipantModel ( ) {
        return $this->getModelFromCache('Esthetic_CS_Model_Participant');
    }
    
    
    /**
     * Получение модели OrganizeRequest
     * @return  Esthetic_CS_Model_OrganizeRequest
     */
    protected function _getOrganizeRequestModel ( ) {
        return $this->getModelFromCache('Esthetic_CS_Model_OrganizeRequest');
    }
    
    
    /**
     * Получение модели EstimateThread
     * @return  Esthetic_CS_Model_EstimateThread
     */
    protected function _getEstimateThreadModel ( ) {
        return $this->getModelFromCache('Esthetic_CS_Model_EstimateThread');
    }
    
    
    /**
     * Получение модели Thread
     * @return  XenForo_Model_Thread
     */
    protected function _getThreadModel ( ) {
        return $this->getModelFromCache('XenForo_Model_Thread');
    }
    
    
    /**
     * Получение модели Forum
     * @return  XenForo_Model_Forum
     */
    protected function _getForumModel ( ) {
        return $this->getModelFromCache('XenForo_Model_Forum');
    }
    

    /**
     * Получение модели User
     * @return  XenForo_Model_User
     */
    protected function _getUserModel ( ) {
        return $this->getModelFromCache('XenForo_Model_User');
    }
    
    
    /**
     * Получение модели Warning
     * @return  XenForo_Model_Warning
     */
    protected function _getWarningModel ( ) {
        return $this->getModelFromCache('XenForo_Model_Warning');
    }
}