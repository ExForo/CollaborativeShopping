<?php
/**
 * Контроллер маршрута '/shopping-inline'
 * @package     Esthetic_CS
 */
class Esthetic_CS_Route_Prefix_Inline_Shopping implements XenForo_Route_Interface {

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
	 * Match a specific route for an already matched prefix.
	 * @see XenForo_Route_Interface::match()
	 */
	public function match ($routePath, Zend_Controller_Request_Http $request, XenForo_Router $router) {
		$action = $router->resolveActionWithIntegerParam($routePath, $request, 'shopping_id');
		return $router->getRouteMatch('Esthetic_CS_ControllerPublic_Inline_Shopping', $action);
	}
    

	/**
	 * Method to build a link to the specified page/action with the provided
	 * data and params.
	 * @see XenForo_Route_BuilderInterface
	 */
	public function buildLink ($originalPrefix, $outputPrefix, $action, $extension, $data, array &$extraParams) {
        return XenForo_Link::buildBasicLinkWithIntegerParam($outputPrefix, $action, $extension, $data, 'shopping_id');
	}
}