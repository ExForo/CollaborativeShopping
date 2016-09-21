<?php
/**
 * Обработчик уведомлений
 * @package     Esthetic_CS
 */
class Esthetic_CS_AlertHandler_Shopping extends XenForo_AlertHandler_Abstract {

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
     * Заглушка селектора контента
     */
    public function getContentByIds (array $content_ids, $model, $user_id, array $viewing_user) {
        return 'estcs';
    }

    
    /**
     * Подготовка массива уведомления
     * @param   array   $item
     * @return  array
     */
    protected function _prepareMessage (array $item) {
        
        $data = unserialize($item['extra_data']);
        
        switch ($data['alert_type']) {
            case 'custom':
                $item['extra'] = array (
                    'message' => $data['message']
                );
                break;
                
            case 'shopping_stage_upgrade':
                $item['extra'] = array (
                    'message'   => new XenForo_Phrase ('estcs_stage_upgrade_' . $data['to_stage']),
                    'link'      => XenForo_Link::buildPublicLink('full:threads', $data)
                );
                break;
                
            case 'shopping_stage_downgrade':
                $item['extra'] = array (
                    'message'   => new XenForo_Phrase ('estcs_stage_downgrade_' . $data['to_stage']),
                    'link'      => XenForo_Link::buildPublicLink('full:threads', $data)
                );
                break;
                
            case 'shopping_participant_kick':
                $item['extra'] = array (
                    'message'   => new XenForo_Phrase ('estcs_kicked_from_shopping'),
                    'link'      => XenForo_Link::buildPublicLink('full:threads', $data)
                );
                break;
                
            case 'shopping_organizer_kick':
                $item['extra'] = array (
                    'message'   => new XenForo_Phrase ('estcs_organizer_kicked_from_shopping'),
                    'link'      => XenForo_Link::buildPublicLink('full:threads', $data)
                );
                break;
                
            case 'shopping_organizer_cancel_approval':
                $item['extra'] = array (
                    'message'   => new XenForo_Phrase ('estcs_organizer_cancel_approval'),
                    'link'      => XenForo_Link::buildPublicLink('full:threads', $data)
                );
                break;
                
            case 'shopping_organizer_approval':
                $item['extra'] = array (
                    'message'   => new XenForo_Phrase ('estcs_shopping_organizer_approval'),
                    'link'      => XenForo_Link::buildPublicLink('full:threads', $data)
                );
                break;
                
                
            // 1.1.0 +
            
            case 'shopping_collection_date_set':
                $item['extra'] = array (
                    'message'       => new XenForo_Phrase ('estcs_alert_shopping_collection_date_set'),
                    'link'          => XenForo_Link::buildPublicLink('full:threads', $data)
                );
                break;
                
            case 'shopping_new_participant':
                $item['extra'] = array (
                    'message'       => empty ($data['is_finished']) ? new XenForo_Phrase ('estcs_alert_shopping_new_participant') : new XenForo_Phrase ('estcs_alert_shopping_new_participant_in_finished'),
                    'link'          => XenForo_Link::buildPublicLink('full:threads', $data)
                );
                break;
                
            case 'shopping_marked_paid':
                $item['extra'] = array (
                    'message'       => new XenForo_Phrase ('estcs_alert_shopping_marked_paid'),
                    'link'          => XenForo_Link::buildPublicLink('full:threads', $data)
                );
                break;
                
            case 'shopping_estimate':
                $item['extra'] = array (
                    'message'       => new XenForo_Phrase ('estcs_alert_shopping_estimate'),
                    'link'          => $data['link'],
                    'important'     => true,
                    'prefix'        => new XenForo_Phrase ('estcs_estimate_short')
                );
                break;
            
            // 1.1.3 +
            
            case 'shopping_forcibly_joined':
                $item['extra'] = array (
                    'message'       => new XenForo_Phrase ('estcs_alert_shopping_forcibly_join'),
                    'link'          => XenForo_Link::buildPublicLink('full:threads', $data)
                );
                break;
        }
        
        if (!empty ($item['extra'])) {
            $item['extra']['thread'] = false;
            
            if (!empty ($data['thread_id']) && !empty ($data['title'])) {
                $item['extra']['thread'] = array (
                    'thread_id' => $data['thread_id'],
                    'title'     => $data['title']
                );
            }
        }
        
        unset($item['extra_data']);
        
        return $item;
    }
}