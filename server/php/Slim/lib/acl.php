<?php
/**
 * Roles configurations
 *
 * PHP version 5
 *
 * @category   PHP
 * @package    Base
 * @subpackage Core
 */
class ACL
{
    public function __construct($scope)
    {
        $this->scope = $scope;
    }
    public function __invoke($request, $response, $next)
    {
        global $authUser;
        if (!empty($_GET['token'])) {
			// Checking provided access token is available/not expired
			if (((empty($authUser) || (!empty($authUser['role_id']) && $authUser['role_id'] != \Constants\ConstUserTypes::Admin)) && !in_array($authUser['scope'], explode(' ', $this->scope)))) {
                return renderWithJson(array(), 'Authorization Failed', '', 1, 401);
            } else {
                $response = $next($request, $response);
            }
        } else {
            return renderWithJson(array(), 'Authorization Failed', '', 1, 401);
        }
        return $response;
    }
}
