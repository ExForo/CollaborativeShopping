<?php
/**
 * Шаблон модели User
 * @package     Esthetic_CS
 */
class Esthetic_CS_DataWriter_User extends XFCP_Esthetic_CS_DataWriter_User {

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
     * Обработка записей после удаления пользователя
     * @return  null
     */
    protected function _postDelete ( ) {

        $this->_getShoppingModel()->cleanUpByUserId($this->get('user_id'));
        
        return parent::_postDelete();
    }
    
    
    /**
     * Обработка записей после сохранения пользователя
     * @return  null
     */
    protected function _postSave ( ) {

        if ($this->get('is_banned')) {
            $this->_getShoppingModel()->cleanUpByUserId($this->get('user_id'));
        }
        
        return parent::_postSave();
    }
    

    /**
     * Получение модели Shopping
     * @return  Esthetic_CS_Model_Shopping
     */
    protected function _getShoppingModel ( ) {
        return $this->getModelFromCache('Esthetic_CS_Model_Shopping');
    }
}