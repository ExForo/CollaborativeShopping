<?php
/**
 * Модель Conversation
 * @package     Esthetic_CS
 */
class Esthetic_CS_Model_Conversation extends XFCP_Esthetic_CS_Model_Conversation {

    /**
     * Номер версии скрипта
     * @var     int
     */
    public static $est_version_id   = 1061;
    
    /**
     * Идентификатор продукта
     * @var     string
     */
    public static $est_addon_id     = 'estcs';


	/**
     * Подсчет допустимого количества участников переписки
	 * @param   array       $conversation
	 * @param   array|null  $viewing_user
	 * @return  int
	 */
    public function allowedAdditionalConversationRecipients (array $conversation, array $viewing_user = null) {
        
        if (!XenForo_Application::isRegistered('estcs_allow_max_conversation_participants')) {
            return parent::allowedAdditionalConversationRecipients ($conversation, $viewing_user);
        }
        
        if (!XenForo_Application::get('estcs_allow_max_conversation_participants')) {
            return parent::allowedAdditionalConversationRecipients ($conversation, $viewing_user);
        }
        
        return -1;
    }
}