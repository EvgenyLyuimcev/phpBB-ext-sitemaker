<?php
/**
 *
 * @package primetime
 * @copyright (c) 2013 Daniel A. (blitze)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace primetime\primetime\core\blocks;

use Symfony\Component\DependencyInjection\Container;

class display
{
	/** @var \phpbb\auth\auth */
	protected $auth;

	/** @var \phpbb\cache\service */
	protected $cache;

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver */
	protected $db;

	/** @var Container */
	protected $phpbb_container;

	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\user */
	protected $user;

	/** @var \primetime\primetime\core\primetime */
	protected $primetime;

	/** @var \primetime\primetime\core\template */
	protected $ptemplate;

	/** @var string */
	private $blocks_table;

	/** @var string */
	private $blocks_config_table;

	/** @var string */
	private $block_routes_table;

	/** @var string */
	private $default_route;

	/** @var string */
	public $route;

	/**
	 * Constructor
	 *
	 * @param \phpbb\auth\auth							$auth					Auth object
	 * @param \phpbb\cache\service						$cache					Cache object
	 * @param \phpbb\config\config						$config					Config object
	 * @param \phpbb\db\driver\factory					$db						Database object
	 * @param Container									$phpbb_container		Service container
	 * @param \phpbb\request\request_interface			$request				Request object
	 * @param \phpbb\template\template					$template				Template object
	 * @param \phpbb\user								$user					User object
	 * @param \primetime\primetime\core\primetime		$primetime				Primetime object
	 * @param \primetime\primetime\core\template		$ptemplate				Primetime template object
	 * @param string									$blocks_table			Name of the blocks database table
	 * @param string									$blocks_config_table	Name of the blocks_config database table
	 * @param string									$block_routes_table		Name of the block_routes database table
	 */
	public function __construct(\phpbb\auth\auth $auth, \phpbb\cache\driver\driver_interface $cache, \phpbb\config\config $config, \phpbb\db\driver\factory $db, Container $phpbb_container, \phpbb\request\request_interface $request, \phpbb\template\template $template, \phpbb\user $user, \primetime\primetime\core\primetime $primetime, \primetime\primetime\core\template $ptemplate, $blocks_table, $blocks_config_table, $block_routes_table)
	{
		$this->auth = $auth;
		$this->cache = $cache;
		$this->config = $config;
		$this->db = $db;
		$this->phpbb_container = $phpbb_container;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->primetime = $primetime;
		$this->ptemplate = $ptemplate;
		$this->blocks_table = $blocks_table;
		$this->blocks_config_table = $blocks_config_table;
		$this->block_routes_table = $block_routes_table;
	}

	public function show()
	{
		$offlimits = array('ucp.php', 'mcp.php');
		if ($this->user->page['page_dir'] == 'adm' || in_array($this->user->page['page_name'], $offlimits) || (strpos($this->user->page['page_name'], 'memberlist') !== false && $this->request->is_set('mode')))
		{
			return;
		}

		$asset_path = $this->primetime->asset_path;
		$this->primetime->add_assets(array(
			'css'   => array(
				$asset_path . 'ext/primetime/primetime/assets/font-awesome/css/font-awesome.min.css',
			)
		));

		$edit_mode = false;
		$this->default_route = $this->config['primetime_default_layout'];
		$route = $this->get_route();
		$style_id = $this->get_style_id();

		if ($this->auth->acl_get('a_manage_blocks'))
		{
			$manager = $this->phpbb_container->get('primetime.blocks.manager');
			$edit_mode = $manager->handle($route, $style_id);
		}

		$route_info = $this->get_route_info($route, $style_id);
		$blocks = $this->get_blocks($route, $style_id, $edit_mode);

		if (empty($route_info))
		{
			$route_info = array(
				'hide_blocks'	=> false,
				'ex_positions'	=> '',
			);
		}

		if (!sizeof($blocks) && !$route_info['hide_blocks'] && $edit_mode === false)
		{
			$blocks = $this->get_blocks($this->default_route, $style_id, false);
		}

		// remove unwanted positions for this route
		if ($route_info['ex_positions'])
		{
			$blocks = array_diff_key($blocks, array_flip(explode(',', $route_info['ex_positions'])));
		}

		$users_groups = $this->get_users_groups();

		$blocks_per_position = array();
		foreach ($blocks as $position => $blocks_ary)
		{
			$pos_count_key = 's_' . $position . '_count';
			$blocks_per_position[$pos_count_key] = 0;

			$blocks_ary = array_values($blocks_ary);
			for ($i = 0, $size = sizeof($blocks_ary); $i < $size; $i++)
			{
				$row = $blocks_ary[$i];
				$allowed_groups = explode(',', $row['permission']);
				$block_service = $row['name'];

				if ($this->phpbb_container->has($block_service) && (!$row['permission'] || sizeof(array_intersect($allowed_groups, $user_groups))))
				{
					$b = $this->phpbb_container->get($block_service);
					$b->set_template($this->ptemplate);
					$block = $b->display($row, $edit_mode);

					if (empty($block['content']))
					{
						continue;
					}

					$data = array_merge($row, array(
							'TITLE'		=> ($row['title']) ? $row['title'] : ((isset($this->user->lang[$block['title']])) ? $this->user->lang[$block['title']] : $block['title']),
							'CONTENT'	=> $block['content'],
						)
					);
					$this->template->assign_block_vars($position, array_change_key_case($data, CASE_UPPER));
					$blocks_per_position[$pos_count_key]++;
				}
			}
		}

		$this->template->assign_vars(array_merge(array(
				'S_PRIMETIME'		=> true,
				'S_BLOCKS_ADMIN'	=> true,
				'L_INDEX'			=> $this->user->lang['HOME'],
			),
			array_change_key_case($blocks_per_position, CASE_UPPER))
		);
	}

	public function get_style_id()
	{
		$style_id = 0;
		if ($this->request->is_set('style'))
		{
			$style_id = request_var('style', 0);
		}
		else
		{
			$style_id = (!$this->config['override_user_style']) ? $this->user->data['user_style'] : $this->config['default_style'];
		}

		return $style_id;
	}

	public function get_route()
	{
		$this->route = $this->user->page['page_name'];
		$controller_service = explode(':', $this->phpbb_container->get('symfony_request')->attributes->get('_controller'));

		if (!empty($controller_service[0]) && $this->phpbb_container->has($controller_service[0]))
		{
			$controller = $this->phpbb_container->get($controller_service[0]);

			/**
			 * Let controller optionally specify route
			 */
			if (method_exists($controller, 'get_blocks_route'))
			{
				$this->route = $controller->get_blocks_route();
			}
		}

		return $this->route;
	}

	public function get_route_info($route, $style_id)
	{
		if (($routes = $this->cache->get('primetime_block_routes')) === false)
		{
			$sql = 'SELECT *
				FROM ' . $this->block_routes_table;
			$result = $this->db->sql_query($sql);

			$routes = array();
			while ($row = $this->db->sql_fetchrow($result))
			{
				$routes[$row['style']][$row['route']] = $row;
			}
			$this->db->sql_freeresult($result);

			$this->cache->put('primetime_block_routes', $routes);
		}

		return (isset($routes[$style_id][$route])) ? $routes[$style_id][$route] : (($this->default_route && isset($routes[$style_id][$this->default_route])) ? $routes[$style_id][$this->default_route] : array());
	}

	public function clear_blocks_cache()
	{
		$this->cache->destroy('primetime_blocks');
	}

	public function get_blocks($route, $style_id, $edit_mode)
	{
		if (($blocks = $this->cache->get('primetime_blocks')) === false)
		{
			$sql_array = array(
				'SELECT'	=> 'b.*, r.*',

				'FROM'	  => array(
					$this->blocks_table			=> 'b',
					$this->block_routes_table	=> 'r',
				),

				'WHERE'	 => 'b.route_id = r.route_id' .
					((!$edit_mode) ? ' AND b.status = 1' : ''),

				'ORDER_BY'  => 'b.style, b.position, b.weight ASC',
			);

			$sql = $this->db->sql_build_query('SELECT', $sql_array);
			$result = $this->db->sql_query($sql);

			$blocks = $block_ids = $block_pos = array();
			while ($row = $this->db->sql_fetchrow($result))
			{
				$block_ids[] = $row['bid'];
				$block_pos[$row['style']][$row['route']][$row['bid']] = $row['position'];
				$blocks[$row['style']][$row['route']][$row['position']][$row['bid']] = $row;
				$blocks[$row['style']][$row['route']][$row['position']][$row['bid']]['settings'] = array();
			}
			$this->db->sql_freeresult($result);

			$db_settings = $this->get_blocks_config($block_ids);

			foreach ($block_pos as $style_id => $routes)
			{
				foreach($routes as $route => $positions)
				{
					foreach ($positions as $bid => $position)
					{
						$block_service = $blocks[$style_id][$route][$position][$bid]['name'];
						$block_config = (isset($db_settings[$bid])) ? $db_settings[$bid] : array();

						if ($this->phpbb_container->has($block_service) === false)
						{
							continue;
						}

						$b = $this->phpbb_container->get($block_service);
						$df_settings = $b->get_config($block_config);

						if (sizeof($df_settings))
						{
							foreach ($df_settings as $key => $settings)
							{
								if (!is_array($settings))
								{
									continue;
								}

								$type = explode(':', $settings['type']);
								$db_settings[$bid][$key] = (isset($db_settings[$bid][$key])) ? $db_settings[$bid][$key] : $settings['default'];

								if ($db_settings[$bid][$key] && ($type[0] == 'checkbox' || $type[0] == 'multi_select'))
								{
									$db_settings[$bid][$key] = explode(',', $db_settings[$bid][$key]);
								}
								$blocks[$style_id][$route][$position][$bid]['settings'][$key] = $db_settings[$bid][$key];
							}
						}
					}
				}
			}

			$this->cache->put('primetime_blocks', $blocks);
		}

		return isset($blocks[$style_id][$route]) ? $blocks[$style_id][$route] : array();
	}

	public function get_blocks_config($bids)
	{
		if (!sizeof($bids))
		{
			return array();
		}

		$sql = 'SELECT bid, bvar, bval
            FROM ' . $this->blocks_config_table . '
            WHERE ' . $this->db->sql_in_set('bid', $bids);
		$result = $this->db->sql_query($sql);

		$data = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$data[$row['bid']][$row['bvar']] = $row['bval'];
		}
		$this->db->sql_freeresult($result);

		return $data;
	}

	public function get_users_groups()
	{
		$sql = 'SELECT group_id
            FROM ' . USER_GROUP_TABLE . '
            WHERE user_id = ' . (int) $this->user->data['user_id'];
		$result = $this->db->sql_query($sql);

		$data = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$data[$row['group_id']] = $row['group_id'];
		}
		$this->db->sql_freeresult($result);

		return $data;
	}
}
