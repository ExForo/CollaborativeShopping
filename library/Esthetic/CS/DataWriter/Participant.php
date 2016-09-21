<?php
/**
 * Шаблон модели Participant
 * @package     Esthetic_CS
 */
class Esthetic_CS_DataWriter_Participant extends XenForo_DataWriter {

    /**
     * Номер версии скрипта
     * @var     int
     */
    public static $est_version_id   = 103;
    
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
            'estcs_participant'         => array (
                'shopping_id'               => array (
                    'type'                      => self::TYPE_UINT,
                    'required'                  => true
                ),
                'user_id'                   => array (
                    'type'                      => self::TYPE_UINT,
                    'required'                  => true
                ),
                'is_primary'                => array (
                    'type'                      => self::TYPE_UINT,
                    'default'                   => 0
                ),
                'is_additional'             => array (
                    'type'                      => self::TYPE_UINT,
                    'default'                   => 0
                ),
                'is_payed'                  => array (
                    'type'                      => self::TYPE_UINT,
                    'default'                   => 0
                ),
                'is_anonymous'              => array (
                    'type'                      => self::TYPE_UINT,
                    'default'                   => 0
                ),
                'is_delivered'              => array (
                    'type'                      => self::TYPE_UINT,
                    'default'                   => 0
                ),
                'is_accepting_organizer'    => array (
                    'type'                      => self::TYPE_UINT,
                    'default'                   => 1
                ),
                'vote'                      => array (
                    'type'                      => self::TYPE_UINT,
                    'default'                   => 1
                ),
                'organizer_vote'            => array (
                    'type'                      => self::TYPE_UINT,
                    'default'                   => 1
                ),
                'signed_at'                 => array (
                    'type'                      => self::TYPE_UINT,
                    'default'                   => XenForo_Application::$time + 5
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
        } else if (isset ($data['shopping_id'], $data['user_id'])) {
            $shopping_id    = $data['shopping_id'];
            $user_id        = $data['user_id'];
        } else if (isset ($data[0], $data[1])) {
            $shopping_id    = $data[0];
            $user_id        = $data[1];
        } else {
            return false;
        }
        
        return array ('estcs_participant' => $this->_getParticipantModel()->getParticipantRecord($shopping_id, $user_id));
    }
    

    /**
     * Получение строки обновления записи БД
     * @param   string  $table_name
     * @return  string
     */
	protected function _getUpdateCondition ($table_name) {
        return 'shopping_id = ' . $this->_db->quote($this->getExisting('shopping_id')) . ' AND user_id = ' . $this->_db->quote($this->getExisting('user_id'));
	}
    
    
    /**
     * Получение модели Participant
     * @return  Esthetic_CS_Model_Participant
     */
    protected function _getParticipantModel ( ) {
        return $this->getModelFromCache('Esthetic_CS_Model_Participant');
    }
}