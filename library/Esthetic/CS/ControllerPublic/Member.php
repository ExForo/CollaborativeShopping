<?php
/**
 * Контроллер Member
 * @package     Esthetic_CS
 */
class Esthetic_CS_ControllerPublic_Member extends XFCP_Esthetic_CS_ControllerPublic_Member {

    /**
     * Номер версии скрипта
     * @var     int
     */
    public static $est_version_id   = 1082;
    
    /**
     * Идентификатор продукта
     * @var     string
     */
    public static $est_addon_id     = 'estcs';
    
    
    /**
     * Дополнение функционала действия Member
     * @return  XenForo_ControllerResponse_View
     */
    public function actionMember ( ) {
        
        $response = parent::actionMember();
        
        if (empty ($response->params['user'])) {
            return $response;
        }
        
        $ratings = $this->_getShoppingModel()->getUserRatings($response->params['user']['user_id']);
        
        if (empty ($ratings)) {
            return $response;
        }

        $response->params['user']['shopping_ratings'] = array (
            'organizer'     => array (
                'efficiency'        => intval ($ratings['organizer_vote_total']) > 0 ? intval (intval ($ratings['organizer_vote_sum']) / intval ($ratings['organizer_vote_total']) * 100) : 0,
                'deals'             => intval ($ratings['organizer_shoppings_total'])
            ),
            'participant'   => array (
                'efficiency'        => intval ($ratings['participant_vote_total']) > 0 ? intval (intval ($ratings['participant_vote_sum']) / intval ($ratings['participant_vote_total']) * 100) : 0,
                'deals'             => intval ($ratings['participant_shoppings_total'])
            )
        );

        return $response;
    }
    
    
    /**
     * Дополнение функционала действия Member
     * @return  XenForo_ControllerResponse_View
     */
    public function actionCard ( ) {
        
        $response = parent::actionCard();
        
        if (empty ($response->params['user'])) {
            return $response;
        }
        
        $ratings = $this->_getShoppingModel()->getUserRatings($response->params['user']['user_id']);
        
        if (empty ($ratings)) {
            return $response;
        }

        $response->params['user']['shopping_ratings'] = array (
            'organizer'     => array (
                'efficiency'        => intval ($ratings['organizer_vote_total']) > 0 ? intval (intval ($ratings['organizer_vote_sum']) / intval ($ratings['organizer_vote_total']) * 100) : 0,
                'deals'             => intval ($ratings['organizer_shoppings_total'])
            ),
            'participant'   => array (
                'efficiency'        => intval ($ratings['participant_vote_total']) > 0 ? intval (intval ($ratings['participant_vote_sum']) / intval ($ratings['participant_vote_total']) * 100) : 0,
                'deals'             => intval ($ratings['participant_shoppings_total'])
            )
        );

        return $response;
    }
    
    
    /**
     * Получение модели Shopping
     * @return  Esthetic_CS_Model_Shopping
     */
    protected function _getShoppingModel ( ) {
        return $this->getModelFromCache('Esthetic_CS_Model_Shopping');
    }
}