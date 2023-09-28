<?php
namespace Clicalmani\Routes\Exceptions;

class RouteNotFoundException extends \Exception {
	function __construct($route = ''){
		parent::__construct("Route $route Not Found");
	}
}
?>