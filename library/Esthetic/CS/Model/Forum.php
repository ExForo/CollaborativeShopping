<?php
/**
 * Модель Forum
 * @package     Esthetic_CS
 */
class Esthetic_CS_Model_Forum extends XFCP_Esthetic_CS_Model_Forum {

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
     * Получение узла по указанному идентификатору
     * @param integer   $id
     * @param array     $fetch_options
     * @return array
     */
    public function getForumById ($id, array $fetch_options = array ( )) {
    
        $result = parent::getForumById($id, $fetch_options);
        
        if (!empty ($result['estcs_type'])) {
            XenForo_Application::set('estcs_last_forum_loaded', $result);
        }
        
        return $result;
    }

    /**
     * Получение узла по указанному имени
     * @param   string      $name
     * @param   array       $fetch_options
     * @return  array
     */
    public function getForumByNodeName ($name, array $fetch_options = array ( )) {
    
        $result = parent::getForumByNodeName($name, $fetch_options);
        
        if (!empty ($result['estcs_type'])) {
            XenForo_Application::set('estcs_last_forum_loaded', $result);
        }
        
        return $result;
    }
}