<?php
namespace Clicalmani\Routes\Exceptions;

class RouteNotFoundException extends \Exception {
	function __construct($route = ''){
		parent::__construct("Route $route Not Found");

		if ( file_exists( root_path('/public/' . $_SERVER['REQUEST_URI']) ) ) {
			header('Location: /public/' . $_SERVER['REQUEST_URI']); exit;
		} else http_response_code(404);
	}
}
?>