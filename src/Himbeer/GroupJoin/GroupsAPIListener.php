<?php

declare(strict_types=1);

namespace Himbeer\GroupJoin;

use alvin0319\GroupsAPI\group\GroupWrapper;
use alvin0319\GroupsAPI\GroupsAPI;
use alvin0319\GroupsAPI\user\Member;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\promise\Promise;

class GroupsAPIListener implements Listener {
	private Main $plugin;

	private GroupsAPI $groupsAPI;

	public function __construct(Main $plugin, GroupsAPI $groupsAPI) {
		$this->plugin = $plugin;
		$this->groupsAPI = $groupsAPI;
	}

	/**
	 * @param Member $member
	 *
	 * @return string[]
	 */
	private static function getMemberGroupNames(Member $member) : array {
		return array_map(function(GroupWrapper $groupWrapper) {
			return $groupWrapper->getGroup()->getName();
		}, $member->getGroups()
		);
	}

	/**
	 * @param Player   $player
	 * @param callable $callback Callback with the group names in an array as the first parameter
	 */
	private function getGroupNamesForPlayer(Player $player, callable $callback) : void {
		$member = $this->groupsAPI->getMemberManager()->loadMember($player->getName());
		if ($member instanceof Member) {
			$callback(self::getMemberGroupNames($member));
		} else if ($member instanceof Promise) {
			$member->onCompletion(function(Member $member) use ($callback) : void {
				$callback(self::getMemberGroupNames($member));
			}, function() use ($callback) : void {
				$callback([]);
			});
		}
	}

	private function getAndSendMessageForPlayer(Player $player, string $type, $originalMessage) : void {
		$this->getGroupNamesForPlayer($player, function(array $groupNames) use ($type, $originalMessage, $player) {
			if (count($groupNames) === 0) {
				$this->plugin->getServer()->broadcastMessage($originalMessage);
			} else {
				$msg = $this->plugin->getMessageForGroupNames($groupNames, $type, $player->getName());
				if ($msg === null) {
					if (!$this->plugin->hideOther) {
						$this->plugin->getServer()->broadcastMessage($originalMessage);
					}
				} else {
					$this->plugin->getServer()->broadcastMessage($msg);
				}
			}
		});
	}

	public function onJoin(PlayerJoinEvent $event) {
		$this->getAndSendMessageForPlayer($event->getPlayer(), "join", $event->getJoinMessage());
		$event->setJoinMessage("");
	}

	public function onQuit(PlayerQuitEvent $event) {
		$this->getAndSendMessageForPlayer($event->getPlayer(), "leave", $event->getQuitMessage());
		$event->setQuitMessage("");
	}
}