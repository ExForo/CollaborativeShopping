<?php
/**
 * Модель Participant
 * @package     Esthetic_CS
 */
class Esthetic_CS_Model_Participant extends XenForo_Model {

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
	 * Получение записи по указанному идентификатору
	 * @param int   $shopping_id
     * @param int   $user_id
	 * @return array|false
	 */
    public function getParticipantRecord ($shopping_id, $user_id) {
        return $this->_getDb()->fetchRow('
            SELECT p.* FROM `estcs_participant` AS p WHERE p.shopping_id = ? AND p.user_id = ?
        ', array($shopping_id, $user_id));
    }
    
    
	/**
	 * Удаление записей по указанному идентификатору покупки
	 * @param int   $shopping_id
	 * @return null
	 */
    public function deleteByShoppingId ($shopping_id) {
        $this->_getDb()->query('DELETE p FROM `estcs_participant` AS p WHERE p.shopping_id = ?', array ($shopping_id));
    }
    
    
	/**
	 * Получение участников совместной покупки по указанному идентификатору покупки
	 * @param   int     $shopping_id
     * @param   bool    $full               Загружать полную информацию о рейтингах и покупках
     * @param   array   $extra_fields       Дополнительные поля таблиц
	 * @return  array|false
	 */
    public function getByShoppingId ($shopping_id, $full = false, array $extra_fields = array ( )) {
        return $this->_getDb()->fetchAll(
            'SELECT p.*, u.username, u.avatar_date' . (!empty ($extra_fields) ? ', ' . implode (', ', $extra_fields) : '') . ($full ? ',
                (SELECT COUNT(p1.vote) 
                    FROM `estcs_participant` AS p1
                    LEFT JOIN `estcs_shopping` AS s1 ON (s1.shopping_id = p1.shopping_id)
                        WHERE s1.stage = \'closed\' AND p1.is_additional = 0 AND p1.user_id = u.user_id AND p1.vote > 0) AS _vote_sum,
                (SELECT COUNT(p2.vote) 
                    FROM `estcs_participant` AS p2
                    LEFT JOIN `estcs_shopping` AS s2 ON (s2.shopping_id = p2.shopping_id)
                        WHERE s2.stage = \'closed\' AND p2.is_additional = 0 AND p2.user_id = u.user_id) AS _vote_total,
                (SELECT COUNT(s3.shopping_id) 
                    FROM `estcs_shopping` AS s3
                        LEFT JOIN `estcs_participant` AS p3 ON (p3.shopping_id = s3.shopping_id)
                        WHERE p3.user_id = u.user_id AND s3.stage = \'closed\' AND p3.is_additional = 0) AS _shoppings_total,
                (SELECT COUNT(s4.shopping_id) 
                    FROM `estcs_shopping` AS s4
                        LEFT JOIN `estcs_participant` AS p4 ON (p4.shopping_id = s4.shopping_id)
                        WHERE p4.user_id = u.user_id AND s4.stage = \'closed\' AND p4.is_additional = 1 AND p4.is_payed = 1) AS _additional_shoppings_total' : '') . '
                FROM `estcs_participant` as p
                LEFT JOIN `xf_user` AS u ON (u.user_id = p.user_id)
                WHERE p.shopping_id = ?
                    AND u.username IS NOT NULL
                ORDER BY p.signed_at ASC',
            array ($shopping_id)
        );
    }
    
    
    /**
     * Подготовка списка участников
     * @param   array   $participants
     * @param   boolean $sort_list
     * @return  array
     */
    public function prepareParticipantsList ($participants, $sort_list = true) {

        $result = array (
            'general'       => array ( ),
            'reserve'       => array ( ),
            'additional'    => array ( )
        );
        
        if (empty ($participants) || !is_array ($participants)) {
            return $result;
        }
        
        foreach ($participants as $user) {
            
            $user['rating'] = 0;
            if (!empty ($user['_vote_total'])) {
                $user['rating'] = round ($user['_vote_sum'] / $user['_vote_total'], 2) * 100;
            }
            
            $user['shoppings_total'] = 0;
            if (!empty ($user['_shoppings_total'])) {
                $user['shoppings_total'] = $user['_shoppings_total'];
            }
            
            if ($user['is_primary']) {
                $result['general'][$user['user_id']] = $user;
            } else if ($user['is_additional']) {
                $result['additional'][$user['user_id']] = $user;
            } else {
                $result['reserve'][$user['user_id']] = $user;
            }
        }

        if ($sort_list) {
            $this->_sortPaticipants($result['general']);
            $this->_sortPaticipants($result['reserve']);
            $this->_sortPaticipants($result['additional']);
        }
        
        return $result;
    }
    
    
    /**
     * Сортировка пользователей в списке
     * @return  bool
     */
    protected function _sortPaticipants (&$participants) {
        
        if (count ($participants) < 2) {
            return true;
        }
        
        $payed = array ( );
        $not_payed = array ( );
        
        foreach ($participants as $participant) {
            if ($participant['is_payed']) {
                $payed[] = $participant;
            } else {
                $not_payed[] = $participant;
            }
        }
        
        $participants = array_merge ($payed, $not_payed);
        
        return true;
    }
    
    
    /**
     * Удаление пользователя из совместной покупки
     * @return  true
     * @comment deprecated
     */
    public function removeParticipantFromShopping ($user_id, $shopping_id) {
        
        $this->_getDb()->query(
            'DELETE p FROM `estcs_participant` AS p WHERE p.user_id = ? AND p.shopping_id = ?',
            array ($user_id, $shopping_id)
        );
        
        return true;
    }
    
    
    /**
     * Перемещение указанных пользователей в основную группу
     * @param   array   $participants
     * @param   int     $shopping_id
     * @return  true
     */
    public function moveToPrimary ($participants, $shopping_id) {
        
        $this->_getDb()->query(
            'UPDATE `estcs_participant` AS p SET p.is_primary = 1 WHERE p.shopping_id = ? AND p.user_id IN (' . implode (',', $participants) . ')', array ($shopping_id)
        );
        
        return true;
    }
    
    
    /**
     * Перемещение указанных пользователей в резерв
     * @param   array   $participants
     * @param   int     $shopping_id
     * @return  true
     */
    public function moveToReserve ($participants, $shopping_id) {
        
        $this->_getDb()->query(
            'UPDATE `estcs_participant` AS p SET p.is_primary = 0 WHERE p.shopping_id = ? AND p.user_id IN (' . implode (',', $participants) . ')', array ($shopping_id)
        );
        
        return true;
    }
    
    
    /**
     * Установка флага статуса оплаты
     * @param   array   $participants
     * @param   int     $shopping_id
     * @param   bool    $is_payed
     * @return  true
     */
    public function updatePaymentStatus ($participants, $shopping_id, $is_payed) {
    
        $this->_getDb()->query(
            'UPDATE `estcs_participant` AS p SET p.is_payed = ? WHERE p.shopping_id = ? AND p.user_id IN (' . implode (',', $participants) . ')', 
            array ((int)$is_payed, $shopping_id)
        );
        
        return true;
    }
    
    
    /**
     * Удалениеуказанных пользователей из списка покупки
     * @param   array   $participants
     * @param   int     $shopping_id
     * @return  true
     */
    public function removeParticipants ($participants, $shopping_id) {
    
        $this->_getDb()->query(
            'DELETE p FROM `estcs_participant` AS p WHERE p.user_id IN (' . implode (',', $participants) . ') AND p.shopping_id = ?',
            array ($shopping_id)
        );
        
        return true;
    }
    
    
    /**
     * Установка оценки участника
     * @param   array   $participants
     * @param   int     $shopping_id
     * @param   int     $vote
     * @return  true
     */
    public function voteParticipants ($participants, $shopping_id, $vote) {
    
        $this->_getDb()->query(
            'UPDATE `estcs_participant` AS p SET p.vote = ? WHERE p.shopping_id = ? AND p.user_id IN (' . implode (',', $participants) . ')', 
            array (intval (!empty ($vote)), $shopping_id)
        );
        
        return true;
    }
    
    
    /**
     * Отметка не оплативших пользователей, как нежелательных
     * @param   int     $shopping_id
     * @return  true
     */
    public function badVoteNotPaidPrimary ($shopping_id) {
    
        $this->_getDb()->query(
            'UPDATE `estcs_participant` AS p SET p.vote = 0 WHERE p.shopping_id = ? AND p.is_payed = 0 AND p.is_primary = 1', 
            array ($shopping_id)
        );
        
        return true;
    }
    
    
    /**
     * Очистка анонимности у неоплативших пользователей
     * @param   int     $shopping_id
     * @return  true
     */
    public function clearAnonymousFromNotPaid ($shopping_id) {
    
        $this->_getDb()->query(
            'UPDATE `estcs_participant` AS p SET p.is_anonymous = 0 WHERE p.shopping_id = ? AND p.is_payed = 0', 
            array ($shopping_id)
        );
        
        return true;
    }
    
    
    /**
     * Роспуск участников
     * @param   int     $shopping_id
     * @return  true
     */
    public function disbandByShoppingId ($shopping_id) {
    
        $this->_getDb()->query(
            'DELETE p FROM `estcs_participant` AS p WHERE p.shopping_id = ?',
            array ($shopping_id)
        );
        
        return true;
    }
    
    
    /**
     * Установка флага статуса доставки
     * @param   array   $participants
     * @param   int     $shopping_id
     * @param   bool    $is_delivered
     * @return  true
     */
    public function updateDeliveryStatus ($participants, $shopping_id, $is_delivered) {
    
        $this->_getDb()->query(
            'UPDATE `estcs_participant` AS p SET p.is_delivered = ? WHERE p.shopping_id = ? AND p.user_id IN (' . implode (',', $participants) . ')', 
            array ((int)$is_delivered, $shopping_id)
        );
        
        return true;
    }
    
    
    /**
     * Получение количества пользователей, не одобряющих кандидатуру организатора
     * @param   int     $shopping_id
     * @return  int
     */
    public function getDisapprovedCount ($shopping_id) {
        return $this->_getDb()->fetchOne(
            'SELECT COUNT(p.shopping_id)
                FROM `estcs_participant` AS p
                    WHERE p.shopping_id = ?
                        AND p.is_accepting_organizer = 0', array ($shopping_id)
        );
    }
    
    
    /**
     * Сброс состояний одобрения кандидатуры организатора
     * @param   int     $shopping_id
     * @return  true
     */
    public function resetApprovements ($shopping_id) {
    
        $this->_getDb()->query(
            'UPDATE `estcs_participant` AS p SET p.is_accepting_organizer = 0 WHERE p.shopping_id = ?', 
            array ($shopping_id)
        );
        
        return true;
    }
    
    
    /**
     * Получение списка штрафников
     * @param       int     $shopping_id
     * @return      array|false
     */
    public function getPenaltyUsers ($shopping_id) {
        return $this->_getDb()->fetchAll(
            'SELECT u.*
                FROM `estcs_participant` AS p 
                LEFT JOIN `xf_user` AS u ON (p.user_id = u.user_id)
                WHERE p.shopping_id = ? AND p.vote = 0 AND p.is_payed = 0', 
            array ($shopping_id)
        );
    }
    
    
    /**
     * Загрузка массива пользователей по указанным именам
     * @param   array   $usernames
     * @return  array
     */
    public function getUsersByNames ($usernames) {
        
        if (empty ($usernames)) {
            return false;
        }
        
        $result = array (
            'exists'    => array ( ),
            'not_found' => array ( )
        );
    
        $result['exists'] = $this->_getUserModel()->getUsersByNames(
            $usernames,
            array (
                'join' => XenForo_Model_User::FETCH_USER_PRIVACY | 
                        XenForo_Model_User::FETCH_USER_OPTION | 
                        XenForo_Model_User::FETCH_USER_PERMISSIONS | 
                        Esthetic_CS_Model_User::FETCH_SHOPPING_RATINGS
            ),
            $result['not_found']
        );
    
        return $result;
    }
    
    
    /**
     * Пакетное добавление пользователей
     * @param   int     $shopping_id
     * @param   array   $user_ids
     * @param   array   $scheme
     * @return  array
     */
    public function addParticipantsByIds ($shopping_id, $user_ids, $scheme = array ( )) {

        if (empty ($user_ids)) {
            return false;
        }
        
        $query = 'INSERT INTO `estcs_participant`(`shopping_id`, `user_id`, `is_primary`, `is_additional`, `is_payed`, `is_anonymous`, `is_delivered`, `is_accepting_organizer`, `vote`, `organizer_vote`, `signed_at`) VALUES';
        
        foreach ($user_ids as $user_id) {
            $query .= sprintf (
                "\r\n(%d, %d, %d, %d, %d, %d, %d, %d, %d, %d, %d),",
                $shopping_id, $user_id,
                isset ($scheme['is_primary']) ? (int)$scheme['is_primary'] : 0,
                isset ($scheme['is_additional']) ? (int)$scheme['is_additional'] : 0,
                isset ($scheme['is_payed']) ? (int)$scheme['is_payed'] : 0,
                isset ($scheme['is_anonymous']) ? (int)$scheme['is_anonymous'] : 0,
                isset ($scheme['is_delivered']) ? (int)$scheme['is_delivered'] : 0,
                isset ($scheme['is_accepting_organizer']) ? (int)$scheme['is_accepting_organizer'] : 0,
                isset ($scheme['vote']) ? (int)$scheme['vote'] : 0,
                isset ($scheme['organizer_vote']) ? (int)$scheme['organizer_vote'] : 0,
                isset ($scheme['signed_at']) ? (int)$scheme['signed_at'] : XenForo_Application::$time
            );
        }
        
        $query = preg_replace ('/\,$/', '', $query);
        
        return $this->_getDb()->query($query);
    }
    
    
    /**
     * @return XenForo_Model_Users
     */
    protected function _getUserModel ( ) {
        return $this->getModelFromCache('XenForo_Model_User');
    }
}