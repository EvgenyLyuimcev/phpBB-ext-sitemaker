<?php
/**
 *
 * @package primetime
 * @copyright (c) 2013 Daniel A. (blitze)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace primetime\core\migrations\v20x;

class m5_add_cblocks_schema extends \phpbb\db\migration\migration
{
	/**
	 * Skip this migration if the pt_cblocks table already exists
	 *
	 * @return bool True to skip this migration, false to run it
	 * @access public
	 */
	public function effectively_installed()
	{
		return $this->db_tools->sql_table_exists($this->table_prefix . 'pt_cblocks');
	}

	/**
	 * @inheritdoc
	 */
	static public function depends_on()
	{
		return array(
			'\primetime\core\migrations\v20x\m1_initial_schema',
		);
	}

	/**
	 * @inheritdoc
	 */
	public function update_schema()
	{
		return array(
			'add_tables'	=> array(
				$this->table_prefix . 'pt_cblocks' => array(
					'COLUMNS'		=> array(
						'block_id'			=> array('UINT', NULL, 'auto_increment'),
						'block_content'		=> array('TEXT_UNI', ''),
						'bbcode_bitfield'	=> array('VCHAR:255', ''),
						'bbcode_options'	=> array('UINT:11', 7),
						'bbcode_uid'		=> array('VCHAR:8', ''),
					),

					'PRIMARY_KEY'	=> 'block_id'
				),
			),
		);
	}

	/**
	 * @inheritdoc
	 */
	public function revert_schema()
	{
		return array(
			'drop_tables'	=> array(
				$this->table_prefix . 'pt_cblocks',
			),
		);
	}
}
