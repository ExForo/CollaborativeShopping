<?php
/**
 * Модель User
 * @package     Esthetic_CS
 */
class Esthetic_CS_Model_User extends XFCP_Esthetic_CS_Model_User {

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
    
    const FETCH_SHOPPING_RATINGS    = 0xE0;

    
    /**
     * Prepares join-related fetch options. (override)
     * @param array $fetch_options
     * @return array Containing 'selectFields' and 'joinTables' keys.
     */
    public function prepareUserFetchOptions (array $fetch_options) {
    
        $result = parent::prepareUserFetchOptions($fetch_options);
        
		if (!empty ($fetch_options['join'])) {
			if ($fetch_options['join'] & self::FETCH_SHOPPING_RATINGS) {
            
                $result['selectFields'] .= ',
                    (SELECT COUNT(p1.organizer_vote) 
                        FROM `estcs_participant` AS p1
                        LEFT JOIN `estcs_shopping` AS s1 ON (p1.shopping_id = s1.shopping_id)
                            WHERE s1.organizer_id = user.user_id AND s1.stage = \'closed\' AND p1.organizer_vote > 0) AS organizer_vote_sum,
                    (SELECT COUNT(p2.organizer_vote) 
                        FROM `estcs_participant` AS p2
                        LEFT JOIN `estcs_shopping` AS s2 ON (p2.shopping_id = s2.shopping_id)
                            WHERE s2.organizer_id = user.user_id AND s2.stage = \'closed\') AS organizer_vote_total,
                    (SELECT COUNT(s3.shopping_id) 
                        FROM `estcs_shopping` AS s3
                            WHERE s3.organizer_id = user.user_id AND s3.stage = \'closed\') AS organizer_shoppings_total,
                    (SELECT COUNT(p4.vote) 
                        FROM `estcs_participant` AS p4
                        LEFT JOIN `estcs_shopping` AS s4 ON (p4.shopping_id = s4.shopping_id)
                            WHERE p4.user_id = user.user_id AND s4.stage = \'closed\' AND p4.vote > 0 AND p4.is_additional = 0) AS participant_vote_sum,
                    (SELECT COUNT(p5.vote) 
                        FROM `estcs_participant` AS p5
                        LEFT JOIN `estcs_shopping` AS s5 ON (p5.shopping_id = s5.shopping_id)
                            WHERE p5.user_id = user.user_id AND s5.stage = \'closed\' AND p5.is_additional = 0) AS participant_vote_total,
                    (SELECT COUNT(s6.shopping_id) 
                        FROM `estcs_shopping` AS s6
                            LEFT JOIN `estcs_participant` AS p6 ON (p6.shopping_id = s6.shopping_id)
                            WHERE p6.user_id = user.user_id AND s6.stage = \'closed\' AND p6.is_additional = 0) AS participant_shoppings_total';
			}
        }
        
        return $result;
    }
}