<?php

namespace Detain\MyAdminSwift;

use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Plugin
 *
 * @package Detain\MyAdminSwift
 */
class Plugin
{
	public static $name = 'Swift Plugin';
	public static $description = 'Allows handling of Swift based Backups';
	public static $help = '';
	public static $type = 'plugin';

	/**
	 * Plugin constructor.
	 */
	public function __construct()
	{
	}

	/**
	 * @return array
	 */
	public static function getHooks()
	{
		return [
			'system.settings' => [__CLASS__, 'getSettings'],
			//'ui.menu' => [__CLASS__, 'getMenu'],
			'function.requirements' => [__CLASS__, 'getRequirements']
		];
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getMenu(GenericEvent $event)
	{
		$menu = $event->getSubject();
		if ($GLOBALS['tf']->ima == 'admin') {
			function_requirements('has_acl');
			if (has_acl('client_billing')) {
				$menu->add_link('admin', 'choice=none.abuse_admin', '/lib/webhostinghub-glyphs-icons/icons/development-16/Black/icon-spam.png', __('Swift'));
			}
		}
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
	public static function getRequirements(GenericEvent $event)
	{
        /**
         * @var \MyAdmin\Plugins\Loader $this->loader
         */
        $loader = $event->getSubject();
		$loader->add_requirement('class.Swift', '/../vendor/detain/myadmin-swift-backups/src/Swift.php');
	}

	/**
	 * @param \Symfony\Component\EventDispatcher\GenericEvent $event
	 */
    public static function getSettings(GenericEvent $event)
    {
        /**
         * @var \MyAdmin\Settings $settings
         **/
        $settings = $event->getSubject();
		$settings->add_text_setting(__('Backups'), __('Swift'), 'swift_auth_url', __('Swift Auth URL'), __('Swift Auth URL'), SWIFT_AUTH_URL);
		$settings->add_text_setting(__('Backups'), __('Swift'), 'swift_auth_v1_url', __('Swift Auth v1 URL'), __('Swift Auth v1 URL'), SWIFT_AUTH_V1_URL);
		$settings->add_text_setting(__('Backups'), __('Swift'), 'swift_admin_user', __('Swift Admin User'), __('Swift Admin User'), SWIFT_ADMIN_USER);
		$settings->add_text_setting(__('Backups'), __('Swift'), 'swift_admin_key', __('Swift Admin Key'), __('Swift Admin Key'), SWIFT_ADMIN_KEY);
	}
}
