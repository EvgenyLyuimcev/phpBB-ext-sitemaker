<?php
/**
 *
 * @package primetime
 * @copyright (c) 2013 Daniel A. (blitze)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace primetime\primetime\migrations\converter;

class c1_remove_modules extends \phpbb\db\migration\migration
{
	/**
	 * Skip this migration if the module_dir column does not exist
	 *
	 * @return bool True to skip this migration, false to run it
	 * @access public
	 */
	public function effectively_installed()
	{
		return !$this->db_tools->sql_column_exists($this->table_prefix . 'modules', 'module_dir');
	}

	public function update_data()
	{
		$sql = 'SELECT *
			FROM ' . MODULES_TABLE . "
			WHERE module_dir <> ''
				OR " . $this->db->sql_in_set('module_langname', array('CMS', 'GCP', 'GRP', 'PRO')) . '
			ORDER BY right_id ASC';
		$result = $this->db->sql_query($sql);

		$migrations_ary = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$migrations_ary[] = array('module.remove', array($row['module_class'], $row['parent_id'], $row['module_id']));
		}
		$this->db->sql_freeresult($result);

		return $migrations_ary;
	}
}