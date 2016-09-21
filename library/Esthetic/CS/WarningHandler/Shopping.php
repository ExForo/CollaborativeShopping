<?php
/**
 * Обработчик контента Shopping
 * @package     Esthetic_CS
 */
class Esthetic_CS_WarningHandler_Shopping {

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
     * Определения права просматривать нарушения(в плане покупок - публичные для всех)
     * @param   array       $content
     * @param   array       $viewing_user
     * @return  true
     */
    public function canView (array $content, array $viewing_user = null) {
        return true;
    }
    
    
    /**
     * Получение контента нарушения
     * @param   int         $content_id
     * @param   array       $viewing_user
     * @return  array|false
     */
    public function getContent ($content_id, array $viewing_user = null) {
        if (is_array ($content_id)) {
            $content_id = array_shift ($content_id);
        }
        return $this->_getThreadModel()->getThreadById($content_id);
    }
    
    
    /**
     * Заглушка для функции дополнительных действий при регистрации нарушения
     * @param   array       $warning
     * @param   array       $content
     * @param   string      $public_message
     * @param   array       $viewing_user
     * @return  null
     */
    final public function warn (array $warning, array $content, $public_message = '', array $viewing_user = null) { }
    
    
    /**
     * Подготовка контента для отображения
     * @param   array       $warning
     * @param   array       $viewing_user
     * @return  null
     */
    final public function prepareWarning (array $warning, array $viewing_user = null) {
        return $warning;
    }
    
    
    /**
     * Построение ссылки на тему покупки
     * @param   array       $content
     * @param   bool        $canonical
     * @return  string
     */
    public function getContentUrl (array $content, $canonical = false) {
        return XenForo_Link::buildPublicLink(($canonical ? 'canonical:' : '') . 'threads', $content);
    }
    
    
    /**
     * Заглушка для функции дополнительных действий при удалении нарушения
     * @param   array       $warning
     * @param   array       $content
     * @return  null
     */
    final public function reverseWarning (array $warning, array $content = array ( )) { }
    
    
    /**
     * @return XenForo_Model_Thread
     */
    protected function _getThreadModel ( ) {
        return XenForo_Model::create('XenForo_Model_Thread');
    }
}