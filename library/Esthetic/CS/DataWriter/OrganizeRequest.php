<?php
/**
 * Шаблон модели OrganizeRequest
 * @package     Esthetic_CS
 */
class Esthetic_CS_DataWriter_OrganizeRequest extends XenForo_DataWriter {

    /**
     * Номер версии скрипта
     * @var     int
     */
    public static $est_version_id   = 104;
    
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
            'estcs_organize_request'    => array (
                'shopping_id'               => array (
                    'type'                      => self::TYPE_UINT,
                    'required'                  => true
                ),
                'user_id'                   => array (
                    'type'                      => self::TYPE_UINT,
                    'required'                  => true
                ),
                'created_at'                => array (
                    'type'                      => self::TYPE_UINT,
                    'default'                   => XenForo_Application::$time
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
        
        return array ('estcs_organize_request' => $this->_getOrganizeRequestModel()->getOrganizerRecord($shopping_id, $user_id));
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
     * Получение модели OrganizeRequest
     * @return  Esthetic_CS_Model_OrganizeRequest
     */
    protected function _getOrganizeRequestModel ( ) {
        return $this->getModelFromCache('Esthetic_CS_Model_OrganizeRequest');
    }
}