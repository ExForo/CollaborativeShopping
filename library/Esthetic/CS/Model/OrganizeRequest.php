<?php
/**
 * Модель OrganizeRequest
 * @package     Esthetic_CS
 */
class Esthetic_CS_Model_OrganizeRequest extends XenForo_Model {

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
	 * Получение записи по указанному идентификатору
	 * @param int   $shopping_id
     * @param int   $user_id
	 * @return array|false
	 */
    public function getOrganizerRecord ($shopping_id, $user_id) {
        return $this->_getDb()->fetchRow(
            'SELECT p.* FROM `estcs_organize_request` AS p WHERE p.shopping_id = ? AND p.user_id = ?', 
            array($shopping_id, $user_id));
    }
    
    
	/**
	 * Получение количества заявок на организацию
	 * @return int|false
	 */
    public function getTotalRequestsCount ( ) {
        return $this->_getDb()->fetchOne(
            'SELECT COUNT(p.shopping_id) FROM `estcs_organize_request` AS p
                LEFT JOIN `estcs_shopping` AS s ON (p.shopping_id = s.shopping_id)
                LEFT JOIN `xf_thread` AS t ON (t.thread_id = s.thread_id)
                WHERE t.discussion_state = \'visible\'
                GROUP BY p.shopping_id'
        );
    }
    
    
	/**
	 * Получение списка кандидатов в организаторы
	 * @param int   $shopping_id
	 * @return array|false
	 */
    public function getOrganizersByShoppingId ($shopping_id, $full = true) {
        return $this->_getDb()->fetchAll(
            'SELECT u.*' . ($full ? ',
                (SELECT COUNT(p1.organizer_vote) 
                    FROM `estcs_participant` AS p1
                    LEFT JOIN `estcs_shopping` AS s1 ON (p1.shopping_id = s1.shopping_id)
                        WHERE s1.organizer_id = u.user_id AND s1.stage = \'closed\' AND p1.organizer_vote > 0) AS organizer_vote_sum,
                (SELECT COUNT(p2.organizer_vote) 
                    FROM `estcs_participant` AS p2
                    LEFT JOIN `estcs_shopping` AS s2 ON (p2.shopping_id = s2.shopping_id)
                        WHERE s2.organizer_id = u.user_id AND s2.stage = \'closed\') AS organizer_vote_total,
                (SELECT COUNT(s3.shopping_id) 
                    FROM `estcs_shopping` AS s3
                        WHERE s3.organizer_id = u.user_id AND s3.stage = \'closed\') AS organizer_shoppings_total,
                (SELECT COUNT(p4.vote) 
                    FROM `estcs_participant` AS p4
                    LEFT JOIN `estcs_shopping` AS s4 ON (p4.shopping_id = s4.shopping_id)
                        WHERE p4.user_id = u.user_id AND s4.stage = \'closed\' AND p4.vote > 0) AS participant_vote_sum,
                (SELECT COUNT(p5.vote) 
                    FROM `estcs_participant` AS p5
                    LEFT JOIN `estcs_shopping` AS s5 ON (p5.shopping_id = s5.shopping_id)
                        WHERE p5.user_id = u.user_id AND s5.stage = \'closed\') AS participant_vote_total,
                (SELECT COUNT(s6.shopping_id) 
                    FROM `estcs_shopping` AS s6
                        LEFT JOIN `estcs_participant` AS p6 ON (p6.shopping_id = s6.shopping_id)
                        WHERE p6.user_id = u.user_id AND s6.stage = \'closed\') AS participant_shoppings_total' : '') .
                ' FROM `xf_user` AS u
                LEFT JOIN `estcs_organize_request` AS r ON (r.user_id = u.user_id)
                WHERE r.shopping_id = ?', 
            array ($shopping_id));
    }
    
    
	/**
	 * Получение списка кандидатов в организаторы
	 * @param int   $shopping_id
	 * @return false
	 */
    public function cleanUpByShoppingId ($shopping_id) {
        $this->_getDb()->query(
            'DELETE r
                FROM `estcs_organize_request` AS r
                    WHERE r.shopping_id = ?', array ($shopping_id)
        );
        return false;
    }
    
    
    /**
     * Получение списка тем, имеющих организаторов, нуждающихся в подтверждении
     * @param   array   $fetch_options
     * @return  array|false
     */
    public function getThreadsWithOrganizersAvaitingApproval ($fetch_options) {
    
        $fetch_options['includeForumReadDate'] = true;

        $join_options = $this->_getThreadModel()->prepareThreadFetchOptions($fetch_options);
        $limit_options = $this->prepareLimitFetchOptions($fetch_options);
    
        return $this->fetchAllKeyed($this->limitQueryResults('
            SELECT thread.*,
                shopping.shopping_id, ROUND (shopping.price, 2) AS shopping_price, shopping.participants AS shopping_participants, 
                shopping.stage AS shopping_stage, organizer.user_id AS organizer_id, organizer.username AS organizer_username,
                (SELECT COUNT(participant.user_id) FROM `estcs_participant` AS participant 
                    WHERE participant.shopping_id = shopping.shopping_id AND participant.is_primary = 1) AS shopping_participants_current,
                (SELECT COUNT(participant.user_id) FROM `estcs_participant` AS participant 
                    WHERE participant.shopping_id = shopping.shopping_id AND participant.is_primary = 0) AS reserve_participants_current
            ' . $join_options['selectFields'] . '
                FROM xf_thread AS thread
            ' . $join_options['joinTables'] . '
                LEFT JOIN `estcs_shopping` AS shopping ON (shopping.thread_id = thread.thread_id)
                LEFT JOIN `estcs_participant` AS participant ON (participant.shopping_id = shopping.shopping_id)
                LEFT JOIN `xf_user` AS organizer ON (organizer.user_id = shopping.organizer_id)
                WHERE thread.discussion_state = \'visible\' 
                    AND shopping.shopping_id IN (SELECT s.shopping_id FROM `estcs_organize_request` AS s GROUP BY s.shopping_id)
                ORDER BY thread.last_post_date ASC
            ', $limit_options['limit'], $limit_options['offset']
        ), 'thread_id');
    }
    
    
    /**
     * @return XenForo_Model_Thread
     */
    protected function _getThreadModel ( ) {
        return $this->getModelFromCache('XenForo_Model_Thread');
    }
}