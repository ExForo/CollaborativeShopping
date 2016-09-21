<?php
/**
 * Модель EstimateThread
 * @package     Esthetic_CS
 */
class Esthetic_CS_Model_EstimateThread extends XenForo_Model {

    /**
     * Номер версии скрипта
     * @var     int
     */
    public static $est_version_id   = 1120;
    
    /**
     * Идентификатор продукта
     * @var     string
     */
    public static $est_addon_id     = 'estcs';




	/**
	 * Получение записи по указанным идентификаторам
	 * @param int   $node_id
     * @param int   $user_id
	 * @return array|false
	 */
    public function getEstimateThread ($node_id, $user_id) {
        return $this->_getDb()->fetchRow(
            'SELECT t.* FROM `estcs_estimate_thread` AS t WHERE t.node_id = ? AND t.user_id = ?', 
            array ($node_id, $user_id));
    }
    
    
	/**
	 * Получение записи по указанному идентификатору треда
	 * @param int   $thread_id
	 * @return array|false
	 */
    public function getEstimateThreadByThreadId ($thread_id) {
        return $this->_getDb()->fetchRow(
            'SELECT t.* FROM `estcs_estimate_thread` AS t WHERE t.thread_id = ?', 
            array ($thread_id));
    }
}