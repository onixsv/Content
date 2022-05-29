<?php

declare(strict_types=1);

namespace Content\event;

use Content\Content;
use pocketmine\event\player\PlayerEvent;
use pocketmine\player\Player;

class ContentClearEvent extends PlayerEvent{
	/** @var Content */
	protected Content $content;

	protected array $rewards = [];

	public function __construct(Player $player, Content $content, array $rewards){
		$this->player = $player;
		$this->content = $content;

		$this->rewards = $rewards;
	}

	public function getContent() : Content{
		return $this->content;
	}

	public function getRewards() : array{
		return $this->rewards;
	}

	public function setRewards(array $rewards) : void{
		$this->rewards = $rewards;
	}
}