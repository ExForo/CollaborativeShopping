<?php
/**
 * Контроллер Thread
 * @package     Esthetic_CS
 */
class Esthetic_CS_ControllerPublic_Thread extends XFCP_Esthetic_CS_ControllerPublic_Thread {

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
     * Дополнение функционала действия Index
     * @return  XenForo_ControllerResponse_View
     */
    public function actionIndex ( ) {

        $response   = parent::actionIndex();
        $params     = &$response->params;
        
        $options    = XenForo_Application::get('options');

        if (!isset ($params['thread'], $params['forum'])) {
            return $response;
        }
        
        /**
         * Если в указанном разделе отсутствует поддержка тем совместных покупок - прекратить поиск и отобразить, как обычную тему
         */
        if (empty ($params['forum']['estcs_type'])) {
            return $response;
        }
        
        if (false === ($shopping = $this->_getShoppingModel()->getByThreadId((int)$params['thread']['thread_id']))) {
            return $response;
        }
        
        if (false === ($p_list = $this->_getParticipantModel()->getByShoppingId($shopping['shopping_id'], true))) {
            return $response;
        }
        
        $participants = $this->_getParticipantModel()->prepareParticipantsList($p_list, !empty ($shopping['extended_data']['sort_members_list']));

        $visitor = XenForo_Visitor::getInstance();
        
        
        /**
         * Поиск пользователя в числе кандидатов в организаторы
         */
        $is_candidate_organizer = false;
        $candidate_organizers   = false;
        
        if (!$shopping['organizer_id']) {
            $candidate_organizers = $this->_getOrganizeRequestModel()->getOrganizersByShoppingId($shopping['shopping_id']);
            
            if (!empty ($candidate_organizers)) {
                foreach ($candidate_organizers as $key => $user) {
                    if ($user['user_id'] == $visitor['user_id']) {
                        $is_candidate_organizer = true;
                    }
                    
                    $user['shopping_rating'] = 0;
                    if (!empty ($user['participant_vote_sum'])) {
                        $user['shopping_rating'] = round ($user['participant_vote_sum'] / $user['participant_vote_total'], 2) * 100;
                    }
                    
                    $user['organizer_rating'] = 0;
                    if (!empty ($user['organizer_vote_sum'])) {
                        $user['organizer_rating'] = round ($user['organizer_vote_sum'] / $user['organizer_vote_total'], 2) * 100;
                    }
                    
                    $candidate_organizers[$key] = $user;
                }
            } else {
                $candidate_organizers = false;
            }
        }
        
        /**
         * Определение флагов
         */
        $can_join = true;
        $can_post_buy = true;
        
        if ((!$visitor->hasPermission('estcs', 'estcs_can_join_primary') && !$visitor->hasPermission('estcs', 'estcs_can_join_reserve')) 
            || Esthetic_CS_Helper_Shopping::getStageId($shopping) > 3) {
            $can_join = false;
        }
        
        /**
         * Если количество участников основного списка достигло предела, и нет возможности записи в резерв - убрать кнопку
         */
        if ($shopping['participants'] > 0 && count ($participants['general']) >= $shopping['participants'] && !$visitor->hasPermission('estcs', 'estcs_can_join_reserve')) {
            $can_join = false;
        }
        
        $participant = false;
        $organizer_vote = 0;
        if (Esthetic_CS_Helper_Shopping::isParticipantOf($p_list)) {
            $can_join = false;
            $can_post_buy = false;
            $participant = Esthetic_CS_Helper_Shopping::loadParticipant($p_list);
            $organizer_vote = (int)$participant['organizer_vote'];
        }
        
        if ($visitor['user_id'] == $shopping['organizer_id']) {
            $can_join = false;
        }

        $can_organize = true;
        
        if (!$visitor->hasPermission('estcs', 'estcs_can_organize')) {
            $can_organize = false;
        }
        
        if ($shopping['organizer_id'] > 0 || $is_candidate_organizer) {
            $can_organize = false;
        }
        
        if (Esthetic_CS_Helper_Shopping::getStageId($shopping) > 3) {
            $can_join = false;
            $can_organize = false;
        } else {
            $can_post_buy = false;
        }
        
        if (!$visitor->hasPermission('estcs', 'estcs_can_join_additional')) {
            $can_post_buy = false;
        }
        
        if (count ($participants['general']) >= $shopping['participants'] && $this->_getShoppingData($shopping, 'deny_reserve')) {
            $can_join = false;
        }

        $require_user_voting = false;
        
        $organizer = $shopping['organizer_id'] ? $this->_getShoppingModel()->getOrganizerByShoppingId($shopping['shopping_id']) : false;
        if (!empty ($organizer)) {
            if ($organizer['_vote_total'] > 0) {
                $organizer['_efficiency'] = round ($organizer['_vote_sum'] / $organizer['_vote_total'], 2) * 100;
            } else {
                $organizer['_efficiency'] = 0;
            }
            
            $organizer['permissions'] = array ( );
            if (!empty ($organizer['global_permission_cache'])) {
                $organizer['permissions'] = unserialize ($organizer['global_permission_cache']);
                
                if (!XenForo_Permission::hasPermission($organizer['permissions'], 'estcs', 'estcs_users_confirm')) {
                    $require_user_voting = true;
                }
            }
        }
        
        /**
         * Рассчет суммы взноса
         */
        $estimate = $this->_getShoppingModel()->getEstimate($shopping, $organizer, $p_list);
        
        $p_price = $estimate['estimated']['payment'];
        
        /**
         * Начисление комиссии анонимности
         */
        if ($participant['is_anonymous']) {
            $p_price += $options->estcs_anonymous_fee;
        }

        $can_manage = false;
        $can_view_extra = false;
        if (!empty ($organizer)) {
            if ($organizer['user_id'] == $visitor['user_id'] && !empty ($visitor['user_id'])) {
                $can_manage = true;
                $can_view_extra = true;
                $is_paid = true;
            }
            
            if (!$params['thread']['discussion_open']) {
                $can_manage = false;
            }
        }
        
        $is_supermanager = false;
        if ($visitor->hasPermission('estcs', 'estcs_can_manage')) {
            $can_manage = true;
            $is_supermanager = true;
        }
        
        /**
         * Определение состояния покупки в процентном отношении
         */
        $stage_complete = Esthetic_CS_Helper_Shopping::getCompletenessLevelPercent($shopping, $p_list);
        
        /**
         * Обработка прав сперехода
         */
        $can_move_to_primary = false;
        $can_move_to_reserve = false;
        if (Esthetic_CS_Helper_Shopping::getStageId($shopping) == 1 && !empty ($participant)) {
            if ($participant['is_primary']) {
                $can_move_to_reserve = true;
            } elseif ($shopping['participants'] == 0 || $shopping['participants'] > count ($participants['general'])) {
                $can_move_to_primary = true;
            }
        }
        if (!$visitor->hasPermission('estcs', 'estcs_can_join_primary')) {
            $can_move_to_primary = false;
        }
        if (!$visitor->hasPermission('estcs', 'estcs_can_join_reserve')) {
            $can_move_to_reserve = false;
        }
        
        /**
         * Обаботка прав анонимности
         */
        $can_manage_anonymity = false;
        if (Esthetic_CS_Helper_Shopping::getStageId($shopping) <= 2 && !empty ($participant)) {
            if (!$participant['is_payed'] && $visitor->hasPermission('estcs', 'estcs_can_join_anonymous')) {
                $can_manage_anonymity = true;
            }
        }
        
        /**
         * Определение права одобрять организатора
         */
        $can_approve_organizer = false;
        $disapproved_count = 0;
        if (Esthetic_CS_Helper_Shopping::getStageId($shopping) == 1 && $shopping['organizer_id'] != $visitor['user_id'] && !empty ($participant)) {
            $can_approve_organizer = true;
        }
        if (empty ($shopping['organizer_id'])) {
            $can_approve_organizer = false;
        }
        
        /**
         * Определение права покидать вакансию организатора
         */
        $can_organizer_leave = true;
        if (Esthetic_CS_Helper_Shopping::getStageId($shopping) > 1 || !$can_manage) {
            $can_organizer_leave = false;
        }
        if (!empty ($p_list)) {
            foreach ($p_list as $user) {
                if ($user['is_payed']) {
                    $can_organizer_leave = false;
                }
                if (!$user['is_accepting_organizer']) {
                    $disapproved_count++;
                }
            }
        }
        
        /**
         * Генерирование сообщения о наличии приватного обсуждения
         */
        $private_discussion_alert = false;
        if ($options->estPD_enabled && $options->estcs_allow_pd) {
            $private_discussion_alert = $this->_getPrivateDiscussionAlertText($shopping, $participant);
        }
        
        $params['shopping_data']  = array (
            'price'                 => sprintf ('%0.2f', $shopping['price']),
            'payment'               => ($p_price - (int)$p_price > 0) ? sprintf ('%0.2f', $p_price) : sprintf ('%d', (int)$p_price),
            'payment_clear'         => sprintf ('%0.2f', $estimate['calculated']['payment']),
            'is_fixed_payment'      => $estimate['flags']['is_payment_changed'],
            'currency_title'        => Esthetic_CS_Helper_Shopping::getCurrencyTitle(),
            'stage_id'              => Esthetic_CS_Helper_Shopping::getStageId($shopping),
            'stage_complete'        => (int)$stage_complete,
            'participants'          => $participants,
            'participants_now'      => count ($participants['general']),
            'disapproved_text'      => $disapproved_count > 0 ? new XenForo_Phrase ('estcs_disapproved_by_x', array ('x' => $disapproved_count)) : '',
            'permissions'           => array (
                'can_join'              => $can_join,
                'can_post_buy'          => $can_post_buy,
                'can_organize'          => $can_organize,
                'can_manage'            => $can_manage,
                'can_view_extra'        => $can_view_extra,
                'is_supermanager'       => $is_supermanager,
                'can_move_to_primary'   => $can_move_to_primary,
                'can_move_to_reserve'   => !$this->_getShoppingData($shopping, 'deny_reserve') ? $can_move_to_reserve : false,
                'can_manage_anonymity'  => $can_manage_anonymity,
                'can_organizer_leave'   => $can_organizer_leave,
                'can_approve_organizer' => $can_approve_organizer,

                'is_candidate_organizer'    => $is_candidate_organizer,             // @version 1.0.4
            ),
            'links'                 => array (                                      // @version 1.1.3
                'conversation_payment'  => !empty ($shopping['extended_data']['payment_conversation_id']) ? 
                    XenForo_Link::buildPublicLink('full:conversations/unread', array ('conversation_id' => $shopping['extended_data']['payment_conversation_id'])) :
                    '/',
                'conversation_delivery' => !empty ($shopping['extended_data']['delivery_conversation_id']) ? 
                    XenForo_Link::buildPublicLink('full:conversations/unread', array ('conversation_id' => $shopping['extended_data']['delivery_conversation_id'])) :
                    '/'
            ),
            'participant'           => $participant,
            'organizer'             => $organizer,
            'organizer_vote'        => $organizer_vote > 0 ? new XenForo_Phrase ('estcs_ctrl_vote_' . $organizer_vote) : '',
            'fee'                   => array (
                'resource'              => sprintf ('%0.2f', $estimate['estimated']['taxes']['service']),
                'organizer'             => sprintf ('%0.2f', $estimate['estimated']['taxes']['organizer']),
                'payment'               => sprintf ('%0.2f', $estimate['taxes']['payment']),
                'anonymity'             => $participant['is_anonymous'] ? sprintf ('%0.2f', $options->estcs_anonymous_fee) : '-'
            ),
            'require_user_voting'   => $require_user_voting,
            'candidate_organizers'  => $candidate_organizers,                       // @version 1.0.4
            
            'private_discussion_alert'  => $private_discussion_alert,               // @version 1.0.5.b3
            'current_time'              => XenForo_Application::$time               // @version 1.1.0 b1
        );
        $params['shopping']     = $shopping;

        return $response;
    }
    
    
    /**
     * Генерирование сообщения о наличии приватного обсуждения
     * @param   array   $shopping
     * @param   array   $participant
     */
    protected function _getPrivateDiscussionAlertText ($shopping, $participant) {
        
        if (empty ($shopping['extended_data'])) {
            return false;
        }
        $extended_data = $shopping['extended_data'];
        
        if (empty ($extended_data['private_thread_id'])) {
            return false;
        }
        
        $visitor = XenForo_Visitor::getInstance();
        
        if ($shopping['organizer_id'] == $visitor['user_id']) {
            return new XenForo_Phrase ('estcs_shopping_has_private_discussion', array (
                'link' => XenForo_Link::buildPublicLink('threads', array ('thread_id' => $extended_data['private_thread_id']))
            ));
        }
        
        if (empty ($participant)) {
            return false;
        }
        
        $access_type = 0;
        if (!empty ($extended_data['private_thread_access_type'])) {
            $access_type = $extended_data['private_thread_access_type'];
        }
        
        switch ($access_type) {
            case 2:
                if ($participant['is_payed'] && $participant['is_delivered']) {
                    return new XenForo_Phrase ('estcs_shopping_has_private_discussion', array (
                        'link' => XenForo_Link::buildPublicLink('threads', array ('thread_id' => $extended_data['private_thread_id']))
                    ));
                } else {
                    return new XenForo_Phrase ('estcs_private_discussion_pay_delivery_required');
                }
                break;
                
            case 1:
                if ($participant['is_payed']) {
                    return new XenForo_Phrase ('estcs_shopping_has_private_discussion', array (
                        'link' => XenForo_Link::buildPublicLink('threads', array ('thread_id' => $extended_data['private_thread_id']))
                    ));
                } else {
                    return new XenForo_Phrase ('estcs_private_discussion_pay_required');
                }
                break;
            
            default:
                return new XenForo_Phrase ('estcs_shopping_has_private_discussion', array (
                    'link' => XenForo_Link::buildPublicLink('threads', array ('thread_id' => $extended_data['private_thread_id']))
                ));
        }
        
        return false;
    }
    
    
    /**
     * Получение значений дополнительных параметров
     * @param   array   $shopping
     * @param   string  $key
     * @return  mixed
     */
    protected function _getShoppingData ($shopping, $key) {
        
        if (empty ($shopping['extended_data'])) {
            return false;
        }
        
        if (empty ($shopping['extended_data'][$key])) {
            return false;
        }
        
        return $shopping['extended_data'][$key];
    }
    
    
    /**
     * Получение модели Shopping
     * @return  Esthetic_CS_Model_Shopping
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
     * Получение модели Participant
     * @return  Esthetic_CS_Model_Participant
     */
    protected function _getParticipantModel ( ) {
        return $this->getModelFromCache('Esthetic_CS_Model_Participant');
    }
}