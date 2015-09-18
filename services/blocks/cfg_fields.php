<?php
/**
 *
 * @package sitemaker
 * @copyright (c) 2015 Daniel A. (blitze)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace blitze\sitemaker\services\blocks;

class cfg_fields
{
	/** @var \phpbb\user */
	protected $user;

	/**
	 * Constructor
	 *
	 * @param \phpbb\user	$user	User object
	 */
	public function __construct(\phpbb\user $user)
	{
		$this->user = $user;
	}

	/**
	 * Used to add multi-select dropdown in blocks config
	 */
	public function build_multi_select($option_ary, $selected_items, $key)
	{
		$selected_items = explode(',', $selected_items);

		$html = '<select id="' . $key . '" name="config[' . $key . '][]" multiple="multiple">';
		foreach ($option_ary as $value => $title)
		{
			$title = $this->user->lang($title);
			$selected = (in_array($value, $selected_items)) ? ' selected="selected"' : '';
			$html .= '<option value="' . $value . '"' . $selected . '>' . $title . '</option>';
		}
		$html .= '</select>';

		return $html;
	}

	/**
	 * Used to build multi-column checkboxes for blocks config
	 */
	public function build_checkbox($option_ary, $selected_items, $key)
	{
		$selected_items = explode(',', $selected_items);
		$id_assigned = false;
		$html = '';

		$test = current($option_ary);
		if (!is_array($test))
		{
			$option_ary = array($option_ary);
		}

		foreach ($option_ary as $col => $row)
		{
			$html .= '<div class="unit sizeAuto ' . $key . '-checkbox" id="' . $key . '-col-' . $col . '">';
			foreach ($row as $value => $title)
			{
				$selected = (in_array($value, $selected_items)) ? ' checked="checked"' : '';
				$title = $this->user->lang($title);
				$html .= '<label><input type="checkbox" name="config[' . $key . '][]"' . ((!$id_assigned) ? ' id="' . $key . '"' : '') . ' value="' . $value . '"' . $selected . (($key) ? ' accesskey="' . $key . '"' : '') . ' class="checkbox" /> ' . $title . '</label><br />';
				$id_assigned = true;
			}
			$html .= '</div>';
		}

		return $html;
	}

	/**
	 * build hidden field for blocks config
	 */
	public function build_hidden($value, $key)
	{
		return '<input type="hidden" name="config[' . $key . ']" value="' . $value . '" />';
	}
}