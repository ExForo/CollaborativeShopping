<?php
/**
 * Контроллер 'Inline_Shopping'
 * @package     Esthetic_CS
 */
class Esthetic_CS_ControllerPublic_Inline_Shopping extends XenForo_ControllerPublic_Abstract {
    
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
     * @var array
     */
    protected $_shopping            = false;
    
    /**
     * @var array
     */
    protected $_participants        = false;
    
    /**
     * @var array
     */
    protected $_forum               = false;
    
    /**
     * @var array
     */
    protected $_thread              = false;
    
    
    
    
    /**
     * Диспетчер контроллера
     * @param   string      $action
     * @return  null
     */
    protected function _preDispatch ($action) {
        
        $this->_shopping = $this->_getShoppingModel()->getShoppingById($this->_input->filterSingle('shopping_id', XenForo_Input::UINT));
        
        if (!empty ($this->_shopping)) {
            $ftp_helper = $this->getHelper('ForumThreadPost');
            
            list ($thread_fetch_options, $forum_fetch_options) = $this->_getThreadForumFetchOptions();
            list ($this->_thread, $this->_forum) = $ftp_helper->assertThreadValidAndViewable($this->_shopping['thread_id'], $thread_fetch_options, $forum_fetch_options);
        }
        
        return parent::_preDispatch($action);
    }
    
    
    /**
     * Применение редактирования данных покупки
     * @return  XenForo_ControllerResponse_Abstract
     */
    public function actionUpdate ( ) {
    
        if ($this->getResponseType() != 'json') {
            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildPublicLink('threads', $this->_thread)
            );
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_responseError(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $this->_responseError($organizer_state);
        }
        
        $visitor = XenForo_Visitor::getInstance();
        $options = XenForo_Application::get('options');
        
        if (!$visitor->hasPermission('estcs', 'estcs_can_manage') && true !== $organizer_state && Esthetic_CS_Helper_Shopping::getStageId($this->_shopping) < 4) {
            return $this->_responseError(new XenForo_Phrase ('estcs_error_thread_not_found'), 403);
        }
        
        $field = strtolower ($this->_input->filterSingle('field', XenForo_Input::STRING));
        
        $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Shopping');
        $dw->setExistingData($this->_shopping['shopping_id']);
        
        switch ($field) {
        
            case 'price':
            
                $value = $this->_input->filterSingle('value', XenForo_Input::FLOAT);
                
                if (floatval ($value) <= 0) {
                    return $this->_responseError(new XenForo_Phrase ('estcs_price_correct_value_required'));
                }
                
                if (floatval ($value) < $options->estcs_minimal_price) {
                    return $this->_responseError(new XenForo_Phrase ('estcs_error_price_low_limit_reached', array (
                        'limit' => sprintf ('%0.2f', $options->estcs_minimal_price),
                        'currency' => $options->estcs_currency_title
                    )));
                }
                
                $dw->set('price', round ($value, 2));
                
                break;
                
            case 'payment':
            
                $value = $this->_input->filterSingle('value', XenForo_Input::FLOAT);
                
                if ($visitor->hasPermission('estcs', 'estcs_can_edit_payment') || $visitor->hasPermission('estcs', 'estcs_can_manage')) {
                    if ($value < $options->estcs_minimum_payment && $value != 0) {
                        return $this->_responseError(new XenForo_Phrase (
                            'estcs_error_payment_minimal_limit', 
                            array (
                                'limit'     => $options->estcs_minimum_payment, 
                                'currency'  => $options->estcs_currency_title
                            )), 400);
                    }
                    $dw->set('payment', $value);
                } else if ($value) {
                    return $this->_responseError(new XenForo_Phrase ('estcs_error_no_permission_to_edit_payment'), 403);
                }
                
                break;
                
            case 'participants':
            
                $value = $this->_input->filterSingle('value', XenForo_Input::UINT);
                
                if ($value > 0 && $value < $options->estcs_minimum_participants) {
                    return $this->_getErrorContent(new XenForo_Phrase ('estcs_you_need_x_participants_at_last', array ('x' => $options->estcs_minimum_participants)), 400);
                }
                $dw->set('participants', $value);
                
                break;
                
            default:
        }
        
        $dw->save();
        
        $shopping_data = $this->_getShoppingData();
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread),
            $shopping_data
        );
    }
    
    
    /**
     * Отображение формы редактирования информации о доставке и атрибутах платежа
     * @return  XenForo_ControllerResponse_Abstract
     */
    public function actionDetailsEdit ( ) {
        
        if ($this->getResponseType() != 'json') {
            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildPublicLink('threads', $this->_thread)
            );
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_responseError(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $this->_responseError($organizer_state);
        }
        
        return $this->responseView(
            'Esthetic_CS_ViewPublic_Shopping_Inline_DetailsEdit', 
            'estcs_details_edit', 
            array (
                'forum'     => $this->_forum,
                'thread'    => $this->_thread,
                'shopping'  => $this->_shopping,
                
                'type'      => $this->_input->filterSingle('type', XenForo_Input::STRING)
            )
        );
    }
    
    
    /**
     * Сохранение информации о доставке и атрибутах платежа
     * @return  XenForo_ControllerResponse_Abstract
     */
    public function actionDetailsSave ( ) {
    
        if ($this->getResponseType() != 'json') {
            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildPublicLink('threads', $this->_thread)
            );
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_responseError(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $this->_responseError($organizer_state);
        }
        
        $prefix = false;
        switch ($this->_input->filterSingle('content_type', XenForo_Input::STRING)) {
            case 'payment':
                $message = XenForo_Helper_String::autoLinkBbCode($this->getHelper('Editor')->getMessageText('payment', $this->_input));
                $prefix = 'payment';
                break;
                
            case 'delivery':
                $message = XenForo_Helper_String::autoLinkBbCode($this->getHelper('Editor')->getMessageText('delivery', $this->_input));
                $prefix = 'product';
                break;
                
            default:
        }
        
        if ($prefix === false) {
            return $this->_responseError(new XenForo_Phrase ('estcs_error_while_saving_shopping_extends'));
        }

        $extended_data = $this->_shopping['extended_data'];

        if (strlen (utf8_decode ($message)) > 1000) {
            return $this->_responseError(new XenForo_Phrase ('estcs_error_' . $prefix . '_details_to_long'));
        }
        $extended_data[$prefix . '_details'] = $message;
        
        $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Shopping');
        $dw->setExistingData($this->_shopping['shopping_id']);
        
        $dw->set('extended_data', $extended_data);
        $dw->save();
        
        return $this->responseView(
            'Esthetic_CS_ViewPublic_Shopping_Inline_DetailsMessage', 
            'estcs_details_message', 
            array (
                'forum'             => $this->_forum,
                'thread'            => $this->_thread,
                'shopping'          => $this->_shopping,
                
                'content_type'      => $prefix == 'payment' ? $prefix : 'delivery',
                'details_message'   => $message
            )
        );
    }
    
    
    /**
     * Установка флагов параметров доступа к спискам
     * @return  XenForo_ControllerResponse_Abstract
     */
    public function actionListAccessSettings ( ) {
        
        if ($this->getResponseType() != 'json') {
            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildPublicLink('threads', $this->_thread)
            );
        }
        
        if (empty ($this->_forum) || empty ($this->_thread)) {
            return $this->_responseError(new XenForo_Phrase ('estcs_error_thread_not_found'), 404);
        }
        
        $organizer_state = $this->_organizerCheckout();
        if (true !== $organizer_state) {
            return $this->_responseError($organizer_state);
        }
        
        $extended_data = $this->_shopping['extended_data'];
        
        $dw = XenForo_DataWriter::create('Esthetic_CS_DataWriter_Shopping');
        $dw->setExistingData($this->_shopping['shopping_id']);
        
        $option_name = $this->_input->filterSingle('option_name', XenForo_Input::STRING);
        
        switch ($option_name) {
            
            case 'allow_reserve':
                $extended_data['deny_reserve'] = empty ($extended_data['deny_reserve']);
                if ($extended_data['deny_reserve']) {
                    $message = new XenForo_Phrase ('estcs_reserve_list_denied');
                } else {
                    $message = new XenForo_Phrase ('estcs_reserve_list_allowed');
                }
                break;
                
            case 'allow_post_buy':
                $extended_data['allow_post_buy'] = empty ($extended_data['allow_post_buy']);
                if ($extended_data['allow_post_buy']) {
                    $message = new XenForo_Phrase ('estcs_post_buy_list_allowed');
                } else {
                    $message = new XenForo_Phrase ('estcs_post_buy_list_denied');
                }
                break;
                
            case 'sort_members_list':
                $extended_data['sort_members_list'] = empty ($extended_data['sort_members_list']);
                $message = false;
                break;
                
            default:
                $message = false;
        }

        $dw->set('extended_data', $extended_data);
        $dw->save();
        
        $shopping_data = $this->_getShoppingData(true);
        $shopping_data['message'] = $message;
        
        if ($option_name == 'sort_members_list') {
            
            if (false === ($p_list = $this->_getParticipantsList())) {
                return $this->_responseError(new XenForo_Phrase ('estcs_error_unnable_to_load_participants_list'), 400);
            }
            
            $visitor = XenForo_Visitor::getInstance();
            
            $participant = false;
            if (Esthetic_CS_Helper_Shopping::isParticipantOf($p_list)) {
                $participant = Esthetic_CS_Helper_Shopping::loadParticipant($p_list);
            }
            
            $participants = $this->_getParticipantModel()->prepareParticipantsList($p_list, !empty ($this->_shopping['extended_data']['sort_members_list']));
            
            return $this->responseView(
                'Esthetic_CS_ViewPublic_Shopping_Inline_MembersList', 
                'estcs_shopping_members_list', 
                array (
                    'forum'                 => $this->_forum,
                    'thread'                => $this->_thread,
                    'shopping'              => $this->_shopping,
                    'shopping_data'         => array (
                        'stage_id'              => Esthetic_CS_Helper_Shopping::getStageId($this->_shopping),
                        'participant'           => $participant,
                        'participants'          => $participants,
                        'permissions'           => array (
                            'can_manage'            => true,
                            'is_supermanager'       => $visitor->hasPermission('estcs', 'estcs_can_manage')
                        )
                    ),
                    'initialization_json'   => json_encode ($shopping_data)
                )
            );
        }
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread),
            $shopping_data
        );
    }
    
    
    /**
     * Загрузка списка участников покупки
     * @return array
     */
    protected function _getParticipantsList ( ) {
        
        if (empty ($this->_participants)) {
            $this->_participants = $this->_getParticipantModel()->getByShoppingId($this->_shopping['shopping_id'], true);
        }
        
        return $this->_participants;
    }
    
    
    /**
     * Формирование ответа о ошибке
     * @param   string      $message
     * @return  XenForo_ControllerResponse_Abstract
     */
    protected function _responseError ($message) {
    
        $shopping_data = $this->_getShoppingData();
        
        $shopping_data['is_error'] = true;
        $shopping_data['error_message'] = $message;
        
        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::SUCCESS,
            XenForo_Link::buildPublicLink('threads', $this->_thread),
            $shopping_data
        );
    }
    
    
    /**
     * Обновление данных покупки
     * @param   boolean $update_cache
     * @return array
     */
    protected function _getShoppingData ($update_cache = false) {
    
        $shopping = $this->_getShoppingModel()->getShoppingById($this->_shopping['shopping_id']);
        
        $estimate = $this->_getShoppingModel()->getEstimate(
            $shopping, 
            $shopping['organizer_id'] ? $this->_getShoppingModel()->getOrganizerByShoppingId($shopping['shopping_id']) : false,
            $this->_getParticipantModel()->getByShoppingId($shopping['shopping_id'], true)
        );
        
        $payment = $estimate['estimated']['payment'];
        $payment_clear = $estimate['calculated']['payment'];
        
        if ($update_cache) {
            $this->_shopping = $shopping;
        }

        $participants = $this->_getParticipantModel()->prepareParticipantsList($this->_getParticipantsList(), !empty ($this->_shopping['extended_data']['sort_members_list']));
        
        return array (
            'price'             => sprintf ('%0.2f', $shopping['price']),
            'payment'           => ($payment - (int)$payment > 0) ? sprintf ('%0.2f', $payment) : sprintf ('%d', (int)$payment),
            'payment_clear'     => ($payment_clear - (int)$payment_clear > 0) ? sprintf ('%0.2f', $payment_clear) : sprintf ('%d', (int)$payment_clear),
            'participants'      => $shopping['participants'],
            'participants_now'  => !empty ($participants) ? count ($participants['general']) : 0,
            'is_fixed_payment'  => $estimate['flags']['is_payment_changed'],
            'resource_fee'      => sprintf ('%0.2f', $estimate['estimated']['taxes']['service']),
            'organizer_fee'     => sprintf ('%0.2f', $estimate['estimated']['taxes']['organizer']),
            'payment_fee'       => sprintf ('%0.2f', $estimate['taxes']['payment']),
            'completeness'      => Esthetic_CS_Helper_Shopping::getCompletenessLevelPercent($shopping, false),
            'stage'             => $shopping['stage'],
            
            'allow_post_buy'    => $shopping['extended_data']['allow_post_buy'],
            'allow_reserve'     => !$shopping['extended_data']['deny_reserve']
        );
    }
    
    
    /**
     * Проверка параметров организатора группы
     * @return  true|object
     */
    protected function _organizerCheckout ( ) {
        
        $visitor = XenForo_Visitor::getInstance();
        
        if (empty ($this->_shopping)) {
            return 'estcs_error_shopping_not_found';
        }
        
        if ($visitor->hasPermission('estcs', 'estcs_can_manage')) {
            return true;
        }
        
        if (!$visitor->hasPermission('estcs', 'estcs_can_organize')) { 
            return 'estcs_error_organize_permission_required';
        }
        
        if ($this->_shopping['organizer_id'] != $visitor['user_id']) {
            return 'estcs_error_user_is_not_organizer';
        }
        
        return true;
    }
    
    
    /**
     * Параметры получения данных о форуме и теме
     * @return  array
     */
	protected function _getThreadForumFetchOptions ( ) {
    
        $visitor = XenForo_Visitor::getInstance();

        $thread_fetch_options = array (
            'readUserId' => $visitor['user_id']
        );
        
        $forum_fetch_options = array(
            'readUserId' => $visitor['user_id']
        );

        return array ($thread_fetch_options, $forum_fetch_options);
	}
    
    
	/**
     * Получение модели Shopping
	 * @return Esthetic_CS_Model_Shopping
	 */
	protected function _getShoppingModel ( ) {
		return $this->getModelFromCache('Esthetic_CS_Model_Shopping');
	}
    
    /**
     * Получение модели Participant
     * @return  Esthetic_CS_Model_Participant
     */
    protected function _getParticipantModel ( ) {
        return $this->getModelFromCache('Esthetic_CS_Model_Participant');
    }
}