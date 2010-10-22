<?php
/**
 * @package Swiftlet
 * @copyright 2009 ElbertF http://elbertf.com
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU Public License
 */

if ( !isset($this) ) die('Direct access to this file is not allowed');

class Cache_Plugin extends Plugin
{
	public
		$version    = '1.0.0',
		$compatible = array('from' => '1.3.0', 'to' => '1.3.*'),
		$hooks      = array('cache' => 1, 'clear_cache' => 1, 'init' => 999)
		;

	private
		$cacheLifeTime = 3600
		;

	/*
	 * Implement init hook
	 */
	function init()
	{
		$this->ready = TRUE;
	}

	/*
	 * Implement cache hook
	 * @param array $params
	 */
	function cache(&$params)
	{
		if ( !empty($app->cache->ready) )
		{
			$app->cache->write($params['contents']);
		}
	}

	/**
	 * Read a file from cache
	 * @return bool
	 */
	private function read()
	{
		if ( !empty($this->app->session->ready) && !empty($this->app->user->ready) && $this->app->session->get('user id') != User_Plugin::GUEST_ID )
		{
			return;
		}

		if ( $this->app->caching && empty($this->app->input->POST_raw) && empty($_POST) )
		{
			if ( $handle = opendir($controller->rootPath . 'cache') )
			{
				while ( $filename = readdir($handle) )
				{
					if ( is_file($this->controller->rootPath . 'cache/' . $filename) )
					{
						list($time, $hash) = explode('_', $filename);

						if ( $time <= time() )
						{
							@unlink($this->controller->rootPath . 'cache/' . $filename);
						}
						else
						{
							if ( $hash == sha1($_SERVER['REQUEST_URI']) )
							{
								if ( $this->app->debugMode )
								{
									header('X-Swiftlet-Cache: HIT');
								}

								echo file_get_contents($this->controller->rootPath . 'cache/' . $filename);

								$this->app->buffer->flush();

								exit;
							}
						}
					}
				}

				closedir($handle);
			}
		}
	}

	/**
	 * Write a file to cache
	 * @param string $contents
	 */
	function write(&$contents)
	{
		if ( !empty($this->app->session->ready) && !empty($this->app->user->ready) && $this->app->session->get('user id') != User::GUEST_ID )
		{
			return;
		}

		if ( $headers = headers_list() )
		{
			foreach ( $headers as $header )
			{
				if ( preg_match('/^Content\-type:/i', $header) )
				{
					return;
				}
			}
		}

		if ( $this->app->caching && empty($this->app->input->POST_raw) )
		{
			if ( !is_dir($controller->rootPath . 'cache') )
			{
				$this->app->error(FALSE, 'Directory "/cache" does not exist.', __FILE__, __LINE__);
			}

			if ( !is_writable($controller->rootPath . 'cache') )
			{
				$this->app->error(FALSE, 'Directory "/cache" is not writable.', __FILE__, __LINE__);
			}

			$filename = ( time() + $this->cacheLifeTime ) . '_' . sha1($_SERVER['REQUEST_URI']);

			if ( !$handle = fopen($controller->rootPath . 'cache/' . $filename, 'a+') )
			{
				$this->app->error(FALSE, 'Could not open file "/cache/' . $filename . '".', __FILE__, __LINE__);
			}

			if ( fwrite($handle, $contents) === FALSE )
			{
				$this->app->error(FALSE, 'Could not write to file "/cache/' . $filename . '".', __FILE__, __LINE__);
			}

			fclose($handle);
		}

		unset($contents);
	}

	/**
	 * Clear cache
	 */
	function clear_cache()
	{
		if ( $handle = opendir('cache') )
		{
			while ( $filename = readdir($handle) )
			{
				if ( is_file('cache/' . $filename) )
				{
					$r = @unlink('cache/' . $filename);

					if ( !$r )
					{
						$this->app->error(FALSE, 'Could not deleted cached file "/cache/' . $filename . '", please check permissions or the delete the file manually.', __FILE__, __LINE__);
					}
				}
			}

			closedir($handle);
		}
	}
}
