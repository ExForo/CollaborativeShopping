<?php
/**
 * Модель Shopping
 * @package     Esthetic_CS
 */
class Esthetic_CS_Model_Shopping extends XenForo_Model {

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
	 * @param integer $shopping_id
	 * @return array|false
	 */
    public function getShoppingById ($shopping_id) {
        return $this->_prepareShopping($this->_getDb()->fetchRow(
            'SELECT s.*
            FROM `estcs_shopping` AS s
            WHERE s.shopping_id = ?
        ', array($shopping_id)));
    }
    
    
	/**
	 * Получение записи по указанному идентификатору темы
	 * @param integer $thread_id
	 * @return array|false
	 */
    public function getByThreadId ($thread_id) {
        return $this->_prepareShopping($this->_getDb()->fetchRow(
            'SELECT s.*
            FROM `estcs_shopping` AS s
            WHERE s.thread_id = ?
        ', array($thread_id)));
    }
    
    
    /**
     * Обработка расширенных данных
     * @param   array   $shopping
     * @return  array|false
     */
    protected function _prepareShopping ($shopping) {
        if (!is_array ($shopping)) {
            return $shopping;
        }
        
        if (!isset ($shopping['extended_data'])) {
            return $shopping;
        }
        
        if (!is_string ($shopping['extended_data']) && !is_array ($shopping['extended_data']) && !empty ($shopping['extended_data'])) {
            return $shopping;
        }
        
        $shopping['extended_data'] = unserialize ($shopping['extended_data']);
        
        if (empty ($shopping['extended_data'])) {
            $shopping['extended_data'] = array ( );
        }
        
        $required_fields = array ('payment_details', 'product_details');
        
        foreach ($required_fields as $key) {
            if (!isset ($shopping['extended_data'][$key])) {
                $shopping['extended_data'][$key] = false;
            }
        }
        
        return $shopping;
    }
    
    
    /**
     * Удаление совместной покупки, закрепленной за указанной темой
     * @param   int         $thread_id
     * @return  bool
     */
    public function deleteByThreadId ($thread_id) {
        
        $shopping_id = (int)$this->_getDb()->fetchOne(
            'SELECT s.shopping_id FROM `estcs_shopping` AS s WHERE s.thread_id = ? LIMIT 1', array ($thread_id)
        );
        
        if (!$shopping_id) {
            return false;
        }
        
        $writer = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Shopping');
        $writer->setExistingData($shopping_id);
        $writer->delete();
        
        return true;
    }
    
    
    /**
     * Удаление несвязанных с темами форума совместных покупок
     * @return null
     */
    public function removeBrokenRecords ( ) {
        $this->_getDb()->query(
            'DELETE s FROM `estcs_shopping` AS s WHERE s.thread_id = 0 AND s.stage = \'preopen\' AND s.created_at > ?', array (XenForo_Application::$time - 300)
        );
    }
    
    
    /**
     * Получение данных организатора указанной совместной покупки
     * @param   int     $shopping
     * @return  array|false
     */
    public function getOrganizerByShoppingId ($shopping_id) {
        return $this->_getDb()->fetchRow(
            'SELECT u.user_id, u.username, u.custom_title, u.avatar_date,
                (SELECT SUM(p1.organizer_vote) 
                    FROM `estcs_participant` AS p1
                    LEFT JOIN `estcs_shopping` AS s1 ON (p1.shopping_id = s1.shopping_id)
                        WHERE s1.organizer_id = u.user_id AND s1.stage = \'closed\' AND p1.organizer_vote > 0) AS _vote_sum,
                (SELECT COUNT(p2.organizer_vote) 
                    FROM `estcs_participant` AS p2
                    LEFT JOIN `estcs_shopping` AS s2 ON (p2.shopping_id = s2.shopping_id)
                        WHERE s2.organizer_id = u.user_id AND s2.stage = \'closed\') AS _vote_total,
                (SELECT COUNT(s3.shopping_id) 
                    FROM `estcs_shopping` AS s3
                        WHERE s3.organizer_id = u.user_id AND s3.stage = \'closed\') AS _shoppings_total,
                pc.cache_value AS global_permission_cache
                FROM `xf_user` AS u
                    LEFT JOIN `estcs_shopping` AS s ON (u.user_id = s.organizer_id)
                    LEFT JOIN xf_permission_combination AS pc ON (pc.permission_combination_id = u.permission_combination_id)
                    WHERE s.shopping_id = ?',
            array ($shopping_id)
        );
    }
    
    
    /**
     * Получение списка тем, привязанных к покупкам, в которых пользователь является участником
     * @param   int     $user_id
     * @param   array   $fetch_options
     * @return  array|false
     */
    public function getThreadsJoinedByUser ($user_id, $fetch_options) {
    
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
                WHERE participant.user_id = ?
                    AND thread.discussion_state = \'visible\'
                ORDER BY thread.thread_id ASC
            ', $limit_options['limit'], $limit_options['offset']
        ), 'thread_id', $user_id);
    }
    
    
    /**
     * Получение списка тем в которых пользователь является организатором
     * @param   int     $user_id
     * @param   array   $fetch_options
     * @return  array|false
     */
    public function getThreadsOrganizedByUser ($user_id, $fetch_options) {
    
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
                WHERE shopping.organizer_id = ?
                    AND thread.discussion_state = \'visible\'
                ORDER BY thread.thread_id ASC
            ', $limit_options['limit'], $limit_options['offset']
        ), 'thread_id', $user_id);
    }
    
    
    /**
     * Подсчет активных тем, в которых пользователь является участником
     * @param   int     $user_id
     * @return  int
     */
    public function countThreadsJoinedByUser ($user_id) {
        return $this->_getDb()->fetchOne(
            'SELECT COUNT(*) FROM `xf_thread` AS thread
                LEFT JOIN `estcs_shopping` AS shopping ON (thread.thread_id = shopping.thread_id)
                LEFT JOIN `estcs_participant` AS participant ON (participant.shopping_id = shopping.shopping_id)
                WHERE thread.discussion_state = \'visible\'
                    AND participant.user_id = ?', $user_id
        );
    }
    
    
    /**
     * Подсчет активных тем, в которых пользователь является организатором
     * @param   int     $user_id
     * @return  int
     */
    public function countThreadsOrganizedByUser ($user_id) {
        return $this->_getDb()->fetchOne(
            'SELECT COUNT(*) FROM `xf_thread` AS thread
                LEFT JOIN `estcs_shopping` AS shopping ON (thread.thread_id = shopping.thread_id)
                WHERE thread.discussion_state = \'visible\'
                    AND shopping.organizer_id = ?', $user_id
        );
    }
    
    
    /**
     * Получение списка тем, которые могут быть просмотрены, отталкиваясь от текущих прав доступа
     * @param array         $threads List of threads, with forum info and permissions included
     * @param array|null $  viewing_user
     * @return array
     */
    public function getViewableThreadsFromList (array $threads, array $viewing_user = null) {
    
        $this->standardizeViewingUserReference($viewing_user);
        
        $thread_model = $this->_getThreadModel();

        foreach ($threads AS $key => $thread) {
            if (isset ($thread['permissions'])) {
                $permissions = $thread['permissions'];
            } else {
                $permissions = XenForo_Permission::unserializePermissions($thread['node_permission_cache']);
            }

            if (!$thread_model->canViewThreadAndContainer($thread, $thread, $null, $permissions, $viewing_user)) {
                unset($threads[$key]);
            }
        }

        return $threads;
    }
    
    
    /**
     * Получение массива рейтинговых значений указанного пользователя
     * @param   int     $user_id
     * @return  array|false
     */
    public function getUserRatings ($user_id) {
        
        if (!$user_id) {
            return false;
        }
        
        return $this->_getDb()->fetchRow(
            'SELECT 
                (SELECT COUNT(p1.organizer_vote) 
                    FROM `estcs_participant` AS p1
                    LEFT JOIN `estcs_shopping` AS s1 ON (p1.shopping_id = s1.shopping_id)
                        WHERE s1.organizer_id = ? AND s1.stage = \'closed\' AND p1.organizer_vote > 0) AS organizer_vote_sum,
                (SELECT COUNT(p2.organizer_vote) 
                    FROM `estcs_participant` AS p2
                    LEFT JOIN `estcs_shopping` AS s2 ON (p2.shopping_id = s2.shopping_id)
                        WHERE s2.organizer_id = ? AND s2.stage = \'closed\') AS organizer_vote_total,
                (SELECT COUNT(s3.shopping_id) 
                    FROM `estcs_shopping` AS s3
                        WHERE s3.organizer_id = ? AND s3.stage = \'closed\') AS organizer_shoppings_total,
                (SELECT COUNT(p4.vote) 
                    FROM `estcs_participant` AS p4
                    LEFT JOIN `estcs_shopping` AS s4 ON (p4.shopping_id = s4.shopping_id)
                        WHERE p4.user_id = ? AND s4.stage = \'closed\' AND p4.vote > 0 AND p4.is_additional = 0) AS participant_vote_sum,
                (SELECT COUNT(p5.vote) 
                    FROM `estcs_participant` AS p5
                    LEFT JOIN `estcs_shopping` AS s5 ON (p5.shopping_id = s5.shopping_id)
                        WHERE p5.user_id = ? AND s5.stage = \'closed\' AND p5.is_additional = 0) AS participant_vote_total,
                (SELECT COUNT(s6.shopping_id) 
                    FROM `estcs_shopping` AS s6
                        LEFT JOIN `estcs_participant` AS p6 ON (p6.shopping_id = s6.shopping_id)
                        WHERE p6.user_id = ? AND s6.stage = \'closed\' AND p6.is_additional = 0) AS participant_shoppings_total',
            array ($user_id, $user_id, $user_id, $user_id, $user_id, $user_id)
        );
    }
    
    
    /**
     * Определение количества ведомых организатором покупок в данный момент
     * @param   int     $user_id
     * @return  array|false
     */
    public function getCurrentOrganizedShoppings ($user_id) {
        return $this->_getDb()->fetchOne(
            'SELECT COUNT(s.shopping_id)
                FROM `estcs_shopping` AS s
                    WHERE s.stage NOT IN (\'banned\', \'closed\')
                    AND s.organizer_id = ?', array ($user_id)
        );
    }
    
    
    /**
     * Очистка списков по указанному идентификатору пользователя
     * @param   int     $user_id
     * @return  false
     */
    public function cleanUpByUserId ($user_id) {

        $this->_getDb()->query(
            'DELETE p
                FROM `estcs_participant` AS p
                    LEFT JOIN `estcs_shopping` AS s ON (s.shopping_id = p.shopping_id)
                    WHERE (s.stage NOT IN (\'closed\', \'finished\', \'banned\') OR p.is_primary = 0)
                    AND p.user_id = ?', array ($user_id)
        );
        
        $this->_getDb()->query(
            'UPDATE `estcs_shopping` AS s
                SET s.organizer_id = 0 
                WHERE s.organizer_id = ? AND s.stage IN (\'preopen\', \'open\', \'active\', \'finished\')', array ($user_id)
        );

        return false;
    }
    
    
    /**
     * Подготовка дополнительных параметров для формирования переписки с участниками покупки
     * @param   int     $shopping_id
     * @return  array|false
     */
    public function getConversationData ($shopping_id) {
        return $this->_prepareShopping($this->_getDb()->fetchRow(
            'SELECT u.*, t.title AS thread_title
            FROM `estcs_shopping` AS s
                LEFT JOIN `xf_user` AS u ON (u.user_id = s.organizer_id)
                LEFT JOIN `xf_thread` AS t ON (t.thread_id = s.thread_id)
            WHERE s.shopping_id = ?
        ', array($shopping_id)));
    }
    
    
    /**
     * Фомирование данных отчета
     * @param   array           $shopping
     * @param   array|false     $organizer
     * @param   array|false     $participants
     * @return  array|false
     */
    public function getEstimate ($shopping, $organizer = false, $participants = false) {
    
        if (!$organizer) {
            $organizer = $this->getOrganizerByShoppingId($shopping['shopping_id']);
        }
        
        if (empty ($organizer['permissions']) && !empty ($organizer['global_permission_cache'])) {
            $organizer['permissions'] = unserialize ($organizer['global_permission_cache']);
        }
        
        $permissions = array ( );
        if (!empty ($organizer['permissions']['estcs'])) {
            $permissions = $organizer['permissions']['estcs'];
        }
    
        $options = XenForo_Application::get('options');
        if (!empty ($participants)) {
            $participants = $this->_getParticipantModel()->prepareParticipantsList($participants);
        } else {
            $participants = $this->_getParticipantModel()->prepareParticipantsList(
                $this->_getParticipantModel()->getByShoppingId($shopping['shopping_id'])
            );
        }
        
        $result = array (
            'defined'           => array (
                'price'             => (float)$shopping['price'],
                'participants'      => $shopping['participants'],
                'payment'           => $shopping['payment']
            ),
            'calculated'        => array (
                'price'             => (float)$shopping['price']
            ),
            'estimated'         => array (
                'amount'            => array (
                    'total'             => 0,
                    'organizer'         => 0,
                    'service'           => 0
                ),
                'additional_charge' => array (
                    'amount'            => 0,
                    'total'             => 0
                ),
                'price'         => (float)$shopping['price']
            ),
            'taxes'             => array (
                'organizer'         => 0,
                'service'           => 0,
                'payment'           => 0,
                'anonymity'         => 0
            ),
            'reserve_taxes'     => array (
                'amount'            => 0,
                'organizer'         => 0,
                'service'           => 0,
                'bonus_rate'        => 0
            ),
            'participants'      => array (
                'primary'           => array (
                    'total'             => count ($participants['general']),
                    'paid'              => 0
                ),
                'reserve'           => array (
                    'total'             => count ($participants['reserve']),
                    'paid'              => 0
                ),
                'anonymous'         => array (
                    'total'             => 0,
                    'paid'              => 0
                )
            ),
            'other'             => array (
                'thread_id'         => $shopping['thread_id'],
                'currency_title'    => Esthetic_CS_Helper_Shopping::getCurrencyTitle()
            ),
            'flags'             => array (
                'is_payment_changed'            => false,
                'is_payment_changed_by_min'     => false,
                'is_reserve_earnings_allowed'   => false,
                'is_compensated_by_reserve'     => false,
                'has_anonymous'                 => false,
                'has_anonymous_paid'            => false,
                'has_anonymous_not_paid'        => false
            ),
            'has_error'         => false
        );
        
        /**
         * Обработка списка участников
         */
        if (!empty ($participants['general'])) {
            foreach ($participants['general'] as $user) {
                if ($user['is_payed']) {
                    $result['participants']['primary']['paid']++;
                }
                if ($user['is_anonymous']) {
                    $result['flags']['has_anonymous'] = true;
                    $result['participants']['anonymous']['total']++;
                    if ($user['is_payed']) {
                        $result['flags']['has_anonymous_paid'] = true;
                        $result['participants']['anonymous']['paid']++;
                    } else {
                        $result['flags']['has_anonymous_not_paid'] = true;
                    }
                }
            }
        }
        if (!empty ($participants['reserve'])) {
            foreach ($participants['reserve'] as $user) {
                if ($user['is_payed']) {
                    $result['participants']['reserve']['paid']++;
                }
                if ($user['is_anonymous']) {
                    $result['flags']['has_anonymous'] = true;
                    $result['participants']['anonymous']['total']++;
                    if ($user['is_payed']) {
                        $result['flags']['has_anonymous_paid'] = true;
                        $result['participants']['anonymous']['paid']++;
                    } else {
                        $result['flags']['has_anonymous_not_paid'] = true;
                    }
                }
            }
        }
        
        /**
         * Поиск ошибок при составлении списков
         */
        if ($result['defined']['participants'] == 0 || $result['defined']['participants'] != $result['participants']['primary']['total']) {
            $result['has_error'] = true;
        }
        if ($result['defined']['participants'] == 0) {
            $result['defined']['participants'] = $result['participants']['primary']['total'];
        }
        
        /**
         * Рассчет взноса и цены продукта(комиссии не учитываются)
         */
        $_participants = $result['defined']['participants'] > 0 ? $result['defined']['participants'] : $result['participants']['primary']['total'];
        if (empty ($shopping['payment'])) {
            $result['calculated']['payment'] = round ($_participants > 0 ? $result['defined']['price'] / $_participants : $result['defined']['price'], 2);
        } else {
            $result['calculated']['payment'] = round ($shopping['payment'], 2);
            $result['calculated']['price'] = round ($_participants > 0 ? $_participants * $result['calculated']['payment'] : $result['calculated']['payment'], 2);
            $result['flags']['is_payment_changed'] = true;
        }
        
        /**
         * Применение правила минимального взноса
         */
        if ($options->estcs_minimum_payment > $result['calculated']['payment']) {
            $result['estimated']['additional_charge']['amount'] = round ($options->estcs_minimum_payment - $result['calculated']['payment'], 2);
            $result['estimated']['additional_charge']['total'] = 
                $result['estimated']['additional_charge']['amount'] * ($result['participants']['primary']['paid'] + $result['participants']['reserve']['paid']);
            $result['calculated']['payment'] = round ($options->estcs_minimum_payment, 2);
            $result['calculated']['price'] = round ($_participants > 0 ? $_participants * $result['calculated']['payment'] : $result['calculated']['payment'], 2);
            $result['flags']['is_payment_changed_by_min'] = true;

        }
        
        /**
         * Определение наценки за работу организатора
         */
        $_shopping = $shopping;
        $_shopping['price'] = $result['calculated']['price'];
        $_shopping['payment'] = $result['calculated']['payment'];
        $result['taxes']['organizer'] = (float)Esthetic_CS_Helper_Shopping::getOrganizerFee($_shopping, $organizer);
        $result['taxes']['service'] = (float)Esthetic_CS_Helper_Shopping::getResourceFee((float)$_shopping['price']);
        
        /**
         * Составление суммарного значения взноса, учитывая наценки и расходы
         */
        $result['estimated']['payment'] = (float)$result['calculated']['payment'];
        if ($_participants > 0) {
            $result['estimated']['payment'] = $result['estimated']['payment'] + ($result['taxes']['organizer'] + $result['taxes']['service']) / $_participants;
        } else {
            // Если участников покупки нет, и не установлен лимит, рассчет ведется как для одного участника
            $result['estimated']['payment'] = $result['estimated']['payment'] + ($result['taxes']['organizer'] + $result['taxes']['service']) / 1;
        }
        if (!empty ($options->estcs_payment_fee)) {
            $result['taxes']['payment'] = round (($result['estimated']['payment'] * (100 + $options->estcs_payment_fee) / 100) - $result['estimated']['payment'], 2);
            $result['estimated']['payment'] = $result['estimated']['payment'] * (100 + $options->estcs_payment_fee) / 100;
            $result['estimated']['price'] = $result['estimated']['price'] * (100 + $options->estcs_payment_fee) / 100;
        }
        $result['estimated']['payment'] = round ($result['estimated']['payment'], 2);
        $result['estimated']['price'] = round ($result['estimated']['price'], 2);
        
        /**
         * Округление значения взноса
         */
        if ($options->estcs_round_payment) {
            if ($result['estimated']['payment'] - (int)$result['estimated']['payment'] > 0) {
                $result['estimated']['payment'] = 1 + (int)$result['estimated']['payment'];
            }
        }
        
        /**
         * Компенсация взносов основного списка за счет взносов резерва
         */
        $paid_reserve = $result['participants']['reserve']['paid'];
        if ($result['participants']['primary']['total'] > $result['participants']['primary']['paid'] && $paid_reserve > 0) {
            $paid_reserve -= ($result['participants']['primary']['total'] - $result['participants']['primary']['paid']);
            $result['flags']['is_compensated_by_reserve'] = true;
        }
        if ($paid_reserve < 0) {
            $paid_reserve = 0;
        }
        
        /**
         * Вычисление сборов резерва
         */
        $result['reserve_taxes']['amount'] = round ($paid_reserve * $result['estimated']['payment'], 2);
        if (!empty ($permissions['estcs_can_get_reserve']) && $options->estcs_organizer_reserve_bonus > 0) {
            $result['flags']['is_reserve_earnings_allowed'] = true;
            $result['reserve_taxes']['organizer'] = round ($result['reserve_taxes']['amount'] * (float)$options->estcs_organizer_reserve_bonus / 100, 2);
            $result['reserve_taxes']['bonus_rate'] = (float)$options->estcs_organizer_reserve_bonus;
            $result['reserve_taxes']['service'] = $result['reserve_taxes']['amount'] - $result['reserve_taxes']['organizer'];
        }
        
        /**
         * Поиск и учет анонимов
         */
        if (!empty ($result['participants']['anonymous']['paid']) && !empty ($options->estcs_anonymous_fee)) {
            $result['taxes']['anonymity'] = round ($options->estcs_anonymous_fee * $result['participants']['anonymous']['paid'], 2);
        }

        /**
         * Начало составления суммы
         */
        $result['estimated']['amount']['total'] = $_participants > 0 ? round ($result['estimated']['payment'] * $_participants, 2) : 
            round ($result['calculated']['price'] + $result['taxes']['organizer'] + $result['taxes']['service'], 2);
        
        $result['estimated']['amount']['organizer'] = $result['taxes']['organizer'] + $result['reserve_taxes']['organizer'];
        $result['estimated']['amount']['service'] = $result['taxes']['service'] + $result['reserve_taxes']['service'] + $result['taxes']['anonymity'];
        $result['estimated']['amount']['total'] += $result['reserve_taxes']['amount'] + $result['taxes']['anonymity'];
        
        $result['estimated']['amount']['reserve'] = $result['reserve_taxes']['amount'];
        $result['estimated']['amount']['primary'] = $result['estimated']['amount']['total'] - $result['reserve_taxes']['amount'] - $result['taxes']['anonymity'];
        
        /**
         * Рассчеты комиссий от каждого взноса
         * @version 1.1.3
         */
        $result['estimated']['taxes'] = array ('organizer' => false, 'service' => false);
        $result['estimated']['taxes']['organizer'] = round ($result['estimated']['amount']['organizer'] / ($_participants > 0 ? $_participants : 1), 2);
        /**
          * Чистое значение:
          *     $result['estimated']['taxes']['service'] = round ($result['estimated']['amount']['service'] / $_participants, 2);
          * Заменено расчетным для получения полного соответствия цифр в отображаемых формах
          */
        $result['estimated']['taxes']['service'] = $result['estimated']['payment'] - $result['calculated']['payment'] - $result['estimated']['taxes']['organizer'] - $result['taxes']['payment'];

        return $result;
    }
    
    
    /**
     * Получение модели Thread
     * @return XenForo_Model_Thread
     */
    protected function _getThreadModel ( ) {
        return $this->getModelFromCache('XenForo_Model_Thread');
    }
    
    
    /**
     * Получение модели Participant
     * @return  Esthetic_CS_Model_Participant
     */
    protected function _getParticipantModel ( ) {
        return $this->getModelFromCache('Esthetic_CS_Model_Participant');
    }
}