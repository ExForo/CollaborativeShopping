<?php
/**
 * Вспомагательный класс Alert
 * @package     Esthetic_CS
 */
class Esthetic_CS_Helper_Alert {

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
     * Отправка пользовательского уведомления
     * @param   int|array   $user_ids
     * @param   array       $params
     * @return  null
     */
    public static function sendById ($user_ids, array $params) {

        $db = XenForo_Application::getDb();
        
        if (is_array ($user_ids) && count ($user_ids) == 1) {
            $user_ids = intval (implode (' ', $user_ids));
        }
        
        if (is_int ($user_ids)) {
            $user_ids = array ($user_ids);
        }
        
        if (empty ($user_ids)) {
            return;
        }
        
        $db->query(
            'INSERT INTO `xf_user_alert` (`alerted_user_id`, `user_id`, `username`, `content_type`, `content_id`, `action`, `event_date`, `view_date`, `extra_data`) 
                SELECT u.user_id, ?, ?, \'estcs_alert\', 1, \'message\', ?, 0, ? FROM `xf_user` AS u
                    WHERE u.user_id IN ('. implode (',', $user_ids) . ')', 
            array ($params['user_id'], $params['username'], XenForo_Application::$time, serialize($params['extra_data']))
        );
        
        $db->query(
            'UPDATE `xf_user` AS u SET
                u.alerts_unread = u.alerts_unread + 1
                WHERE u.user_id IN ('. implode (',', $user_ids) . ')'
        );
    }
    
    
    /**
     * Отправка уведомлений о переходе на следующий этап совершения покупки
     * @param   array   $shopping
     * @param   array   $participants
     * @param   int     $target_stage
     * @return  bool
     */
    public static function createUpgradeAlert ($shopping, $participants, $target_stage) {
        
        $visitor = XenForo_Visitor::getInstance();
        
        if (empty ($participants)) {
            return false;
        }
        
        $send_to = array ( );
        foreach ($participants as $participant) {
            if ($participant['user_id'] != $shopping['organizer_id']) {
                $send_to[$participant['user_id']] = $participant['user_id'];
            }
        }
        
        Esthetic_CS_Helper_Alert::sendById($send_to, array (
            'user_id'           => $visitor['user_id'],
            'username'          => $visitor['username'],
            'extra_data'        => array (
                'alert_type'        => 'shopping_stage_upgrade',
                'to_stage'          => $target_stage,
                'shopping_id'       => $shopping['shopping_id'],
                'thread_id'         => $shopping['thread_id']
            )
        ));
        
        return true;
    }
    
    
    /**
     * Отправка уведомлений о переходе на более ранний этап совершения покупки
     * @param   array   $shopping
     * @param   array   $participants
     * @param   int     $target_stage
     * @return  bool
     */
    public static function createDowngradeAlert ($shopping, $participants, $target_stage) {
        
        $visitor = XenForo_Visitor::getInstance();
        
        if (empty ($participants)) {
            return false;
        }
        
        $send_to = array ( );
        foreach ($participants as $participant) {
            if ($participant['user_id'] != $shopping['organizer_id']) {
                $send_to[$participant['user_id']] = $participant['user_id'];
            }
        }
        
        Esthetic_CS_Helper_Alert::sendById($send_to, array (
            'user_id'           => $visitor['user_id'],
            'username'          => $visitor['username'],
            'extra_data'        => array (
                'alert_type'        => 'shopping_stage_downgrade',
                'to_stage'          => $target_stage,
                'shopping_id'       => $shopping['shopping_id'],
                'thread_id'         => $shopping['thread_id']
            )
        ));
        
        return true;
    }
    
    
    /**
     * Отправка уведомлений о удалении из списка участников
     * @param   array   $shopping
     * @param   array   $participants
     * @return  bool
     */
    public static function createKickAlert ($shopping, $participants) {
        
        $visitor = XenForo_Visitor::getInstance();
        
        if (empty ($participants)) {
            return false;
        }
        
        $send_to = array ( );
        foreach ($participants as $participant) {
            $send_to[$participant['user_id']] = $participant['user_id'];
        }

        Esthetic_CS_Helper_Alert::sendById($send_to, array (
            'user_id'           => $visitor['user_id'],
            'username'          => $visitor['username'],
            'extra_data'        => array (
                'alert_type'        => 'shopping_participant_kick',
                'shopping_id'       => $shopping['shopping_id'],
                'thread_id'         => $shopping['thread_id']
            )
        ));
        
        return true;
    }
    
    
    /**
     * Отправка уведомлений о лишении права организатора
     * @param   array   $shopping
     * @return  bool
     */
    public static function createOrganizerKickAlert ($shopping) {
        
        $visitor = XenForo_Visitor::getInstance();
        
        Esthetic_CS_Helper_Alert::sendById($shopping['organizer_id'], array (
            'user_id'           => $visitor['user_id'],
            'username'          => $visitor['username'],
            'extra_data'        => array (
                'alert_type'        => 'shopping_organizer_kick',
                'shopping_id'       => $shopping['shopping_id'],
                'thread_id'         => $shopping['thread_id']
            )
        ));
        
        return true;
    }
    
    
    /**
     * Отправка уведомления организатору о одобрении кандидатуры
     * @param   array   $shopping
     * @param   int     $organizer_id
     * @return  bool
     */
    public static function createOrganizerApprovalAlert ($shopping, $organizer_id) {
        
        $visitor = XenForo_Visitor::getInstance();
        
        if (empty ($organizer_id)) {
            return false;
        }
        
        Esthetic_CS_Helper_Alert::sendById($organizer_id, array (
            'user_id'           => $visitor['user_id'],
            'username'          => $visitor['username'],
            'extra_data'        => array (
                'alert_type'        => 'shopping_organizer_approval',
                'shopping_id'       => $shopping['shopping_id'],
                'thread_id'         => $shopping['thread_id']
            )
        ));
        
        return true;
    }
    
    
    /**
     * Отправка уведомлений о отмене заявки на организацию
     * @param   array   $shopping
     * @param   array   $organizers
     * @param   int     $organizer_id
     * @return  bool
     */
    public static function createCancelOrganizerApprovalAlert ($shopping, array $organizers, $organizer_id) {
        
        $visitor = XenForo_Visitor::getInstance();
        
        if (empty ($organizers)) {
            return false;
        }
        
        $send_to = array ( );
        foreach ($organizers as $participant) {
            if ($participant['user_id'] != $organizer_id) {
                $send_to[$participant['user_id']] = $participant['user_id'];
            }
        }
        
        if (empty ($send_to)) {
            return false;
        }

        Esthetic_CS_Helper_Alert::sendById($send_to, array (
            'user_id'           => $visitor['user_id'],
            'username'          => $visitor['username'],
            'extra_data'        => array (
                'alert_type'        => 'shopping_organizer_cancel_approval',
                'shopping_id'       => $shopping['shopping_id'],
                'thread_id'         => $shopping['thread_id']
            )
        ));
        
        return true;
    }
    
    
    /**
     * Отправка уведомлений об установке даты сбора взносов
     * @param   array   $shopping
     * @param   array   $participants
     * @param   array   $thread
     * @return  bool
     */
    public static function createCollectionDateSetAlert ($shopping, $participants, $thread = false) {
        
        $visitor = XenForo_Visitor::getInstance();
        
        if (empty ($participants)) {
            return false;
        }
        
        $send_to = array ( );
        foreach ($participants as $participant) {
            if ($participant['user_id'] != $shopping['organizer_id']) {
                $send_to[$participant['user_id']] = $participant['user_id'];
            }
        }
        
        Esthetic_CS_Helper_Alert::sendById($send_to, array (
            'user_id'           => $visitor['user_id'],
            'username'          => $visitor['username'],
            'extra_data'        => array (
                'alert_type'        => 'shopping_collection_date_set',
                'shopping_id'       => $shopping['shopping_id'],
                'thread_id'         => $shopping['thread_id'],
                'title'             => empty ($thread['title']) ? new XenForo_Phrase ('estcs_unknown_thread') : $thread['title'] // TODO : Отправка названия темы для всех возможных сообщений
            )
        ));
        
        return true;
    }
    
    
    /**
     * Отправка уведомлений о новом участнике покупки
     * @param   array   $shopping
     * @param   array   $participants
     * @param   array   $thread
     * @return  bool
     */
    public static function createNewParticipantAlert ($shopping, $participants, $thread = false) {
        
        $visitor = XenForo_Visitor::getInstance();
        
        $send_to = array ( );
        
        if (!empty ($participants)) {
            foreach ($participants as $participant) {
                if ($participant['user_id'] != $shopping['organizer_id']) {
                    $send_to[$participant['user_id']] = $participant['user_id'];
                }
            }
        }
        
        if ($shopping['organizer_id']) {
            $send_to[(int)$shopping['organizer_id']] = (int)$shopping['organizer_id'];
        }
        
        if (empty ($send_to)) {
            return false;
        }
        
        Esthetic_CS_Helper_Alert::sendById($send_to, array (
            'user_id'           => $visitor['user_id'],
            'username'          => $visitor['username'],
            'extra_data'        => array (
                'alert_type'        => 'shopping_new_participant',
                'shopping_id'       => $shopping['shopping_id'],
                'thread_id'         => $shopping['thread_id'],
                'title'             => empty ($thread['title']) ? new XenForo_Phrase ('estcs_unknown_thread') : $thread['title'],
                'is_finished'       => Esthetic_CS_Helper_Shopping::getStageId($shopping['stage']) > 3
            )
        ));
        
        return true;
    }
    
    
    /**
     * Отправка уведомлений о новом участнике покупки
     * @param   array   $shopping
     * @param   array   $participants
     * @param   array   $thread
     * @return  bool
     */
    public static function createMarkedPaidAlert ($shopping, $participants, $thread = false) {
        
        $visitor = XenForo_Visitor::getInstance();
        
        if (empty ($participants)) {
            return false;
        }
        
        Esthetic_CS_Helper_Alert::sendById($participants, array (
            'user_id'           => $visitor['user_id'],
            'username'          => $visitor['username'],
            'extra_data'        => array (
                'alert_type'        => 'shopping_marked_paid',
                'shopping_id'       => $shopping['shopping_id'],
                'thread_id'         => $shopping['thread_id'],
                'title'             => empty ($thread['title']) ? new XenForo_Phrase ('estcs_unknown_thread') : $thread['title']
            )
        ));
        
        return true;
    }
    
    
    /**
     * Отправка уведомлений о формировании отчета
     * @param   array   $shopping
     * @param   array   $thread
     * @param   string  $link
     * @return  bool
     */
    public static function createEstimateAlert ($shopping, $thread, $link) {
        
        $visitor = XenForo_Visitor::getInstance();
        
        Esthetic_CS_Helper_Alert::sendById($shopping['organizer_id'], array (
            'user_id'           => $visitor['user_id'],
            'username'          => $visitor['username'],
            'extra_data'        => array (
                'alert_type'        => 'shopping_estimate',
                'shopping_id'       => $shopping['shopping_id'],
                'thread_id'         => $shopping['thread_id'],
                'title'             => empty ($thread['title']) ? new XenForo_Phrase ('estcs_unknown_thread') : $thread['title'],
                'link'              => $link
            )
        ));
        
        return true;
    }
    
    
    /**
     * Отправка уведомлений о принудительном присоединении к покупке
     * @param   array   $shopping
     * @param   array   $participants
     * @param   array   $thread
     * @return  bool
     */
    public static function usersForciblyJoinedAlert ($shopping, $participants, $thread = false) {
        
        $visitor = XenForo_Visitor::getInstance();
        
        if (empty ($participants)) {
            return false;
        }
        
        $send_to = array ( );
        foreach ($participants as $participant) {
            if ($participant['user_id'] != $shopping['organizer_id']) {
                $send_to[$participant['user_id']] = $participant['user_id'];
            }
        }
        
        Esthetic_CS_Helper_Alert::sendById($send_to, array (
            'user_id'           => $visitor['user_id'],
            'username'          => $visitor['username'],
            'extra_data'        => array (
                'alert_type'        => 'shopping_forcibly_joined',
                'shopping_id'       => $shopping['shopping_id'],
                'thread_id'         => $shopping['thread_id'],
                'title'             => empty ($thread['title']) ? new XenForo_Phrase ('estcs_unknown_thread') : $thread['title']
            )
        ));
        
        return true;
    }
}