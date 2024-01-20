<?php
namespace Clicalmani\Routes\Exceptions;

use Clicalmani\Flesco\Http\Response\Response;
use Clicalmani\Routes\Route;

/**
 * Class RouteNotFoundException
 * 
 * @package Clicalmani\Route
 * @author @clicalmani
 */
class RouteNotFoundException extends \Exception 
{
	public function __construct(?string $route = ''){
		parent::__construct("Route $route Not Found");

		/**
		 * Render asset
		 */
		if ( file_exists( root_path('/public/' . $_SERVER['REQUEST_URI']) ) ) {
			header('Location: /public/' . $_SERVER['REQUEST_URI']); exit;
		} 
		
		/**
		 * Render response
		 */
		else {
			if (Route::isApi()) response()->status(404, 'NOT_FOUND', 'Not Found');	
			else (new Response)->notFound();

			exit;
		}
	}
}
