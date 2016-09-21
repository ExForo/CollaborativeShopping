<?php
/**
 * Модель Thread
 * @package     Esthetic_CS
 */
class Esthetic_CS_Model_Thread extends XFCP_Esthetic_CS_Model_Thread {

    /**
     * Номер версии скрипта
     * @var     int
     */
    public static $est_version_id   = 1100;
    
    /**
     * Идентификатор продукта
     * @var     string
     */
    public static $est_addon_id     = 'estcs';

    
    protected $_request = false;
    


    /**
     * Управление параметрами селекции темы
     * @param   array   $fetch_options
     * @return  array
     */
    public function prepareThreadFetchOptions (array $fetch_options) {
        $result = parent::prepareThreadFetchOptions($fetch_options);
        
        if (!XenForo_Application::isRegistered('estcs_last_forum_loaded')) {
            return $result;
        }
        
        $forum = XenForo_Application::get('estcs_last_forum_loaded');
        
        if (isset ($result['selectFields'], $result['joinTables']) && $forum['estcs_type'] > 0) {
            $result['selectFields'] .= ', 
                shopping.shopping_id, ROUND (shopping.price, 2) AS shopping_price, shopping.participants AS shopping_participants, 
                shopping.stage AS shopping_stage, organizer.user_id AS organizer_id, organizer.username AS organizer_username, shopping.collection_date AS shopping_collection_date,
                (SELECT COUNT(participant.user_id) FROM `estcs_participant` AS participant 
                    WHERE participant.shopping_id = shopping.shopping_id AND participant.is_primary = 1) AS shopping_participants_current,
                (SELECT COUNT(participant.user_id) FROM `estcs_participant` AS participant 
                    WHERE participant.shopping_id = shopping.shopping_id AND participant.is_primary = 0) AS reserve_participants_current
                ';
            $result['joinTables'] .= '
                LEFT JOIN `estcs_shopping` AS shopping ON (shopping.thread_id = thread.thread_id)
                LEFT JOIN `xf_user` AS organizer ON (organizer.user_id = shopping.organizer_id)';
        }
        
        return $result;
    }
}