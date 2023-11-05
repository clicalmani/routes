<?php
namespace Clicalmani\Routes\Exceptions;

use Clicalmani\Flesco\Http\Response\Response;
use Clicalmani\Routes\Route;

class RouteNotFoundException extends \Exception {
	function __construct($route = ''){
		parent::__construct("Route $route Not Found");

		if ( file_exists( root_path('/public/' . $_SERVER['REQUEST_URI']) ) ) {
			header('Location: /public/' . $_SERVER['REQUEST_URI']); exit;
		} else {
			if (Route::isApi()) response()->status(404, 'NOT_FOUND', 'Not Found');	
			else (new Response)->notFound();

			exit;
		}
	}
}
