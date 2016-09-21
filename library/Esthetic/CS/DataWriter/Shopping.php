<?php
/**
 * Шаблон модели Shopping
 * @package     Esthetic_CS
 */
class Esthetic_CS_DataWriter_Shopping extends XenForo_DataWriter {

    /**
     * Номер версии скрипта
     * @var     int
     */
    public static $est_version_id   = 100;
    
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
            'estcs_shopping'            => array (
                'shopping_id'       => array (
                    'type'              => self::TYPE_UINT,
                    'autoIncrement'     => true
                ),
                'thread_id'         => array (
                    'type'              => self::TYPE_UINT,
                    'required'          => true
                ),
                'organizer_id'      => array (
                    'type'              => self::TYPE_UINT,
                    'required'          => true,
                    'default'           => 0
                ),
                'price'             => array (
                    'type'              => self::TYPE_FLOAT,
                    'default'           => 0.00,
                    'required'          => true,
                    'requiredError'     => 'estcs_price_required_error',
                    'verification'      => array ('$this', '_verifyPrice'),
                    'max'               => 999999999
                ),
                'payment'             => array (
                    'type'              => self::TYPE_FLOAT,
                    'default'           => 0.00,
                    'max'               => 999999999    
                ),
                'participants'      => array (
                    'type'              => self::TYPE_UINT,
                    'default'           => 0,
                    'max'               => 9999
                ),
                'stage'                 => array (
                    'type'                  => self::TYPE_STRING,
                    'default'               => 'preopen',
                    'required'              => true, 
                    'allowedValues'         => array (
                        'preopen', 'open', 'active', 'finished', 'closed', 'banned'
                    )
                ),
                'collection_date'   => array (
                    'type'              => self::TYPE_UINT,
                    'default'           => 0
                ),
                'created_at'        => array (
                    'type'              => self::TYPE_UINT,
                    'default'           => XenForo_Application::$time
                ),
                'extended_data'     => array (
                    'type'              => self::TYPE_SERIALIZED, 
                    'default'           => ''
                ),
            )
        );
    }

    /**
     * Получение информации о существующих данных
     * @param   array   $data
     * @return  array
     */
    protected function _getExistingData ($data) {
        if (!$shopping_id = $this->_getExistingPrimaryKey($data)) {
            return false;
        }

        return array('estcs_shopping' => $this->_getShoppingModel()->getShoppingById($shopping_id));
    }
    

    /**
     * Получение строки обновления записи БД
     * @param   string  $table_name
     * @return  string
     */
	protected function _getUpdateCondition ($table_name) {
		return 'shopping_id = ' . $this->_db->quote($this->getExisting('shopping_id'));
	}
    
    
    /**
     * Проверка суммы
     * @param   float       &$price
     * @return  boolean
     */
    protected function _verifyPrice (&$price) {
        
        $options = XenForo_Application::get('options');
        
        if (floatval ($price) <= 0) {
            $this->error(new XenForo_Phrase ('estcs_price_correct_value_required'), 'estcs_price');
        }
        
        if (floatval ($price) < $options->estcs_minimal_price) {
            $this->error(new XenForo_Phrase ('estcs_error_price_low_limit_reached', array (
                'limit' => sprintf ('%0.2f', $options->estcs_minimal_price),
                'currency' => $options->estcs_currency_title
            )), 'estcs_price');
        }
        
        return true;
    }
    
    
    /**
     * Обработка данных после сохранения
     * @return  null
     */
    protected function _postSave ( ) {
        
        $shopping_id    = $this->get('shopping_id');
        $organizer_id   = $this->get('organizer_id');
        $data           = $this->get('extended_data');
        
        if (empty ($shopping_id) || empty ($organizer_id) || empty ($data)) {
            return false;
        }
        
        if (is_string ($data)) {
            $data = unserialize ($data);
        }
        
        if (!is_array ($data)) {
            return false;
        }
        
        $require_organizer_record = false;
        if (!empty ($data['organizer_role_id'])) {
            if ($data['organizer_role_id'] == 2) {
                $require_organizer_record = true;
            }
        }
        
        $organizer_record = $this->_getParticipantModel()->getParticipantRecord($shopping_id, $organizer_id);
        
        if (!$require_organizer_record && !empty ($organizer_record)) {
            $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Participant');
            $dw->setExistingData(array ('shopping_id' => $shopping_id, 'user_id' => $organizer_id));
            $dw->delete();
        } elseif ($require_organizer_record && empty ($organizer_record)) {
            $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Participant');
            $dw->bulkSet(array (
                'shopping_id'               => $shopping_id,
                'user_id'                   => $organizer_id,
                'is_primary'                => 1,
                'is_accepting_organizer'    => 1,
                'signed_at'                 => (int)$this->get('crated_at')
            ));
            $dw->save();
        }
        
        return true;
    }
    
    
    /**
     * Обработка данных после удаления совместной покупки
     * @return  null
     */
    protected function _postDelete ( ) {
        
        $shopping_id = $this->get('shopping_id');
        
        $this->_getParticipantModel()->deleteByShoppingId($shopping_id);
    }
    
    
    /**
     * Получение модели Shopping
     * @return  Esthetic_CS_Model_Shopping
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
}