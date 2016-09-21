<?php
/**
 * Вспомагательный класс Shopping
 * @package     Esthetic_CS
 */
class Esthetic_CS_Helper_Shopping {

    /**
     * Номер версии скрипта
     * @var     int
     */
    public static $est_version_id   = 1130;
    
    /**
     * Идентификатор продукта
     * @var     string
     */
    public static $est_addon_id     = 'estcs';
    
    
    
    /**
     * Получение сокращенного названия валюты для проведения операций
     * @return string
     */
    public static function getCurrencyTitle ( ) {
        
        // TODO: Интеграция названия валюты с банкингом
        
        $options = XenForo_Application::get('options');
        
        return $options->estcs_currency_title;
    }
    
    
    /**
     * Проверяет, является ли текущий пользователь участником покупки
     * @param   array       $participants
     * @return  bool
     */
    public static function isParticipantOf ($participants) {
        
        $user_id = XenForo_Visitor::getUserId();
        
        if (!$user_id) {
            return false;
        }
        
        foreach ($participants as $participant) {
            if ($participant['user_id'] == $user_id) return true;
        }
        
        return false;
    }
    
    
    /**
     * Проверяет, наличие оплаты продукта
     * @param   array       $participants
     * @return  bool
     */
    public static function isPaid ($participants) {
        
        $user_id = XenForo_Visitor::getUserId();
        
        if (!$user_id) {
            return false;
        }
        
        foreach ($participants as $participant) {
            if ($participant['user_id'] == $user_id) {
                if ($participant['is_payed']) {
                    return true;
                }
                return false;
            }
        }
        
        return false;
    }
    
    
    /**
     * Проверяет, наличие оплаты продукта
     * @param   array       $participants
     * @return  bool
     */
    public static function loadParticipant ($participants) {
        
        $user_id = XenForo_Visitor::getUserId();
        
        if (!$user_id) {
            return false;
        }
        
        foreach ($participants as $participant) {
            if ($participant['user_id'] == $user_id) {
                return $participant;
            }
        }
        
        return false;
    }
    
    
    /**
     * Формирование числового значения этапа покупки
     * @param   array       $shopping
     * @return  int
     */
    public static function getStageId ($shopping) {
        
        $stages = array (
            'preopen'       => 0,
            'open'          => 1,
            'active'        => 2,
            'finished'      => 3,
            'closed'        => 4,
            'banned'        => 5
        );
        
        if (empty ($shopping)) {
            return false;
        }
        
        if (is_string ($shopping)) {
            return $stages[$shopping];
        }
        
        if (empty ($shopping['stage'])) {
            return false;
        }
        
        return $stages[$shopping['stage']];
    }
    
    
    /**
     * Расчет гонорара организатора
     * @param   float       $shopping
     * @param   array       $organizer
     * @return  float
     */
    public static function getOrganizerFee ($shopping, $organizer = false) {
    
        if (empty ($shopping['price'])) {
            return false;
        }

        $options = XenForo_Application::get('options');
        
        $fee = self::getActualFeeValues($organizer);
        
        /**
         * Отказ от вознаграждения
         */
        if (!empty ($shopping['extended_data']['organizer_role_id']) && !empty ($shopping['organizer_id'])) {
            return 0;
        }
        
        $price = floatval ($shopping['price']);
        
        $percent_fee = round (($fee['percent'] / 100) * $price, 2);
        
        if ($options->estcs_organizer_price_limit > 0 && $options->estcs_organizer_price_limit > $price) {
            return 0;
        }

        switch ($fee['type']) {
            case 2:
                $_fee = $percent_fee > $fee['fix'] ? $percent_fee : $fee['fix'];
                break;
                
            case 1:
                $_fee = $percent_fee;
                break;
                
            default:
                $_fee = $fee['fix'];
        }

        return $_fee;
    }
    
    
    /**
     * Получение актуальных значений заработка организатора из значений, установленных в настройках группы
     * @param   array   $organizer
     * @return  array
     */
    public static function getActualFeeValues ($organizer = false) {
    
        $options = XenForo_Application::get('options');

        $permissions = array ( );
        if (!empty ($organizer)) {
            if (!empty ($organizer['permissions']) && !empty ($organizer['permissions']['estcs'])) {
                $permissions = $organizer['permissions']['estcs'];
            } else if (!empty ($organizer['global_permission_cache'])) {
                $permissions = unserialize ($organizer['global_permission_cache']);
                if (!empty ($permissions['estcs'])) {
                    $permissions = $permissions['estcs'];
                } else {
                    $permissions = array ( );
                }
            }
        }

        $fee = array (
            'type'      => $options->estcs_organizer_fee_type,
            'fix'       => $options->estcs_organizer_fee_fix,
            'percent'   => $options->estcs_organizer_fee_percent
        );
        
        if (isset ($permissions['estcs_fee_fix_ratio']) && intval ($permissions['estcs_fee_fix_ratio']) > 0) {
            $fee['fix'] = !Esthetic_CS_Service_Config::$organizer_fee_extend_use_simple_mode ? 
                round ($fee['fix'] * ($permissions['estcs_fee_fix_ratio'] / 100), 2) :
                $permissions['estcs_fee_fix_ratio'];
        }
        
        if (isset ($permissions['estcs_fee_percent_ratio']) && intval ($permissions['estcs_fee_percent_ratio']) > 0) {
            $fee['percent'] = !Esthetic_CS_Service_Config::$organizer_fee_extend_use_simple_mode ? 
                round ($fee['percent'] * ($permissions['estcs_fee_percent_ratio'] / 100), 2) :
                $permissions['estcs_fee_percent_ratio'];
        }
        
        if (isset ($permissions['estcs_fee_type_id']) && intval ($permissions['estcs_fee_type_id']) > 0 && intval ($permissions['estcs_fee_type_id']) < 4) {
            $fee['type'] = $permissions['estcs_fee_type_id'] - 1;
        }
        
        if (empty ($permissions['estcs_can_get_money'])) {
            $fee = array (
                'type'      => 0,
                'fix'       => 0,
                'percent'   => 0
            );
        }

        return $fee;
    }
    
    
    /**
     * Расчет гонорара ресурса(администратора проекта)
     * @param   float       $price      Цена продукта
     * @return  float
     */
    public static function getResourceFee ($price) {
        
        $options = XenForo_Application::get('options');
        
        $percent_fee = round (($options->estcs_resource_fee_percent / 100) * (float)$price, 2);
        
        switch ($options->estcs_resource_fee_type) {
            case 2:
                $fee = $percent_fee > $options->estcs_resource_fee_fix ? $percent_fee : $options->estcs_resource_fee_fix;
                break;
                
            case 1:
                $fee = $percent_fee;
                break;
                
            default:
                $fee = $options->estcs_resource_fee_fix;
        }
        
        return $fee;
    }
    
    
    /**
     * Подготовка контента новостей
     * @param   string  $notice_title
     * @param   string  $where_clause
     * @return  mixed
     */
    public static function prepareNotice ($notice_title, $where_clause = '') {
        
        $db = XenForo_Application::getDb();
        
        $options = XenForo_Application::get('options');
        
        $threads = $db->fetchAll(
            'SELECT t.*
                FROM `xf_thread` AS t
                LEFT JOIN `estcs_shopping` AS s ON (s.thread_id = t.thread_id)
                WHERE t.discussion_state = \'visible\' AND s.shopping_id > 0 ' . (!empty ($where_clause) ? 'AND ' . $where_clause : '') . '
                ORDER BY t.last_post_date DESC
                LIMIT ?', array ($options->estcs_notice_content_max)
        );
        
        if (empty ($threads)) {
            return false;
        }
        
        $template = new XenForo_Template_Public ('estcs_notice', array (
            'notice_title'          => new XenForo_Phrase ($notice_title),
            'threads'               => $threads
        ));
        
        return $template->render();
    }
    
    
    /**
     * Определение состояния покупки в процентном отношении
     * @param   array   $shopping
     * @param   array   $participants
     * @return  integer
     */
    public static function getCompletenessLevelPercent ($shopping, $participants = false) {
        
        $participant_model = XenForo_Model::create('Esthetic_CS_Model_Participant');
        
        if (!$participants) {
            if (false === ($participants = $participant_model->getByShoppingId($shopping['shopping_id'], true))) {
                return 0;
            }
        }
        
        if ($shopping['participants'] == 0) {
            return 100;
        }
        
        $participants = $participant_model->prepareParticipantsList($participants, !empty ($shopping['extended_data']['sort_members_list']));
        
        /**
         * Определение расчетного количества пользователей основного списка
         */
        $primary_participants_count = count ($participants['general']);
        if (!empty ($shopping['participants'])) {
            $primary_participants_count = $shopping['participants'];
        }
    
        $stage_complete = 0;
        if ($shopping['stage'] == 'open') {
            if (count ($participants['general']) >= $shopping['participants']) {
                $stage_complete = 100;
            } else {
                $stage_complete = (count ($participants['general']) / $shopping['participants']) * 100;
            }
        }
        
        if ($shopping['stage'] == 'active') {
        
            $paid_users = 0;
            
            if (count ($participants['general']) > 0) {
                foreach ($participants['general'] as $_participant) {
                    if ($_participant['is_payed']) {
                        $paid_users++;
                    }
                }
            }
            
            if (count ($participants['reserve']) > 0) {
                foreach ($participants['reserve'] as $_participant) {
                    if ($_participant['is_payed']) {
                        $paid_users++;
                    }
                }
            }
            
            $stage_complete = $primary_participants_count > 0 ? ($paid_users / $primary_participants_count) * 100 : 0;
            
            if ($stage_complete > 100) {
                $stage_complete = 100;
            }
        }
        
        return $stage_complete;
    }
}