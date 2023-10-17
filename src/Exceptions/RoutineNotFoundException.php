<?php
namespace Clicalmani\Routes\Exceptions;

class RoutineNotFoundException extends \Exception {
	function __construct($resource = ''){
		parent::__construct("Resource $resource Routine Not Found");
	}
}
