<?php
/**
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package    Fuel
 * @version    1.0
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2011 Fuel Development Team
 * @link       http://fuelphp.com
 */

namespace Fuel\Core;

/**
 * Uri Class
 *
 * @package		Fuel
 * @category	Core
 * @author		Dan Horrigan
 * @link		http://fuelphp.com/docs/classes/uri.html
 */
class Uri {

	protected static $detected_uri = null;

	public static function detect()
	{
		if (static::$detected_uri !== null)
		{
			return static::$detected_uri;
		}

		if (\Fuel::$is_cli)
		{
			if ($uri = \Cli::option('uri') !== null)
			{
				static::$detected_uri = $uri;
			}
			else
			{
				static::$detected_uri = \Cli::option(1);
			}

			return static::$detected_uri;
		}

		// We want to use PATH_INFO if we can.
		if ( ! empty($_SERVER['PATH_INFO']))
		{
			$uri = $_SERVER['PATH_INFO'];
		}
		// Only use ORIG_PATH_INFO if it contains the path
		elseif ( ! empty($_SERVER['ORIG_PATH_INFO']) and ($path = str_replace($_SERVER['SCRIPT_NAME'], '', $_SERVER['ORIG_PATH_INFO'])) != '')
		{
			$uri = $path;
		}
		else
		{
			// Fall back to parsing the REQUEST URI
			if (isset($_SERVER['REQUEST_URI']))
			{
				// Some servers require 'index.php?' as the index page
				// if we are using mod_rewrite or the server does not require
				// the question mark, then parse the url.
				if (\Config::get('index_file') != 'index.php?')
				{
					$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
				}
				else
				{
					$uri = $_SERVER['REQUEST_URI'];
				}
			}
			else
			{
				throw new \Fuel_Exception('Unable to detect the URI.');
			}

			// Remove the base URL from the URI
			$base_url = parse_url(\Config::get('base_url'), PHP_URL_PATH);
			if ($uri != '' and strncmp($uri, $base_url, strlen($base_url)) === 0)
			{
				$uri = substr($uri, strlen($base_url));
			}

			// If we are using an index file (not mod_rewrite) then remove it
			$index_file = \Config::get('index_file');
			if ($index_file and strncmp($uri, $index_file, strlen($index_file)) === 0)
			{
				$uri = substr($uri, strlen($index_file));
			}

			// Lets split the URI up in case it containes a ?.  This would
			// indecate the server requires 'index.php?' and that mod_rewrite
			// is not being used.
			preg_match('#(.*?)\?(.*)#i', $uri, $matches);

			// If there are matches then lets set set everything correctly
			if ( ! empty($matches))
			{
				$uri = $matches[1];
				$_SERVER['QUERY_STRING'] = $matches[2];
				parse_str($matches[2], $_GET);
			}
		}

		// Strip the defined url suffix from the uri if needed
		$ext = \Config::get('url_suffix');
		strrchr($uri, '.') === $ext and $uri = substr($uri,0,-strlen($ext));

		// Do some final clean up of the uri
		static::$detected_uri = str_replace(array('//', '../'), '/', $uri);

		return static::$detected_uri;
	}

	/**
	 * Returns the desired segment, or false if it does not exist.
	 *
	 * @access	public
	 * @param	int		The segment number
	 * @return	string
	 */
	public static function segment($segment, $default = null)
	{
		return \Request::active()->uri->get_segment($segment, $default);
	}

	/**
	 * Returns all segments in an array
	 *
	 * @return	array
	 */
	public static function segments()
	{
		return \Request::active()->uri->get_segments();
	}

	/**
	 * Converts the current URI segments to an associative array.  If
	 * the URI has an odd number of segments, null will be returned.
	 *
	 * @return  array|null  the array or null
	 */
	public static function to_assoc()
	{
		return \Arr::to_assoc(static::segments());
	}

	/**
	 * Returns the full uri as a string
	 *
	 * @return	string
	 */
	public static function string()
	{
		return \Request::active()->uri->get();
	}

	/**
	 * Creates a url with the given uri, including the base url
	 *
	 * @param	string	the url
	 * @param	array	some variables for the url
	 */
	public static function create($uri = null, $variables = array(), $get_variables = array())
	{
		$url = '';

		if(!preg_match("/^(http|https|ftp):\/\//i", $uri))
		{
			$url .= \Config::get('base_url');

			if (\Config::get('index_file'))
			{
				$url .= \Config::get('index_file').'/';
			}
		}

		$url = $url.ltrim(is_null($uri) ? static::string() : $uri, '/');

		substr($url, -1) != '/' and $url .= \Config::get('url_suffix');

		if ( ! empty($get_variables))
		{
			$char = strpos($url, '?') === false ? '?' : '&';
			foreach ($get_variables as $key => $val)
			{
				$url .= $char.$key.'='.$val;
				$char = '&';
			}
		}

		foreach($variables as $key => $val)
		{
			$url = str_replace(':'.$key, $val, $url);
		}

		return $url;
	}

	/**
	 * Gets the current URL, including the BASE_URL
	 *
	 * @param	string	the url
	 */
	public static function main()
	{
		return static::create(\Request::main()->uri->uri);
	}

	/**
	 * Gets the current URL, including the BASE_URL
	 *
	 * @param	string	the url
	 */
	public static function current()
	{
		return static::create();
	}

	/**
	 * Gets the base URL, including the index_file
	 *
	 * @return  the base uri
	 */
	public static function base($include_index = true)
	{
		$url = \Config::get('base_url');

		if ($include_index and \Config::get('index_file'))
		{
			$url .= \Config::get('index_file').'/';
		}

		return $url;
	}


	/**
	 * @var	string	The URI string
	 */
	public $uri = '';

	/**
	 * @var	array	The URI segements
	 */
	public $segments = '';

	/**
	 * Contruct takes a URI or detects it if none is given and generates
	 * the segments.
	 *
	 * @access	public
	 * @param	string	The URI
	 * @return	void
	 */
	public function __construct($uri = NULL)
	{
		if ($uri === NULL)
		{
			$uri = static::detect();
		}
		$this->uri = \Security::clean_uri(trim($uri, '/'));
		$this->segments = explode('/', $this->uri);
	}

	public function get()
	{
		return $this->uri;
	}

	public function get_segments()
	{
		return $this->segments;
	}

	public function get_segment($segment, $default = null)
	{
		if (isset($this->segments[$segment - 1]))
		{
			return $this->segments[$segment - 1];
		}

		return $default;
	}

	public function __toString()
	{
		return $this->get();
	}
}


