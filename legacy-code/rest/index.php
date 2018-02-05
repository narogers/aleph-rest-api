<?php

spl_autoload_register('apiAutoload');
function apiAutoload($classname)
{
    if (preg_match('/[a-zA-Z]+Controller$/', $classname)) {
        include 'controllers/' . $classname . '.php';
        return true;
    } elseif (preg_match('/[a-zA-Z]+Model$/', $classname)) {
        include 'models/' . $classname . '.php';
        return true;
    } elseif (preg_match('/[a-zA-Z]+View$/', $classname)) {
        include 'views/' . $classname . '.php';
        return true;
    } else {
        include 'library/' . str_replace('_', DIRECTORY_SEPARATOR, $classname) . '.php';
        return true;
    }
    return false;
}

$request = new Request();

// route the request to the right place
$controller_name = ucfirst($request->url_elements[1]) . 'Controller';

if (!class_exists($controller_name)) {
	$controller_name = "CMAController";
}

$controller = new $controller_name();
$action_name = strtolower($request->verb) . 'Action';
$result = $controller->$action_name($request);

$view_name = ucfirst($request->format) . 'View';
if(class_exists($view_name)) {
    $view = new $view_name();
    $view->render($result);
}
