<?php
/**
 *
 * @package sitemaker
 * @copyright (c) 2013 Daniel A. (blitze)
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace blitze\sitemaker\blocks;

/**
* Stats Block
*/
class stats extends \blitze\sitemaker\services\blocks\driver\block
{
	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\language\language */
	protected $translator;

	/**
	 * Constructor
	 *
	 * @param \phpbb\config\config			$config			phpBB configuration
	 * @param \phpbb\language\language		$translator		Language object
	 */
	public function __construct(\phpbb\config\config $config, \phpbb\language\language $translator)
	{
		$this->config = $config;
		$this->translator = $translator;
	}

	/**
	 * {@inheritdoc}
	 */
	public function display(array $settings, $edit_mode = false)
	{
		$content = '';
		$content .= $this->translator->lang('TOTAL_POSTS_COUNT', (int) $this->config['num_posts']) . '<br />';
		$content .= $this->translator->lang('TOTAL_TOPICS', (int) $this->config['num_topics']) . '<br />';
		$content .= $this->translator->lang('TOTAL_USERS', (int) $this->config['num_users']) . '<br />';
		$content .= $this->translator->lang('NEWEST_USER', get_username_string('full', $this->config['newest_user_id'], $this->config['newest_username'], $this->config['newest_user_colour']));

		return array(
			'title'		=> 'STATISTICS',
			'content'	=> $content,
		);
	}
}
