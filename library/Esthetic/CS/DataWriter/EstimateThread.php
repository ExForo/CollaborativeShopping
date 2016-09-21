<?php
/**
 * Шаблон модели EstimateThread
 * @package     Esthetic_CS
 */
class Esthetic_CS_DataWriter_EstimateThread extends XenForo_DataWriter {

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
     * Получение массива свойств модели
     * @return  array
     */
    protected function _getFields ( ) {
        return array (
            'estcs_estimate_thread'    => array (
                'node_id'                   => array (
                    'type'                      => self::TYPE_UINT,
                    'required'                  => true
                ),
                'user_id'                   => array (
                    'type'                      => self::TYPE_UINT,
                    'required'                  => true
                ),
                'thread_id'                 => array (
                    'type'                      => self::TYPE_UINT,
                    'default'                   => 0
                )
            )
        );
    }
    

    /**
     * Получение информации о существующих данных
     * @param   array   $data
     * @return  array
     */
    protected function _getExistingData ($data) {
    
        if (!is_array($data)) {
            return false;
        } else if (isset ($data['node_id'], $data['user_id'])) {
            $node_id        = $data['node_id'];
            $user_id        = $data['user_id'];
        } else if (isset ($data[0], $data[1])) {
            $node_id        = $data[0];
            $user_id        = $data[1];
        } else {
            return false;
        }
        
        return array ('estcs_estimate_thread' => $this->_getEstimateThreadModel()->getEstimateThread($node_id, $user_id));
    }
    

    /**
     * Получение строки обновления записи БД
     * @param   string  $table_name
     * @return  string
     */
	protected function _getUpdateCondition ($table_name) {
        return sprintf ('node_id = %d AND user_id = %d', 
            (int)$this->_db->quote($this->getExisting('node_id')),
            (int)$this->_db->quote($this->getExisting('user_id'))
        );
	}
    
    
    /**
     * Получение модели EstimateThread
     * @return  Esthetic_CS_Model_EstimateThread
     */
    protected function _getEstimateThreadModel ( ) {
        return $this->getModelFromCache('Esthetic_CS_Model_EstimateThread');
    }
}