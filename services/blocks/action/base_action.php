<?php
/**
 *
 * @package sitemaker
 * @copyright (c) 2013 Daniel A. (blitze)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace blitze\sitemaker\services\blocks\action;

use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class base_action implements action_interface
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var ContainerInterface */
	protected $phpbb_container;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \phpbb\user */
	protected $user;

	/** @var \blitze\sitemaker\services\blocks\blocks */
	protected $blocks;

	/** @var \blitze\sitemaker\services\blocks\factory */
	protected $block_factory;

	/** @var \blitze\sitemaker\model\mapper_factory */
	protected $mapper_factory;

	protected static $default_prefs = array(
		'hide_blocks'	=> false,
		'ex_positions'	=> array(),
	);

	/**
	 * Constructor
	 *
	 * @param \phpbb\config\config							$config					Config object
	 * @param ContainerInterface							$phpbb_container		Service container
	 * @param \phpbb\request\request_interface				$request				Request object
	 * @param \phpbb\user									$user					User object
	 * @param \blitze\sitemaker\services\blocks\blocks		$blocks					Blocks object
	 * @param \blitze\sitemaker\services\blocks\factory		$block_factory			Blocks factory object
	 * @param \blitze\sitemaker\model\mapper_factory		$mapper_factory			Mapper factory object
	 */
	public function __construct(\phpbb\config\config $config, ContainerInterface $phpbb_container, \phpbb\request\request_interface $request, \phpbb\user $user, \blitze\sitemaker\services\blocks\blocks $blocks, \blitze\sitemaker\services\blocks\factory $block_factory, \blitze\sitemaker\model\mapper_factory $mapper_factory)
	{
		$this->config = $config;
		$this->phpbb_container = $phpbb_container;
		$this->request = $request;
		$this->user = $user;
		$this->blocks = $blocks;
		$this->block_factory = $block_factory;
		$this->mapper_factory = $mapper_factory;
	}

	/**
	 * This is guaranteed to return a route entity. If the route does not exist, it create it
	 *
	 * @param array $route_data
	 * @param bool  $has_blocks
	 * @return \blitze\sitemaker\model\entity_interface
	 */
	protected function force_get_route(array $route_data, $has_blocks = false)
	{
		$route_mapper = $this->mapper_factory->create('blocks', 'routes');

		if (($route = $route_mapper->load($this->get_condition($route_data))) === null)
		{
			$route_data['ext_name'] = $this->request->variable('ext', '');
			$route_data['has_blocks'] = $has_blocks;

			$entity = $route_mapper->create_entity($route_data);
			$route = $route_mapper->save($entity);
		}

		return $route;
	}

	/**
	 * @param array $info
	 * @return array
	 */
	protected function get_condition(array $info)
	{
		return array(
			array('route', '=', $info['route']),
			array('style', '=', $info['style']),
		);
	}

	/**
	 * @param \blitze\sitemaker\model\entity_interface $entity
	 * @return array
	 */
	protected function render_block(\blitze\sitemaker\model\entity_interface $entity)
	{
		/** @type \blitze\sitemaker\model\blocks\entity\block $entity */
		$block_name = $entity->get_name();
		if ($block_instance = $this->block_factory->get_block($block_name))
		{
			$default_settings = $block_instance->get_config(array());
			$settings = $this->blocks->sync_settings($default_settings, $entity->get_settings());
			$entity->set_settings($settings);

			$block_data = $entity->to_array();
			$disp_data = $block_instance->display($block_data, true);

			return array_merge($block_data, array(
				'id'		=> $block_data['bid'],
				'title'		=> (!empty($block_data['title'])) ? $block_data['title'] : $this->user->lang($disp_data['title']),
				'content'	=> (!empty($disp_data['content'])) ? $disp_data['content'] : $this->user->lang('BLOCK_NO_DATA'),
			));
		}

		return array();
	}

	/**
	 * @param array $route_prefs
	 * @return bool
	 */
	protected function route_is_customized(array $route_prefs)
	{
		$route_prefs = array_intersect_key($route_prefs, self::$default_prefs);
		return (self::$default_prefs !== $route_prefs) ? true : false;
	}
}
