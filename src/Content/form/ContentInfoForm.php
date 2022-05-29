<?php
declare(strict_types=1);

namespace Content\form;

use Content\Content;
use OnixUtils\OnixUtils;
use pocketmine\form\Form;
use pocketmine\player\Player;
use function array_keys;
use function array_map;
use function array_values;
use function implode;

class ContentInfoForm implements Form{

	/** @var Content */
	protected Content $content;

	public function __construct(Content $content){
		$this->content = $content;
	}

	public function jsonSerialize() : array{
		$sorted = $this->content->getTopRanks(1);
		$c = 0;
		return [
			"type" => "form",
			"title" => "§l컨텐츠 순위 TOP 5",
			"content" => implode("\n", array_map(function(string $name, int $time) use (&$c) : string{
				++$c;
				return "§f[§d{$c}§f위] §d{$name}§f님: " . OnixUtils::convertTimeToString($time);
			}, array_keys($sorted), array_values($sorted))),
			"buttons" => [
				["text" => "§l나가기\n현재 컨텐츠 창에서 나갑니다."]
			]
		];
	}

	public function handleResponse(Player $player, $data) : void{
	}
}