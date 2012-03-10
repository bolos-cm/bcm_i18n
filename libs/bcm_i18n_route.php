<?php
/**
 * Copyright 2009-2010, Cake Development Corporation (http://cakedc.com)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2009-2010, Cake Development Corporation (http://cakedc.com)
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
App::import('Core', 'Router');

/**
 * i18n Route
 *
 * @package i18n
 * @subpackage i18n.libs
 */
class BcmI18nRoute extends CakeRoute {

/**
 * Internal flag to know whether default routes were mapped or not
 * 
 * @var boolean
 */	
	private static $__defaultsMapped = false;

/**
 * Class name - Workaround to be able to extend this class without breaking existing features
 *
 * @var string
 */
	public $name = __CLASS__;
	
/**
 * Constructor for a Route
 * Add a regex condition on the lang param to be sure it matches the available langs
 *
 * @param string $template Template string with parameter placeholders
 * @param array $defaults Array of defaults for the route.
 * @param string $params Array of parameters and additional options for the Route
 * @return void
 */
	public function __construct($template, $defaults = array(), $options = array()) {
		if (strpos($template, ':lang') === false && empty($options['disableAutoNamedLang'])) {
			$template = '/:lang' . $template;
		}

		$original = $template;

		// TODO JK: Translate to specific language not only DEFAULT_LANGUAGE
		$parts = explode('/', $template);
		if (isset($defaults['plugin']) && !empty($defaults['plugin']))
			$plugin = $defaults['plugin'];
		else
			$plugin = null;
		
		foreach($parts as $i => $part) {
			if (empty($part)) continue;
			if (substr($part, 0, 1) === ':') continue;
			if ($plugin === null)
				$parts[$i] = __($part, true);
			else
				$parts[$i] = __d($plugin, $part, true);
		}
		$templates = array(join('/', $parts), $original);

		foreach($templates as $template) {
			if (strpos($template, ':lang')) {

				if (defined('DEFAULT_LANGUAGE') && empty($options['disableDefaultConnect'])) {
					// Connects the default language without the :lang param
					Router::connect(
						str_replace('/:lang', '', $template),
						array_merge($defaults, array('lang' => DEFAULT_LANGUAGE)),
						array_merge($options, array('routeClass' => $this->name, 'disableAutoNamedLang' => true)));
				}
				$options = array_merge((array)$options, array(
					'lang' => join('|', Configure::read('Config.languages')),
				));

				$Router = Router::getInstance();
				$routesList = Configure::read('BcmI18nRoute.routes');
				$routesList[] = count($Router->routes);
				Configure::write('BcmI18nRoute.routes', $routesList);
			}
			unset($options['disableAutoNamedLang'], $options['disableDefaultConnect']);

			if ($template == '/:lang/') {
				$template = '/:lang';
			}
			if ($template !== $original) {
				Router::connect($template, $defaults, $options);
			}
		}
		
		if ($original == '/:lang/') {
			$original = '/:lang';
		}
		parent::__construct($original, $defaults, $options);
	}

/**
 * Attempt to match a url array.  If the url matches the route parameters + settings, then
 * return a generated string url.  If the url doesn't match the route parameters false will be returned.
 * This method handles the reverse routing or conversion of url arrays into string urls.
 *
 * @param array $url An array of parameters to check matching with.
 * @return mixed Either a string url for the parameters if they match or false.
 */
	public function match($url) {
		if (empty($url['lang'])) {
			$url['lang'] = Configure::read('Config.language');
		}
		return parent::match($url);
	}

/**
 * Checks to see if the given URL can be parsed by this route.
 * If the route can be parsed an array of parameters will be returned if not
 * false will be returned. String urls are parsed if they match a routes regular expression.
 *
 * @param string $url The url to attempt to parse.
 * @return mixed Boolean false on failure, otherwise an array or parameters
 * @access public
 */
	public function parse($url) {
		$params = parent::parse($url);

		if ($params !== false && array_key_exists('lang', $params)) {
			Configure::write('Config.language', $params['lang']);
		}

		return $params;
	}

/**
 * Connects the default, built-in routes, including prefix and plugin routes with the i18n custom Route
 * Code mostly duplicated from Router::__connectDefaultRoutes
 *
 * @see Router::__connectDefaultRoutes
 * @param array $pluginExceptions Plugins ommited from the lang default routing
 * @return void
 */
	public static function connectDefaultRoutes($pluginExceptions = array()) {
		if (!self::$__defaultsMapped) {
			Router::defaults(false);
			$options = array('routeClass' => __CLASS__);
			$prefixes = Router::prefixes();
			
			if ($plugins = App::objects('plugin')) {
				foreach ($plugins as $key => $value) {
					$plugins[$key] = Inflector::underscore($value);
				}
				$plugins = array_diff($plugins, $pluginExceptions);

				$pluginPattern = implode('|', $plugins);
				$match = array('plugin' => $pluginPattern) + $options;
				$shortParams = array('routeClass' => 'PluginShortBcmI18nRoute', 'plugin' => $pluginPattern);
				
				foreach ($prefixes as $prefix) {
					$params = array('prefix' => $prefix, $prefix => true);
					$indexParams = $params + array('action' => 'index');
					Router::connect("/{$prefix}/:plugin", $indexParams, $shortParams);
					Router::connect("/{$prefix}/:plugin/:controller", $indexParams, $match);
					Router::connect("/{$prefix}/:plugin/:controller/:action/*", $params, $match);
				}
				Router::connect('/:plugin', array('action' => 'index'), $shortParams);
				Router::connect('/:plugin/:controller', array('action' => 'index'), $match);
				Router::connect('/:plugin/:controller/:action/*', array(), $match);
			}
	
			foreach ($prefixes as $prefix) {
				$params = array('prefix' => $prefix, $prefix => true);
				$indexParams = $params + array('action' => 'index');
				Router::connect("/{$prefix}/:controller/:action/*", $params, $options);
				Router::connect("/{$prefix}/:controller", $indexParams, $options);
			}
			Router::connect('/:controller', array('action' => 'index'), $options);
			Router::connect('/:controller/:action/*', array(), $options);

			$Router = Router::getInstance();
			if ($Router->named['rules'] === false) {
				$Router->connectNamed(true);
			}

			self::$__defaultsMapped = true;
		}
	}

/**
 * Promote all the lang routes before their automatically created route for the default language
 *
 * @return void
 */
	public static function promoteLangRoutes() {
		$routesList = Configure::read('BcmI18nRoute.routes');
		if (!empty($routesList)) {
			$Router = Router::getInstance();
			$lastIndex = count($Router->routes) - 1;
			rsort($routesList);
			foreach($routesList as $langRouteIndex) {
				while ($langRouteIndex < $lastIndex) {
					Router::promote();
					$lastIndex--;
				}
				Router::promote(count($Router->routes) - 2);
				Router::promote();
				$lastIndex = $langRouteIndex - 2;
			}
			Configure::write('BcmI18nRoute.routes', array());
		}
	}

/**
 * Reset all the internal static variables.
 * Convenience method for using in tests
 *
 * @return void
 */
	public static function reload() {
		Configure::write('BcmI18nRoute.routes', array());
		self::$__defaultsMapped = false;
		Router::reload();
	}
}

/**
 * Plugin short route, that copies the plugin param to the controller parameters
 * It is used for supporting /:plugin routes.
 *
 * @package i18n
 * @subpackage i18n.libs
 * @see PluginShortRoute
 */
class PluginShortBcmI18nRoute extends BcmI18nRoute {
/**
* Class name - Workaround to be able to extend this class without breaking exixtent features
* 
* @var string
*/
	public $name = __CLASS__;

/**
 * Parses a string url into an array.  If a plugin key is found, it will be copied to the 
 * controller parameter
 *
 * @param string $url The url to parse
 * @return mixed false on failure, or an array of request parameters
 */
	public function parse($url) {
		$params = parent::parse($url);
		if (!$params) {
			return false;
		}
		$params['controller'] = $params['plugin'];
		return $params;
	}

/**
 * Reverse route plugin shortcut urls.  If the plugin and controller
 * are not the same the match is an auto fail.
 *
 * @param array $url Array of parameters to convert to a string.
 * @return mixed either false or a string url.
 */
	public function match($url) {
		if (isset($url['controller']) && isset($url['plugin']) && $url['plugin'] != $url['controller']) {
			return false;
		}
		$this->defaults['controller'] = $url['controller'];
		$result = parent::match($url);
		unset($this->defaults['controller']);
		return $result;
	}

}