<?php
/**
 *
 * @package sitemaker
 * @copyright (c) 2015 Daniel A. (blitze)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace blitze\sitemaker\services\blocks;

use blitze\sitemaker\services\blocks\cfg_fields;

class cfg_handler extends cfg_fields
{
	/** @var \phpbb\request\request_interface */
	protected $request;

	/** @var \phpbb\template\template */
	protected $template;

	/** @var \phpbb\language\language */
	protected $translator;

	/** @var \blitze\sitemaker\services\groups */
	protected $groups;

	/** @var string phpBB root path */
	protected $phpbb_root_path;

	/** @var string phpEx */
	protected $php_ext;

	/**
	 * Constructor
	 *
	 * @param \phpbb\request\request_interface		$request				Request object
	 * @param \phpbb\template\template				$template				Template object
	 * @param \phpbb\language\language				$translator				Language object
	 * @param \blitze\sitemaker\services\groups		$groups					Groups object
	 * @param string								$phpbb_root_path		phpBB root path
	 * @param string								$php_ext				phpEx
	 */
	public function __construct(\phpbb\request\request_interface $request, \phpbb\template\template $template, \phpbb\language\language $translator, \blitze\sitemaker\services\groups $groups, $phpbb_root_path, $php_ext)
	{
		parent::__construct($translator);

		$this->request = $request;
		$this->template = $template;
		$this->translator = $translator;
		$this->groups = $groups;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	/**
	 * @param array $block_data
	 * @param array $default_settings
	 * @return template|string
	 */
	public function get_edit_form(array $block_data, array $default_settings)
	{
		global $module;

		if (!function_exists('build_cfg_template'))
		{
			include($this->phpbb_root_path . 'includes/functions_acp.' . $this->php_ext); // @codeCoverageIgnore
		}

		// We fake this class as it is needed by the build_cfg_template function
		$module = new \stdClass();
		$module->module = $this;

		$this->_generate_config_fields($block_data['settings'], $default_settings);

		return $this->_get_form($block_data);
	}

	/**
	 * @param array $default_settings
	 * @return array|void
	 */
	public function get_submitted_settings(array $default_settings)
	{
		// @codeCoverageIgnoreStart
		if (!function_exists('validate_config_vars'))
		{
			include($this->phpbb_root_path . 'includes/functions_acp.' . $this->php_ext);
		}
		// @codeCoverageIgnoreEnd

		$cfg_array = utf8_normalize_nfc($this->request->variable('config', array('' => ''), true));

		$errors = array();
		validate_config_vars($default_settings, $cfg_array, $errors);

		if (sizeof($errors))
		{
			return array('errors' => join("\n", $errors));
		}

		$this->_get_multi_select($cfg_array, $default_settings);

		return array_intersect_key($cfg_array, $default_settings);
	}

	/**
	 * Get the html form
	 *
	 * @param array $block_data
	 * @return string
	 */
	private function _get_form(array $block_data)
	{
		$selected_groups = $this->_ensure_array($block_data['permission']);

		$this->template->assign_vars(array(
			'S_ACTIVE'		=> $block_data['status'],
			'S_TYPE'		=> $block_data['type'],
			'S_NO_WRAP'		=> $block_data['no_wrap'],
			'S_HIDE_TITLE'	=> $block_data['hide_title'],
			'S_BLOCK_CLASS'	=> trim($block_data['class']),
			'S_GROUP_OPS'	=> $this->groups->get_options('special', $selected_groups),
		));

		$this->template->set_filenames(array(
			'block_settings' => 'block_settings.html',
		));

		return $this->template->assign_display('block_settings');
	}

	/**
	 * Generate block configuration fields
	 *
	 * @param array $db_settings
	 * @param array $default_settings
	 */
	private function _generate_config_fields(array &$db_settings, array $default_settings)
	{
		foreach ($default_settings as $field => $vars)
		{
			if ($this->_sets_legend($field, $vars) || !is_array($vars))
			{
				continue;
			}

			$db_settings[$field] = $this->_get_field_value($field, $vars['default'], $db_settings);
			$content = $this->_get_field_template($field, $db_settings, $vars);

			$this->template->assign_block_vars('options', array(
				'KEY'			=> $field,
				'TITLE'			=> $this->translator->lang($vars['lang']),
				'S_EXPLAIN'		=> $vars['explain'],
				'TITLE_EXPLAIN'	=> $vars['lang_explain'],
				'CONTENT'		=> $content,
			));
			unset($default_settings[$field]);
		}
	}

	/**
	 * @param $field
	 * @param array $db_settings
	 * @param array $vars
	 * @return string
	 */
	private function _get_field_template($field, array &$db_settings, array &$vars)
	{
		$vars['lang_explain'] = $this->_explain_field($vars);
		$vars['append'] = $this->_append_field($vars);

		$type = explode(':', $vars['type']);
		$method = '_prep_' . $type[0] . '_field_for_display';

		if (is_callable(array($this, $method)))
		{
			$this->_set_params($field, $vars, $db_settings);
			$this->$method($vars, $type, $field, $db_settings);
		}

		return build_cfg_template($type, $field, $db_settings, $field, $vars);
	}

	/**
	 * @param string $field
	 * @param string|array $vars
	 * @return boolean
	 */
	private function _sets_legend($field, $vars)
	{
		if (strpos($field, 'legend') !== false)
		{
			$this->template->assign_block_vars('options', array(
				'S_LEGEND'	=> $field,
				'LEGEND'	=> $this->translator->lang($vars)
			));

			return true;
		}

		return false;
	}

	/**
	 * @param array $vars
	 * @return mixed|string
	 */
	private function _explain_field(array $vars)
	{
		$l_explain = '';
		if (!empty($vars['explain']))
		{
			$l_explain = (isset($vars['lang_explain'])) ? $this->translator->lang($vars['lang_explain']) : $this->translator->lang($vars['lang'] . '_EXPLAIN');
		}

		return $l_explain;
	}

	/**
	 * @param array $vars
	 * @return mixed|string
	 */
	private function _append_field(array $vars)
	{
		$append = '';
		if (!empty($vars['append']))
		{
			$append = $this->translator->lang($vars['append']);
		}

		return $append;
	}

	/**
	 * @param string $field
	 * @param array $vars
	 * @param array $settings
	 */
	private function _set_params($field, array &$vars, array $settings)
	{
		if (isset($vars['options']))
		{
			$vars['params'][] = $vars['options'];
			$vars['params'][] = $settings[$field];
		}
	}

	/**
	 * @param string $field
	 * @param mixed $default
	 * @param array $db_settings
	 * @return mixed
	 */
	private function _get_field_value($field, $default, array $db_settings)
	{
		return (!empty($db_settings[$field])) ? $db_settings[$field] : $default;
	}

	/**
	 * @param array $vars
	 */
	private function _prep_select_field_for_display(array &$vars)
	{
		$this->_add_lang_vars($vars['params'][0]);

		$vars['function'] = (!empty($vars['function'])) ? $vars['function'] : 'build_select';
	}

	/**
	 * @param array $vars
	 * @param array $type
	 * @param string $field
	 */
	private function _prep_checkbox_field_for_display(array &$vars, array &$type, $field)
	{
		$this->_add_lang_vars($vars['params'][0]);

		$vars['method'] = 'build_checkbox';
		$vars['params'][] = $field;
		$type[0] = 'custom';
	}

	/**
	 * @param array $vars
	 * @param array $type
	 * @param string $field
	 */
	private function _prep_radio_field_for_display(array &$vars, array &$type, $field)
	{
		if (!isset($type[1]))
		{
			$this->_add_lang_vars($vars['params'][0]);

			$vars['method'] = 'build_radio';
			$vars['params'][] = $field;
			$type[0] = 'custom';
		}
	}

	/**
	 * @param array $vars
	 * @param array $type
	 * @param string $field
	 */
	private function _prep_multi_select_field_for_display(array &$vars, array &$type, $field)
	{
		$this->_prep_checkbox_field_for_display($vars, $type, $field);

		$vars['method'] ='build_multi_select';
	}

	/**
	 * @param array $vars
	 * @param array $type
	 */
	private function _prep_hidden_field_for_display(array &$vars, array &$type)
	{
		$vars['method'] = 'build_hidden';
		$vars['explain'] = '';
		$vars['lang'] = '';
		$type[0] = 'custom';
	}

	/**
	 * @param array $vars
	 * @param array $type
	 */
	private function _prep_custom_field_for_display(array &$vars, array &$type)
	{
		$vars['function'] = (!empty($vars['function'])) ? $vars['function'] : '';
		$type[0] = 'custom';
	}

	/**
	 * this looks bad but its the only way without modifying phpbb code
	 * this is for select items that do not need to be translated
	 * @param array $options
	 */
	private function _add_lang_vars(array $options)
	{
		foreach ($options as $title)
		{
			if (!$this->translator->is_set($title))
			{
				$this->user->lang[$title] = $title;
			}
		}
	}

	/**
	 * @param array $cfg_array
	 * @param array $df_settings
	 */
	private function _get_multi_select(array &$cfg_array, array $df_settings)
	{
		$multi_select = utf8_normalize_nfc($this->request->variable('config', array('' => array('' => '')), true));
		$multi_select = array_filter($multi_select);

		foreach ($multi_select as $field => $settings)
		{
			$cfg_array[$field] = (!empty($settings)) ? $settings : $df_settings[$field]['default'];
		}
	}
}
