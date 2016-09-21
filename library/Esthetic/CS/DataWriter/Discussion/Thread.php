<?php
/**
 * Шаблон модели Discussion_Thread
 * @package     Esthetic_CS
 */
class Esthetic_CS_DataWriter_Discussion_Thread extends XFCP_Esthetic_CS_DataWriter_Discussion_Thread {

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
     * Сохранение идентификатора треда
     * @return  null
     */
    protected function _discussionPostSave ( ) {
    
        if (XenForo_Application::isRegistered('estcs_thread_id')) {
            XenForo_Application::set('estcs_thread_id', $this->get('thread_id'));
        }
        
        $thread_id = $this->get('thread_id');
        
        $estimate_thread = $this->_getEstimateThreadModel()->getEstimateThreadByThreadId($thread_id);
        if (!empty ($estimate_thread)) {
            
            /**
             * Удаление записи estimate thread, если тред был перемещен в другой раздел
             */
            if ($this->get('node_id') != (int)$estimate_thread['node_id'] || $this->get('discussion_state') != 'visible') {
                $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_EstimateThread');
                $dw->setExistingData($estimate_thread);
                $dw->delete();
            }
        }

        return parent::_discussionPostSave();
    }
    
    
    /**
     * Сохранение идентификатора треда
     * @return  null
     */
    protected function _discussionPostDelete ( ) {
    
        $thread_id = $this->get('thread_id');
        
        if ($thread_id > 0) {
            $this->getModelFromCache('Esthetic_CS_Model_Shopping')->deleteByThreadId($thread_id);
        }
        
        $estimate_thread = $this->_getEstimateThreadModel()->getEstimateThreadByThreadId($thread_id);
        if (!empty ($estimate_thread)) {
            
            /**
             * Удаление записи estimate thread при удалении треда
             */
            $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_EstimateThread');
            $dw->setExistingData($estimate_thread);
            $dw->delete();
        }
        
        return parent::_discussionPostDelete();
    }

    
    /**
     * Получение модели EstimateThread
     * @return  Esthetic_CS_Model_EstimateThread
     */
    protected function _getEstimateThreadModel ( ) {
        return $this->getModelFromCache('Esthetic_CS_Model_EstimateThread');
    }
}