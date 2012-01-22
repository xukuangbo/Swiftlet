<?php

class Swiftlet
{
	const
		version = '3.0'
		;

	protected
		$_action     = 'indexAction',
		$_args       = array(),
		$_controller,
		$_plugins    = array(),
		$_rootPath   = '/',
		$_singletons = array(),
		$_view
		;

	/**
	 * Initialize the application
	 */
	public function __construct()
	{
		set_error_handler(array($this, 'error'), E_ALL);

		// Determine the client-side path to root
		$path = dirname(dirname(__FILE__));

		if ( !empty($_SERVER['DOCUMENT_ROOT']) && preg_match('/^' . preg_quote($_SERVER['DOCUMENT_ROOT'], '/') . '/', $path) ) {
			$path = preg_replace('/^' . preg_quote($_SERVER['DOCUMENT_ROOT'], '/') . '/', '', $path);

			$this->_rootPath = rtrim($path, '/') . '/';
		}

		// Extract controller name, view name, action name and arguments from URL
		$controllerName = 'IndexController';
		$viewName       = 'index';

		if ( !empty($_GET['q']) ) {
			$this->_args = explode('/', $_GET['q']);

			if ( $this->_args ) {
				$viewName = array_shift($this->_args);

				$controllerName = ucfirst($viewName) . 'Controller';
			}

			if ( $this->_args ) $this->_action = array_shift($this->_args) . 'Action';
		}

		if ( !is_file('controllers/' . $controllerName . '.php') ) {
			$controllerName = 'Error404Controller';
			$viewName       = 'error404';
		}

		// Instantiate the view
		$this->_view = new SwiftletView($this, $viewName);

		// Instantiate the controller
		require('controllers/' . $controllerName . '.php');

		$this->_controller = new $controllerName($this, $controllerName);

		// Load plugins
		if ( $handle = opendir('plugins') ) {
			while ( ( $file = readdir($handle) ) !== FALSE ) {
				if ( is_file('plugins/' . $file) && preg_match('/^(.+Plugin)\.php$/', $file, $match) ) {
					$pluginName = $match[1];

					require('plugins/' . $file);

					$this->_plugins[] = new $pluginName($this, $pluginName);
				}
			}

			ksort($this->_plugins);

			closedir($handle);
		}

		// Call the controller action
		if ( !method_exists($this->_controller, $this->_action) ) {
			$this->_action = 'notImplementedAction';
		}

		$this->registerHook('actionBefore');

		$this->_controller->{$this->_action}();

		$this->registerHook('actionAfter');

		// Render the view
		$this->_view->render();
	}

	/**
	 * Get the action name
	 * @return string
	 */
	public function getAction()
   	{
		return $this->_action();
	}

	/**
	 * Get the arguments
	 * @return array
	 */
	public function getArgs()
   	{
		return $this->_args();
	}

	/**
	 * Get a model
	 * @param string $modelName
	 * @return object
	 */
	public function getModel($modelName)
   	{
		$modelName = ucfirst($modelName) . 'Model';

		if ( is_file($file = 'models/' . $modelName . '.php') ) {
			// Instantiate the model
			require($file);

			return new $modelName($this, $modelName);
		} else {
			throw new Exception($modelName . ' not found');
		}
	}

	/**
	 * Get a model singleton
	 * @param string $modelName
	 * @return object
	 */
	public function getSingleton($modelName)
	{
		if ( isset($this->_singletons[$modelName]) ) {
			return $this->_singletons[$modelName];
		}

		$model = $this->getModel($modelName);

		$this->_singletons[$modelName] = $model;

		return $model;
	}

	/**
	 * Get the controller instance
	 * @return object
	 */
	public function getController()
   	{
		return $this->_controller;
	}

	/**
	 * Get the view instance
	 * @return object
	 */
	public function getView()
   	{
		return $this->_view;
	}

	/**
	 * Get the client-side path to root
	 * @return string
	 */
	public function getRootPath()
	{
		return $this->_rootPath;
	}

	/**
	 * Register a new hook for plugins to implement
	 * @param string $hookName
	 * @param array $params
	 */
	public function registerHook($hookName, $params = array()) {
		$hookName .= 'Hook';

		foreach ( $this->_plugins as $plugin ) {
			if ( method_exists($plugin, $hookName) ) {
				$plugin->{$hookName}($params);
			}
		}
	}

	/**
	 * Error handler
	 * @param int $number
	 * @param string $string
	 * @param string $file
	 * @param int $line
	 */
	public function error($number, $string, $file, $line)
	{
		throw new Exception('Error #' . $number . ': ' . $string . ' in ' . $file . ' on line ' . $line);
	}
}
