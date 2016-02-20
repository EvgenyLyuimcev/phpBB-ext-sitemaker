<?php
/**
 *
 * @package sitemaker
 * @copyright (c) 2015 Daniel A. (blitze)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace blitze\sitemaker\tests\services;

use blitze\sitemaker\services\template;

class template_test extends \phpbb_test_case
{
	/**
	 * Define the extension to be tested.
	 *
	 * @return string[]
	 */
	protected static function setup_extensions()
	{
		return array('blitze/sitemaker');
	}

	/**
	 * Create the members service
	 *
	 * @return \blitze\sitemaker\services\members
	 */
	public function get_service()
	{
		global $phpbb_root_path, $phpEx;

		$config = new \phpbb\config\config(array());
		$user = new \phpbb\user('\phpbb\datetime');
		$filesystem = new \phpbb\filesystem\filesystem();
		$context = $this->getMockBuilder('\phpbb\template\context')
			->disableOriginalConstructor()
			->getMock();
		$context->expects($this->any())
			->method('get_data_ref')
			->willReturn(array(
				'.' => array(
					array('SOME_VAR' => 'some string')
				)
			));
		$context->expects($this->once())
			->method('clear');

		$path_helper =  new \phpbb\path_helper(
			new \phpbb\symfony_request(
				new \phpbb_mock_request()
			),
			new \phpbb\filesystem(),
			$this->getMock('\phpbb\request\request_interface'),
			$phpbb_root_path,
			$phpEx
		);

		$container = new phpbb_mock_container_builder();
		$cache_path = $phpbb_root_path . 'cache/twig';
		$loader = new \phpbb\template\twig\loader(new \phpbb\filesystem\filesystem(), '');
		$twig = new \phpbb\template\twig\environment(
			$config,
			$filesystem,
			$path_helper,
			$container,
			$cache_path,
			null,
			$loader,
			array(
				'cache'			=> false,
				'debug'			=> false,
				'auto_reload'	=> true,
				'autoescape'	=> false,
			)
		);

		$phpbb_extension_manager = new \phpbb_mock_extension_manager($phpbb_root_path, array());

		return $this->getMockBuilder('\blitze\sitemaker\services\template')
			->setConstructorArgs(array($path_helper, $config, $context, $twig, $cache_path, $user, $phpbb_extension_manager))
			->setMethods(array('set_style', 'set_filenames', 'assign_display'))
			->getMock();
	}

	public function test_clear()
	{
		$template = $this->get_service();
		$template->clear();
	}

	public function test_render_view()
	{
		$template = $this->get_service();

		$template->expects($this->once())
			->method('set_style')
			->with(array('ext/foo/bar/styles', 'styles'));
		$template->expects($this->once())
			->method('set_filenames')
			->with(array('some_handle' => 'some.html'));
		$template->expects($this->once())
			->method('assign_display')
			->with('some_handle');

		$template->render_view('foo/bar', 'some.html', 'some_handle');
	}
}
